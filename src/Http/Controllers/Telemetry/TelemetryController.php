<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Http\Controllers\Telemetry;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use SplFileObject;
use WireNinja\Accelerator\Telemetry\TelemetryDatabase;

class TelemetryController
{
    public function __construct(
        private readonly TelemetryDatabase $database,
    ) {}

    /**
     * List exception groups with optional status filter.
     */
    public function index(Request $request): View
    {
        $pdo = $this->database->connection();
        $status = $request->query('status');
        $page = max(1, (int) $request->query('page', '1'));
        $perPage = 25;

        if ($pdo === null) {
            return view('accelerator::telemetry.index', [
                'groups' => new LengthAwarePaginator([], 0, $perPage),
            ]);
        }

        $where = '';
        $params = [];

        if ($status && in_array($status, ['open', 'resolved', 'muted'], true)) {
            $where = 'WHERE status = :status';
            $params['status'] = $status;
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM exception_groups {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $offset = ($page - 1) * $perPage;
        $dataStmt = $pdo->prepare(<<<SQL
            SELECT
                eg.*,
                (
                    SELECT eo.message
                    FROM exception_occurrences eo
                    WHERE eo.group_id = eg.id
                    ORDER BY eo.created_at DESC
                    LIMIT 1
                ) AS latest_message,
                (
                    SELECT eo.user_name
                    FROM exception_occurrences eo
                    WHERE eo.group_id = eg.id
                    ORDER BY eo.created_at DESC
                    LIMIT 1
                ) AS latest_user_name,
                (
                    SELECT eo.user_username
                    FROM exception_occurrences eo
                    WHERE eo.group_id = eg.id
                    ORDER BY eo.created_at DESC
                    LIMIT 1
                ) AS latest_user_username,
                (
                    SELECT eo.user_email
                    FROM exception_occurrences eo
                    WHERE eo.group_id = eg.id
                    ORDER BY eo.created_at DESC
                    LIMIT 1
                ) AS latest_user_email,
                (
                    SELECT COUNT(DISTINCT eo.user_id)
                    FROM exception_occurrences eo
                    WHERE eo.group_id = eg.id AND eo.user_id IS NOT NULL
                ) AS user_count,
                (
                    SELECT eo.duration_ms
                    FROM exception_occurrences eo
                    WHERE eo.group_id = eg.id
                    ORDER BY eo.created_at DESC
                    LIMIT 1
                ) AS latest_duration_ms,
                (
                    SELECT eo.db_query_count
                    FROM exception_occurrences eo
                    WHERE eo.group_id = eg.id
                    ORDER BY eo.created_at DESC
                    LIMIT 1
                ) AS latest_db_query_count,
                (
                    SELECT eo.db_duration_ms
                    FROM exception_occurrences eo
                    WHERE eo.group_id = eg.id
                    ORDER BY eo.created_at DESC
                    LIMIT 1
                ) AS latest_db_duration_ms
            FROM exception_groups eg
            {$where}
            ORDER BY eg.last_seen_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        SQL);
        $dataStmt->execute($params);
        $rows = $dataStmt->fetchAll();

        $paginator = new LengthAwarePaginator(
            $rows,
            $total,
            $perPage,
            $page,
            ['path' => route('accelerator.telemetry.index'), 'query' => $request->query()]
        );

        return view('accelerator::telemetry.index', [
            'groups' => $paginator,
        ]);
    }

    /**
     * Show exception group detail with recent occurrences.
     */
    public function show(Request $request, int $id): View
    {
        $pdo = $this->database->connection();

        abort_if($pdo === null, 503, 'Telemetry database unavailable.');

        $groupStmt = $pdo->prepare('SELECT * FROM exception_groups WHERE id = :id');
        $groupStmt->execute(['id' => $id]);
        $group = $groupStmt->fetch();

        abort_if($group === false, 404);

        $page = max(1, (int) $request->query('page', '1'));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $countStmt = $pdo->prepare('SELECT COUNT(*) as total FROM exception_occurrences WHERE group_id = :id');
        $countStmt->execute(['id' => $id]);
        $total = (int) $countStmt->fetch()['total'];

        $occStmt = $pdo->prepare("SELECT * FROM exception_occurrences WHERE group_id = :id ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}");
        $occStmt->execute(['id' => $id]);
        $occurrences = $occStmt->fetchAll();

        $paginator = new LengthAwarePaginator(
            $occurrences,
            $total,
            $perPage,
            $page,
            ['path' => route('accelerator.telemetry.show', $id)]
        );

        return view('accelerator::telemetry.show', [
            'group' => $group,
            'occurrences' => $paginator,
        ]);
    }

    /**
     * Mark an exception group as resolved.
     */
    public function resolve(int $id): RedirectResponse
    {
        $pdo = $this->database->connection();

        abort_if($pdo === null, 503);

        $stmt = $pdo->prepare("UPDATE exception_groups SET status = 'resolved' WHERE id = :id");
        $stmt->execute(['id' => $id]);

        return back();
    }

    /**
     * Reopen an exception group.
     */
    public function reopen(int $id): RedirectResponse
    {
        $pdo = $this->database->connection();

        abort_if($pdo === null, 503);

        $stmt = $pdo->prepare("UPDATE exception_groups SET status = 'open' WHERE id = :id");
        $stmt->execute(['id' => $id]);

        return back();
    }

    /**
     * Mute an exception group.
     */
    public function mute(int $id): RedirectResponse
    {
        $pdo = $this->database->connection();

        abort_if($pdo === null, 503);

        $stmt = $pdo->prepare("UPDATE exception_groups SET status = 'muted' WHERE id = :id");
        $stmt->execute(['id' => $id]);

        return back();
    }

    /**
     * Paginated log reader (reads laravel.log from the end using SplFileObject).
     *
     * Uses seek-based reading to avoid loading the entire log file into memory.
     * Reads backward from EOF and groups continuation lines into one Laravel log entry.
     */
    public function logs(Request $request): View
    {
        $logPath = storage_path('logs/laravel.log');
        $perPage = 25;
        $page = max(1, (int) $request->query('page', '1'));

        if (! File::exists($logPath) || File::size($logPath) === 0) {
            return view('accelerator::telemetry.logs', [
                'entries' => [],
                'logFile' => 'laravel.log',
                'page' => $page,
                'hasMore' => false,
            ]);
        }

        $result = $this->readLogEntries($logPath, $page, $perPage);

        return view('accelerator::telemetry.logs', [
            'entries' => $result['entries'],
            'logFile' => 'laravel.log',
            'page' => $page,
            'hasMore' => $result['has_more'],
        ]);
    }

    /**
     * @return array{entries: array<int, array{timestamp: string|null, level: string|null, summary: string, body: string, line_count: int}>, has_more: bool}
     */
    private function readLogEntries(string $logPath, int $page, int $perPage): array
    {
        $file = new SplFileObject($logPath, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();

        $skip = ($page - 1) * $perPage;
        $seen = 0;
        $entries = [];
        $currentLines = [];
        $hasMore = false;

        for ($lineNumber = $lastLine; $lineNumber >= 0; $lineNumber--) {
            $file->seek($lineNumber);
            $line = $file->current();

            if ($line !== false) {
                $line = rtrim((string) $line);
            }

            if ($line === false || ($line === '' && $lineNumber === $lastLine)) {
                continue;
            }

            $currentLines[] = $line;

            if (! $this->isLogEntryStart($line)) {
                continue;
            }

            $seen++;

            if ($seen > ($skip + $perPage)) {
                $hasMore = true;

                break;
            }

            if ($seen > $skip && count($entries) < $perPage) {
                $entries[] = $this->formatLogEntry(array_reverse($currentLines));
            }

            $currentLines = [];
        }

        if ($currentLines !== []) {
            $seen++;

            if ($seen > ($skip + $perPage)) {
                $hasMore = true;
            } elseif ($seen > $skip && count($entries) < $perPage) {
                $entries[] = $this->formatLogEntry(array_reverse($currentLines));
            }
        }

        return [
            'entries' => $entries,
            'has_more' => $hasMore,
        ];
    }

    private function isLogEntryStart(string $line): bool
    {
        return preg_match('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $line) === 1;
    }

    /**
     * @param  list<string>  $lines
     * @return array{timestamp: string|null, level: string|null, summary: string, body: string, line_count: int}
     */
    private function formatLogEntry(array $lines): array
    {
        $firstLine = $lines[0] ?? '';
        $timestamp = null;
        $level = null;
        $summary = $firstLine;

        if (preg_match('/^\[(?<timestamp>[^\]]+)\]\s+\w+\.(?<level>\w+):\s*(?<message>.*)$/', $firstLine, $matches) === 1) {
            $timestamp = $matches['timestamp'];
            $level = strtolower($matches['level']);
            $summary = $matches['message'] !== '' ? $matches['message'] : $firstLine;
        }

        return [
            'timestamp' => $timestamp,
            'level' => $level,
            'summary' => $summary,
            'body' => implode(PHP_EOL, $lines),
            'line_count' => count($lines),
        ];
    }
}

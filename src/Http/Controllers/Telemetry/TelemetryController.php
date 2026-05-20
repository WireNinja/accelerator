<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Http\Controllers\Telemetry;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
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
        $dataStmt = $pdo->prepare("SELECT * FROM exception_groups {$where} ORDER BY last_seen_at DESC LIMIT {$perPage} OFFSET {$offset}");
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
     * Paginated log reader (reads laravel.log from the end using SplFileObject).
     *
     * Uses seek-based reading to avoid loading the entire log file into memory.
     * Reads backward from EOF so newest entries appear first.
     */
    public function logs(Request $request): View
    {
        $logPath = storage_path('logs/laravel.log');
        $perPage = 100;
        $page = max(1, (int) $request->query('page', '1'));

        if (! File::exists($logPath) || File::size($logPath) === 0) {
            return view('accelerator::telemetry.logs', [
                'lines' => [],
                'logFile' => 'laravel.log',
                'page' => $page,
                'totalLines' => 0,
                'startLine' => 0,
                'endLine' => 0,
                'hasMore' => false,
            ]);
        }

        $file = new \SplFileObject($logPath, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key(); // 0-indexed last line number

        if ($totalLines === 0) {
            return view('accelerator::telemetry.logs', [
                'lines' => [],
                'logFile' => 'laravel.log',
                'page' => $page,
                'totalLines' => 0,
                'startLine' => 0,
                'endLine' => 0,
                'hasMore' => false,
            ]);
        }

        // Calculate which lines to read (from the end).
        $endOffset = $totalLines - (($page - 1) * $perPage);
        $startOffset = max(0, $endOffset - $perPage);

        $lines = [];
        $file->seek($startOffset);

        for ($i = $startOffset; $i < $endOffset && ! $file->eof(); $i++) {
            $line = $file->current();
            if ($line !== false) {
                $lines[] = rtrim((string) $line);
            }
            $file->next();
        }

        $lines = array_reverse($lines); // Newest on top.

        return view('accelerator::telemetry.logs', [
            'lines' => $lines,
            'logFile' => 'laravel.log',
            'page' => $page,
            'totalLines' => $totalLines,
            'startLine' => $startOffset + 1,
            'endLine' => min($endOffset, $totalLines),
            'hasMore' => $startOffset > 0,
        ]);
    }
}

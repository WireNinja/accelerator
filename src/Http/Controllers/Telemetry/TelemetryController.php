<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Http\Controllers\Telemetry;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use SplFileObject;
use Throwable;
use WireNinja\Accelerator\Telemetry\TelemetryBuffer;
use WireNinja\Accelerator\Telemetry\TelemetryStore;

final class TelemetryController
{
    public function __construct(
        private readonly TelemetryStore $store,
        private readonly TelemetryBuffer $buffer,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $status = in_array($status, ['open', 'resolved', 'muted'], true) ? $status : null;
        $page = max(1, $request->integer('page', 1));
        $perPage = 25;

        try {
            $result = $this->store->groups($status, $page, $perPage);
            $statistics = $this->store->statistics();
        } catch (Throwable $exception) {
            abort(503, 'Telemetry database unavailable: '.$exception->getMessage());
        }

        return view('accelerator::telemetry.index', [
            'activeStatus' => $status,
            'groups' => new LengthAwarePaginator(
                $result['items'],
                $result['total'],
                $perPage,
                $page,
                ['path' => route('accelerator.telemetry.index'), 'query' => $request->query()],
            ),
            'runtime' => $this->buffer->health(),
            'statistics' => $statistics,
        ]);
    }

    public function show(Request $request, int $id): View
    {
        try {
            $group = $this->store->group($id);
        } catch (Throwable $exception) {
            abort(503, 'Telemetry database unavailable: '.$exception->getMessage());
        }

        abort_if($group === null, 404);

        $page = max(1, $request->integer('page', 1));
        $perPage = 20;

        try {
            $result = $this->store->occurrences($id, $page, $perPage);
        } catch (Throwable $exception) {
            abort(503, 'Telemetry database unavailable: '.$exception->getMessage());
        }

        $occurrences = new LengthAwarePaginator(
            $result['items'],
            $result['total'],
            $perPage,
            $page,
            ['path' => route('accelerator.telemetry.show', $id)],
        );
        $latest = $occurrences->getCollection()->first();

        return view('accelerator::telemetry.show', [
            'group' => $group,
            'occurrences' => $occurrences,
            'latest' => is_array($latest) ? $latest : null,
            'requestContext' => is_array($latest['context'] ?? null) ? $latest['context'] : [],
            'source' => $this->source((string) $group['source_file'], (int) $group['source_line']),
            'markdown' => $this->markdown($group, is_array($latest) ? $latest : null),
        ]);
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['open', 'resolved', 'muted'])],
        ]);

        abort_unless($this->store->updateStatus($id, $validated['status']), 404);

        return back()->with('status', 'Telemetry group updated.');
    }

    /**
     * @return list<array{line: int, code: string, highlighted: bool}>
     */
    private function source(string $relativePath, int $line): array
    {
        if ($relativePath === '' || str_starts_with($relativePath, '/') || str_contains($relativePath, '..')) {
            return [];
        }

        $basePath = realpath(base_path());
        $path = realpath(base_path($relativePath));

        if (
            $basePath === false
            || $path === false
            || ! str_starts_with($path, $basePath.DIRECTORY_SEPARATOR)
            || ! is_file($path)
            || ! is_readable($path)
        ) {
            return [];
        }

        $file = new SplFileObject($path, 'r');
        $start = max(1, $line - 5);
        $end = $line + 5;
        $source = [];

        for ($current = $start; $current <= $end; $current++) {
            $file->seek($current - 1);

            if ($file->eof() && $file->current() === false) {
                break;
            }

            $source[] = [
                'line' => $current,
                'code' => rtrim((string) $file->current(), "\r\n"),
                'highlighted' => $current === $line,
            ];
        }

        return $source;
    }

    /**
     * @param  array<string, mixed>  $group
     * @param  array<string, mixed>|null  $latest
     */
    private function markdown(array $group, ?array $latest): string
    {
        $lines = [
            '# '.class_basename((string) $group['exception_class']),
            '',
            '- Status: '.$group['status'],
            '- Exception: `'.$group['exception_class'].'`',
            '- Location: `'.$group['source_file'].':'.$group['source_line'].'`',
            '- Route: '.($group['route_name'] ?? 'n/a'),
            '- Occurrences: '.$group['occurrence_count'],
            '- First seen: '.$group['first_seen_at'],
            '- Last seen: '.$group['last_seen_at'],
            '',
            '## Message',
            '',
            (string) $group['message'],
        ];

        if ($latest !== null) {
            $lines = [
                ...$lines,
                '',
                '## Latest occurrence',
                '',
                '- User: '.($latest['user_label'] ?? $latest['user_id']),
                '- Method: '.($latest['method'] ?? 'n/a'),
                '- At: '.$latest['occurred_at'],
                '',
                '```text',
                (string) $latest['stack_trace'],
                '```',
            ];
        }

        return implode(PHP_EOL, $lines);
    }
}

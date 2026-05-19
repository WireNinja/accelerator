<?php

namespace WireNinja\Accelerator\Console\Filament;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use JsonException;
use Throwable;
use WireNinja\Accelerator\Support\Filament\ResourceContextScanner;

/**
 * Hard pass/fail verification of a Filament resource against Accelerator
 * conventions documented in resources/boost/skills/accelerator-filament/SKILL.md.
 *
 * Designed to be run by AI agents after touching a resource — JSON output
 * cannot be bullshitted into "selesai". Exit code:
 *   0  PASS    — all checks satisfied
 *   1  FAIL    — at least one critical violation
 *   2  WARNING — only non-critical findings
 */
#[Signature('accelerator:verify-resource
    {resource : Resource key (e.g. user) or class FQN}
    {--json : Output JSON instead of table}
    {--compact : Compact JSON for piping}')]
#[Description('Verify a Filament resource against Accelerator FILAMENT.md conventions. Returns PASS/FAIL/WARNING with findings — anti-bullshit gate for AI agents.')]
class VerifyResourceCommand extends Command
{
    public function handle(ResourceContextScanner $scanner): int
    {
        $key = (string) $this->argument('resource');

        try {
            $payload = $scanner->scan(resource: $key, includeRegistry: false);
        } catch (Throwable $throwable) {
            $this->writeOutput([
                'status' => 'FAIL',
                'resource' => $key,
                'findings' => [[
                    'severity' => 'critical',
                    'check' => 'scan',
                    'message' => $throwable->getMessage(),
                ]],
            ]);

            return self::FAILURE;
        }

        $resource = $payload['resource'] ?? null;

        if (! is_array($resource)) {
            $this->writeOutput([
                'status' => 'FAIL',
                'resource' => $key,
                'findings' => [[
                    'severity' => 'critical',
                    'check' => 'lookup',
                    'message' => "Resource [{$key}] not found in registry. Did you register it in ResourceEnum?",
                ]],
            ]);

            return self::FAILURE;
        }

        $findings = [];

        // 1. Discovery attribute present
        $discovery = $resource['discovery'] ?? [];
        if (empty($discovery['annotated_as_resource'])) {
            $findings[] = $this->finding('critical', 'discovery', 'Missing #[DiscoverAsResource] attribute on resource class.');
        }

        // 2. BetterResource trait
        $traits = $resource['traits'] ?? $resource['source']['traits'] ?? null;
        $resourceClass = (string) ($resource['class'] ?? '');
        if ($resourceClass !== '' && class_exists($resourceClass)) {
            $usedTraits = class_uses_recursive($resourceClass);
            if (! in_array('WireNinja\\Accelerator\\Filament\\Traits\\BetterResource', $usedTraits, true)) {
                $findings[] = $this->finding('critical', 'trait', 'Resource class does not use BetterResource trait.');
            }
        }

        // 3. Form + table classes split
        $formClass = $resource['form']['definition_class']['class'] ?? null;
        $tableClass = $resource['table']['definition_class']['class'] ?? null;
        if (empty($formClass) || $formClass === $resourceClass) {
            $findings[] = $this->finding('warning', 'form-split', 'Form schema not split into a dedicated class. FILAMENT.md prefers Schemas/<Name>Form.php.');
        }
        if (empty($tableClass) || $tableClass === $resourceClass) {
            $findings[] = $this->finding('warning', 'table-split', 'Table not split into a dedicated class. FILAMENT.md prefers Tables/<Names>Table.php.');
        }

        // 4. Bulk actions ban
        $tableActions = $resource['table']['actions'] ?? [];
        $bulkLeak = false;
        foreach ($tableActions as $action) {
            $type = is_array($action) ? ($action['type'] ?? '') : '';
            if (is_string($type) && (str_contains(strtolower($type), 'bulk') || str_contains(strtolower($type), 'deletebulkaction'))) {
                $bulkLeak = true;
                break;
            }
        }
        if (! empty($resource['table']['toolbar_actions']) || ! empty($resource['table']['bulk_actions']) || $bulkLeak) {
            $findings[] = $this->finding('critical', 'bulk-actions', 'Bulk action API detected. FILAMENT.md forbids bulk semantics — remove BulkAction / BulkActionGroup / toolbarActions().');
        }

        // 5. Empty state declared
        $emptyState = $resource['table']['empty_state'] ?? null;
        if (! is_array($emptyState) || (empty($emptyState['heading']) && empty($emptyState['actions']))) {
            $findings[] = $this->finding('warning', 'empty-state', 'Table emptyState heading/actions not detected. FILAMENT.md requires emptyStateActions([CreateAction::make()]).');
        }

        // 6. Authorization — resource MUST have a policy registered
        $policy = $resource['authorization']['policy'] ?? null;
        if (! is_array($policy) || empty($policy['class'])) {
            $findings[] = $this->finding('critical', 'policy', 'No policy class registered for the resource. Run shield:safe-regenerate after registering in ResourceEnum.');
        }

        // 7. Pages — must have at least 1 page registered
        $pages = $resource['pages'] ?? [];
        if (! is_array($pages) || $pages === []) {
            $findings[] = $this->finding('warning', 'pages', 'No pages discovered. Resource must register at least an index page.');
        }

        $status = 'PASS';
        foreach ($findings as $finding) {
            if ($finding['severity'] === 'critical') {
                $status = 'FAIL';
                break;
            }
            $status = 'WARNING';
        }

        $this->writeOutput([
            'status' => $status,
            'resource' => $key,
            'class' => $resourceClass,
            'findings' => $findings,
            'summary' => [
                'total' => count($findings),
                'critical' => count(array_filter($findings, static fn ($f) => $f['severity'] === 'critical')),
                'warnings' => count(array_filter($findings, static fn ($f) => $f['severity'] === 'warning')),
            ],
        ]);

        return match ($status) {
            'PASS' => self::SUCCESS,
            'WARNING' => 2,
            default => self::FAILURE,
        };
    }

    /**
     * @return array{severity: string, check: string, message: string}
     */
    protected function finding(string $severity, string $check, string $message): array
    {
        return ['severity' => $severity, 'check' => $check, 'message' => $message];
    }

    protected function writeOutput(array $payload): void
    {
        if ($this->option('json')) {
            try {
                $json = json_encode(
                    $payload,
                    ($this->option('compact') ? 0 : JSON_PRETTY_PRINT) | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                );
                $this->output->writeln($json);
            } catch (JsonException $exception) {
                $this->components->error($exception->getMessage());
            }

            return;
        }

        $this->components->{match ($payload['status']) {
            'PASS' => 'success',
            'WARNING' => 'warn',
            default => 'error',
        }}("Status: {$payload['status']} — {$payload['resource']}");

        if (! empty($payload['findings'])) {
            $this->table(
                ['Severity', 'Check', 'Message'],
                array_map(static fn (array $f): array => [$f['severity'], $f['check'], $f['message']], $payload['findings']),
            );
        } else {
            $this->components->info('No findings.');
        }
    }
}

<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console;

use Illuminate\Console\Command;
use JsonException;
use RuntimeException;
use Symfony\Component\Process\Process;

final class DependenciesCommand extends Command
{
    protected $signature = 'accelerator:dependencies {--json : Output as JSON}';

    protected $description = 'Report Composer currency, advisories, blockers, and frontend lock ownership';

    /** @throws JsonException */
    public function handle(): int
    {
        try {
            $direct = $this->composer(['outdated', '--direct', '--format=json']);
            $all = $this->composer(['outdated', '--format=json']);
            $audit = $this->composer(['audit', '--locked', '--format=json'], allowFailure: true);
            $frontend = $this->frontend();
            $directPackages = $this->packages($direct);
            $allPackages = $this->packages($all);
            $transitive = array_values(array_filter(
                $allPackages,
                static fn (array $package): bool => ! in_array($package['name'] ?? null, array_column($directPackages, 'name'), true),
            ));
            $advisories = is_array($audit['advisories'] ?? null) ? $audit['advisories'] : [];
            $abandoned = is_array($audit['abandoned'] ?? null) ? $audit['abandoned'] : [];
            $payload = [
                'schema' => 1,
                'status' => $advisories === [] ? 'OK' : 'ERROR',
                'composer' => [
                    'direct_outdated' => $directPackages,
                    'transitive_outdated' => $transitive,
                    'advisories' => $advisories,
                    'abandoned' => $abandoned,
                    'blocker_command' => 'composer why-not <package> <latest-version>',
                ],
                'frontend' => $frontend,
            ];

            if ($this->option('json')) {
                $this->output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            } else {
                $this->components->info('Composer');
                $this->line('Direct outdated: '.count($directPackages));
                $this->line('Transitive outdated: '.count($transitive));
                $this->line('Security advisories: '.count($advisories));
                $this->components->info('Frontend');
                $this->line("Manager: {$frontend['manager']}");
                $this->line("Lockfile: {$frontend['lockfile']}");
            }

            return $advisories === [] ? self::SUCCESS : self::FAILURE;
        } catch (RuntimeException $exception) {
            if ($this->option('json')) {
                $this->output->writeln(json_encode([
                    'schema' => 1,
                    'status' => 'ERROR',
                    'error' => $exception->getMessage(),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            } else {
                $this->components->error($exception->getMessage());
            }

            return self::FAILURE;
        }
    }

    /** @param list<string> $arguments @return array<string, mixed> */
    private function composer(array $arguments, bool $allowFailure = false): array
    {
        $process = new Process(['composer', ...$arguments], base_path());
        $process->setTimeout(null);
        $process->run();
        $output = trim($process->getOutput());

        if ($output === '' || (! $allowFailure && ! $process->isSuccessful())) {
            throw new RuntimeException('Composer dependency inspection failed: '.trim($process->getErrorOutput()));
        }

        $decoded = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('Composer returned an invalid dependency report.');
        }

        return $decoded;
    }

    /** @param array<string, mixed> $report @return list<array<string, mixed>> */
    private function packages(array $report): array
    {
        $installed = $report['installed'] ?? [];

        return is_array($installed) ? array_values(array_filter($installed, is_array(...))) : [];
    }

    /** @return array{manager: string, lockfile: string, policy: string} */
    private function frontend(): array
    {
        $contents = @file_get_contents(base_path('package.json'));
        $package = is_string($contents) ? json_decode($contents, true) : null;
        $specification = is_array($package) && is_string($package['packageManager'] ?? null) ? $package['packageManager'] : '';
        $manager = str_starts_with($specification, 'npm@') ? 'npm' : 'pnpm';

        return [
            'manager' => $specification,
            'lockfile' => $manager === 'npm' ? 'package-lock.json' : 'pnpm-lock.yaml',
            'policy' => $manager === 'npm' ? '.npmrc' : 'pnpm-workspace.yaml',
        ];
    }
}

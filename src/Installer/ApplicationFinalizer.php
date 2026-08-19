<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Installer;

use Closure;
use JsonException;
use RuntimeException;
use WireNinja\Accelerator\Support\Cast;

final readonly class ApplicationFinalizer
{
    public function __construct(private InstallContext $context) {}

    public function finalize(InstallJournal $journal): void
    {
        $runner = $this->context->processRunner;
        $root = $this->context->projectRoot;
        $this->operation($journal, 'application.autoload', fn () => $runner->run(['composer', 'dump-autoload', '--no-interaction'], $root));
        $this->publishApplicationMigrations($journal);
        $this->operation($journal, 'application.database-file', $this->ensureLocalDatabase(...));
        $this->operation($journal, 'application.language.add', fn () => $runner->run(['php', 'artisan', 'lang:add', 'id', '--no-interaction'], $root));
        $this->operation($journal, 'application.language.update', fn () => $runner->run(['php', 'artisan', 'lang:update', '--no-interaction'], $root));
        $this->operation($journal, 'application.database.rebuild', fn () => $runner->run(['php', 'artisan', 'migrate:fresh', '--seed', '--force', '--no-interaction'], $root));
        $this->operation($journal, 'application.storage-link', fn () => $runner->run(['php', 'artisan', 'storage:link', '--force', '--no-interaction'], $root));
        $this->operation($journal, 'application.shield', fn () => $runner->run(['php', 'artisan', 'shield:safe-regenerate', '--no-interaction'], $root));
        $this->operation($journal, 'application.admin', fn () => $runner->run([
            'php',
            'artisan',
            'accelerator:provision-admin',
            '--name='.$this->context->plan->adminName,
            '--username='.$this->context->plan->adminUsername,
            '--email='.$this->context->plan->adminEmail,
            '--no-interaction',
        ], $root, [
            'ACCELERATOR_ADMIN_PASSWORD_HASH' => $this->context->plan->adminPasswordHash,
        ]));

        if ($this->context->hasFeature('pwa')) {
            $this->operation($journal, 'application.pwa-icons', fn () => $runner->run($this->context->packageBinaryArguments('laravel-pwa', 'icons'), $root));
        }

        $this->operation($journal, 'application.frontend-build', fn () => $runner->run([$this->context->plan->packageManager, 'run', 'build'], $root));
    }

    /** @throws JsonException */
    public function quality(): void
    {
        $this->selectAcceleratorBoostResources();
        $runner = $this->context->processRunner;
        $root = $this->context->projectRoot;
        $runner->run(['php', 'artisan', 'boost:install', '--ansi', '--no-interaction'], $root);
        $this->assertAcceleratorBoostResources();
        $runner->run(['vendor/bin/pint', '--format=agent'], $root);
        $runner->run(['composer', 'phpstan'], $root);
        $runner->run([$this->context->plan->packageManager, 'run', 'lint:check'], $root);
        $runner->run([$this->context->plan->packageManager, 'run', 'format'], $root);
        $runner->run([$this->context->plan->packageManager, 'run', 'format:check'], $root);
        $runner->run([$this->context->plan->packageManager, 'run', 'types:check'], $root);
        $runner->run(['php', 'artisan', 'accelerator:doctor'], $root);
    }

    private function publishApplicationMigrations(InstallJournal $journal): void
    {
        $commands = [
            ['php', 'artisan', 'make:notifications-table', '--no-interaction'],
            ['php', 'artisan', 'vendor:publish', '--tag=permission-migrations', '--no-interaction'],
            ['php', 'artisan', 'vendor:publish', '--provider=NotificationChannels\\WebPush\\WebPushServiceProvider', '--tag=migrations', '--no-interaction'],
            ['php', 'artisan', 'vendor:publish', '--provider=Spatie\\LaravelSettings\\LaravelSettingsServiceProvider', '--tag=migrations', '--no-interaction'],
            ['php', 'artisan', 'vendor:publish', '--tag=activitylog-migrations', '--no-interaction'],
            ['php', 'artisan', 'vendor:publish', '--tag=medialibrary-migrations', '--no-interaction'],
            ['php', 'artisan', 'vendor:publish', '--tag=pennant-migrations', '--no-interaction'],
        ];

        foreach ($commands as $index => $command) {
            $operation = 'application.migrations.'.($index + 1);

            if ($journal->isCompleted($operation)) {
                continue;
            }

            $before = $this->applicationMigrationFiles();
            $this->context->processRunner->run($command, $this->context->projectRoot);
            $journal->recordPublish(implode(' ', $command), array_values(array_diff($this->applicationMigrationFiles(), $before)));
            $journal->complete($operation);
        }
    }

    /** @return list<string> */
    private function applicationMigrationFiles(): array
    {
        $files = [
            ...(glob($this->context->projectRoot.'/database/migrations/*.php') ?: []),
            ...(glob($this->context->projectRoot.'/database/settings/*.php') ?: []),
        ];
        sort($files);

        return array_map(
            fn (string $file): string => ltrim(str_replace($this->context->projectRoot, '', $file), '/'),
            $files,
        );
    }

    private function ensureLocalDatabase(): void
    {
        if ($this->context->plan->database !== 'sqlite') {
            return;
        }

        $databasePath = $this->context->projectRoot.'/database/database.sqlite';

        if (! is_file($databasePath) && touch($databasePath) === false) {
            throw new RuntimeException('Unable to create database/database.sqlite.');
        }
    }

    private function operation(InstallJournal $journal, string $name, Closure $operation): void
    {
        if ($journal->isCompleted($name)) {
            return;
        }

        $operation();
        $journal->complete($name);
    }

    /** @throws JsonException */
    private function selectAcceleratorBoostResources(): void
    {
        $path = $this->context->projectRoot.'/boost.json';
        $contents = is_file($path) ? file_get_contents($path) : null;
        $config = is_string($contents) ? json_decode($contents, true, flags: JSON_THROW_ON_ERROR) : [];

        if (! is_array($config)) {
            throw new RuntimeException('Unable to parse project boost.json.');
        }

        $packages = $config['packages'] ?? [];

        if (! is_array($packages)) {
            throw new RuntimeException('Project boost.json packages must be an array.');
        }

        $config['packages'] = array_values(array_unique([...Cast::stringList($packages), 'wireninja/accelerator']));
        ksort($config);
        $this->context->writeFile('boost.json', json_encode(
            $config,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ).PHP_EOL);
    }

    /** @throws JsonException */
    private function assertAcceleratorBoostResources(): void
    {
        $contents = file_get_contents($this->context->projectRoot.'/boost.json');
        $config = is_string($contents) ? json_decode($contents, true, flags: JSON_THROW_ON_ERROR) : null;
        $installedSkills = is_array($config) ? ($config['skills'] ?? null) : null;

        if (! is_array($installedSkills)) {
            throw new RuntimeException('Boost did not record installed skills.');
        }

        $skillFiles = glob($this->context->packageRoot.'/resources/boost/skills/*/SKILL.md') ?: [];
        $expectedSkills = array_map(static fn (string $skillFile): string => basename(dirname($skillFile)), $skillFiles);
        $missingSkills = array_values(array_diff($expectedSkills, Cast::stringList($installedSkills)));

        if ($missingSkills !== []) {
            throw new RuntimeException('Boost did not install Accelerator skills: '.implode(', ', $missingSkills));
        }
    }
}

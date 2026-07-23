<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Shield;

use BackedEnum;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

#[Signature('shield:safe-regenerate
    {--panel=admin : Filament panel ID to regenerate policies for}
    {--json : Output as JSON}
    {--compact : Compact JSON output}')]
#[Description('Safe regenerate shield policies and permissions for a panel')]
final class SafeRegenerateCommand extends Command
{
    public function handle(): int
    {
        $panel = (string) $this->option('panel');
        $isJson = (bool) $this->option('json');

        if (! $isJson) {
            $this->components->info(sprintf('Regenerating shield policies and permissions safely for panel [%s]...', $panel));
        }

        $exitCode = $this->call('shield:generate', [
            '--all' => true,
            '--option' => 'policies_and_permissions',
            '--ignore-existing-policies' => true,
            '--panel' => $panel,
        ]);
        $roleCount = $exitCode === 0 ? $this->syncRoleDefaults() : 0;

        if ($isJson) {
            $payload = [
                'status' => $exitCode === 0 ? 'OK' : 'ERROR',
                'panel' => $panel,
                'shield_exit_code' => $exitCode,
                'roles_synchronized' => $roleCount,
            ];

            $flags = ($this->option('compact') ? 0 : JSON_PRETTY_PRINT) | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
            $this->output->writeln(json_encode($payload, $flags));

            return $exitCode === 0 ? 0 : 1;
        }

        if ($exitCode !== 0) {
            $this->components->error(sprintf('Shield regeneration failed for panel [%s] (exit %d).', $panel, $exitCode));

            return 1;
        }

        $this->components->success(sprintf('Shield regeneration complete for panel [%s]; %d roles synchronized.', $panel, $roleCount));

        return 0;
    }

    private function syncRoleDefaults(): int
    {
        $roleEnum = config('accelerator.enums.role');

        if (! is_string($roleEnum) || ! enum_exists($roleEnum)) {
            return 0;
        }

        $permissions = Permission::query()->get()->keyBy('name');
        $superAdminRole = (string) config('filament-shield.super_admin.name', 'super_admin');
        $count = 0;

        foreach ($roleEnum::cases() as $case) {
            if (! $case instanceof BackedEnum || ! is_string($case->value)) {
                continue;
            }

            $role = Role::findOrCreate($case->value);

            if ($case->value === $superAdminRole || ! method_exists($case, 'defaultPermissions')) {
                $role->syncPermissions([]);
                $count++;

                continue;
            }

            $defaults = $case->defaultPermissions();
            $role->syncPermissions($defaults === []
                ? $permissions->values()
                : $permissions->only($defaults)->values());
            $count++;
        }

        return $count;
    }
}

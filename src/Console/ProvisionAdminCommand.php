<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console;

use BackedEnum;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\Permission\Models\Role;
use WireNinja\Accelerator\Contracts\AcceleratorUser;
use WireNinja\Accelerator\Support\UserModel;

#[Signature('accelerator:provision-admin
    {--name= : Super Admin display name}
    {--username= : Unique Super Admin username}
    {--email= : Unique Super Admin email}
    {--password-hash= : Bcrypt password hash}')]
#[Description('Provision the initial Accelerator Super Admin account')]
final class ProvisionAdminCommand extends Command
{
    public function handle(): int
    {
        $name = trim((string) $this->option('name'));
        $username = strtolower(trim((string) $this->option('username')));
        $email = strtolower(trim((string) $this->option('email')));
        $passwordHash = (string) $this->option('password-hash');

        if ($name === ''
            || preg_match('/^[a-z0-9._-]+$/', $username) !== 1
            || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || ! password_get_info($passwordHash)['algo']) {
            $this->components->error('Name, username, email, or password hash is invalid.');

            return self::FAILURE;
        }

        $userClass = UserModel::className();
        $existing = $userClass::query()
            ->where('email', $email)
            ->orWhere('username', $username)
            ->exists();

        if ($existing) {
            $this->components->error('The Super Admin email or username is already in use.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($email, $name, $passwordHash, $userClass, $username): void {
            $user = new $userClass;

            if (! $user instanceof AcceleratorUser) {
                throw new LogicException('The configured user model is incompatible with Accelerator.');
            }

            $user->forceFill([
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'email_verified_at' => now(),
                'password' => $passwordHash,
            ])->save();
            $user->syncRoles([$this->ensureApplicationRoles()]);
        });

        $this->components->success("Super Admin [{$email}] provisioned.");

        return self::SUCCESS;
    }

    private function ensureApplicationRoles(): string
    {
        $roleEnum = config('accelerator.enums.role');

        if (! is_string($roleEnum) || ! enum_exists($roleEnum)) {
            throw new LogicException('Accelerator requires a backed role enum.');
        }

        foreach ($roleEnum::cases() as $case) {
            if ($case instanceof BackedEnum && is_string($case->value)) {
                Role::findOrCreate($case->value);
            }
        }

        $superAdminRole = (string) config('filament-shield.super_admin.name', 'super_admin');
        Role::findOrCreate($superAdminRole);

        return $superAdminRole;
    }
}

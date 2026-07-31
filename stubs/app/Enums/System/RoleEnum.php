<?php

declare(strict_types=1);

namespace App\Enums\System;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use WireNinja\Accelerator\Concerns\RoleEnumPermissions;

enum RoleEnum: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    use RoleEnumPermissions;

    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Manager = 'manager';
    case User = 'user';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Administrator',
            self::Admin => 'Administrator',
            self::Manager => 'Manager',
            self::User => 'Pengguna Biasa',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Akses penuh melalui Gate bypass.',
            self::Admin => 'Menerima seluruh permission yang dihasilkan Shield.',
            self::Manager => 'Role kosong untuk dikonfigurasi sesuai domain proyek.',
            self::User => 'Role kosong untuk dikonfigurasi sesuai domain proyek.',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::SuperAdmin => 'lucide-shield-check',
            self::Admin => 'lucide-shield',
            self::Manager => 'lucide-briefcase',
            self::User => 'lucide-user',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SuperAdmin => 'danger',
            self::Admin => 'warning',
            self::Manager => 'info',
            self::User => 'gray',
        };
    }

    /** @return list<string>|null */
    public function defaultPermissions(): ?array
    {
        return match ($this) {
            self::Admin => null,
            self::SuperAdmin, self::Manager, self::User => [],
        };
    }
}

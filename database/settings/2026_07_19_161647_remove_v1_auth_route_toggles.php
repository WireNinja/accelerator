<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->deleteIfExists('system.password_reset_enabled');
        $this->migrator->deleteIfExists('system.email_verification_enabled');
    }
};

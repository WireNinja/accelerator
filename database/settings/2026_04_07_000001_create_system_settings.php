<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('system.brand_name', (string) config('app.name', 'My App'));
        $this->migrator->add('system.brand_logo', null);
        $this->migrator->add('system.brand_favicon', null);
        $this->migrator->add('system.support_enabled', true);
        $this->migrator->add('system.google_font', 'Poppins');
        $this->migrator->add('system.app_notice', null);
    }
};

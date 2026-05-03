<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('system.brand_name', (string) config('app.name', 'My App'));
        $this->migrator->add('system.brand_logo', null);
        $this->migrator->add('system.brand_favicon', null);
        $this->migrator->add('system.registration_enabled', true);
        $this->migrator->add('system.password_reset_enabled', true);
        $this->migrator->add('system.email_verification_enabled', false);
        $this->migrator->add('system.telegram_bot_token', null);
        $this->migrator->add('system.telegram_api_base_uri', null);
        $this->migrator->add('system.google_font', 'Poppins');
        $this->migrator->add('system.app_notice', null);
        $this->migrator->add('system.app_version', '1.0.0');
        $this->migrator->add('system.simple_page_image', 'login-side-image.jpg');
        $this->migrator->add('system.simple_page_title', 'Manage your workspace');
        $this->migrator->add('system.simple_page_subtitle', 'Internal System – online solutions for your workspace.');
    }
};

<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        foreach ([
            'system.telegram_bot_token',
            'system.telegram_api_base_uri',
            'system.app_version',
            'system.simple_page_image',
            'system.simple_page_layout',
        ] as $property) {
            if ($this->migrator->exists($property)) {
                $this->migrator->delete($property);
            }
        }
    }
};

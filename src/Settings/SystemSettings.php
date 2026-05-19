<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Settings;

use Spatie\LaravelSettings\Settings;
use WireNinja\Accelerator\Enums\GoogleFontEnum;
use WireNinja\Accelerator\Enums\LoginLayoutEnum;

final class SystemSettings extends Settings
{
    public string $brand_name;

    public ?string $brand_logo;

    public ?string $brand_favicon;

    public bool $registration_enabled;

    public bool $password_reset_enabled;

    public bool $email_verification_enabled;

    public bool $support_enabled;

    public ?string $telegram_bot_token;

    public ?string $telegram_api_base_uri;

    public GoogleFontEnum $google_font;

    public LoginLayoutEnum $simple_page_layout;

    public ?string $app_notice;

    public string $app_version;

    public ?string $simple_page_image;

    public static function group(): string
    {
        return 'system';
    }
}

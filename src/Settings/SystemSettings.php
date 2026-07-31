<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Settings;

use Spatie\LaravelSettings\Settings;
use WireNinja\Accelerator\Enums\GoogleFontEnum;

final class SystemSettings extends Settings
{
    public string $brand_name;

    public ?string $brand_logo;

    public ?string $brand_favicon;

    public bool $support_enabled;

    public GoogleFontEnum $google_font;

    public ?string $app_notice;

    public static function group(): string
    {
        return 'system';
    }
}

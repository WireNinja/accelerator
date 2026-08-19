<?php

namespace WireNinja\Accelerator\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use WireNinja\Accelerator\Support\Cast;

class DiceBearAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $query = http_build_query([
            'seed' => Filament::getNameForDefaultAvatar($record),
        ], '', '&', PHP_QUERY_RFC3986);

        return sprintf(
            '%s/%s/%s/svg?%s',
            rtrim(Cast::mustString(config('accelerator.dicebear.url')), '/'),
            trim(Cast::mustString(config('accelerator.dicebear.version')), '/'),
            trim(Cast::mustString(config('accelerator.dicebear.style')), '/'),
            $query,
        );
    }
}

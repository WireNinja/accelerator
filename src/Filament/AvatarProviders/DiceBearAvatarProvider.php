<?php

namespace WireNinja\Accelerator\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class DiceBearAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $query = http_build_query([
            'seed' => Filament::getNameForDefaultAvatar($record),
        ], '', '&', PHP_QUERY_RFC3986);

        return sprintf(
            '%s/%s/%s/svg?%s',
            rtrim((string) config('accelerator.dicebear.url'), '/'),
            trim((string) config('accelerator.dicebear.version'), '/'),
            trim((string) config('accelerator.dicebear.style'), '/'),
            $query,
        );
    }
}

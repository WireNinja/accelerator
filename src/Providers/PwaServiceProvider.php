<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Head\Enums\ImageType;
use Laravel\Head\Facades\Head;
use Laravel\Head\HeadBuilder;
use WireNinja\Accelerator\Support\Cast;

final class PwaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/pwa.php');

        Head::defaults(static fn (HeadBuilder $head): HeadBuilder => $head
            ->favicon('/favicon.ico', type: ImageType::Ico, sizes: '64x64')
            ->icon('/favicon.svg', type: ImageType::Svg, sizes: 'any')
            ->pwa(
                name: Cast::mustString(config('app.name')),
                manifest: '/build/manifest.webmanifest',
                themeColor: Cast::mustString(config('accelerator.pwa.theme_color', '#ffffff')),
                appleTouchIcon: '/apple-touch-icon-180x180.png',
                appleWebAppStatusBarStyle: 'default',
            ));
    }
}

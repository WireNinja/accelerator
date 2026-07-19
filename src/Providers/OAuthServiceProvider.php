<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Providers;

use Illuminate\Support\ServiceProvider;

final class OAuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/oauth.php');
    }
}

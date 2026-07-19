<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Providers;

use Illuminate\Support\ServiceProvider;
use WireNinja\Accelerator\Console\NotifyOverdueTicketsCommand;

final class TicketingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([NotifyOverdueTicketsCommand::class]);
        }
    }
}

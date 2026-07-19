<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use WireNinja\Accelerator\Console\NotifyOverdueTicketsCommand;
use WireNinja\Accelerator\Model\Ticket;
use WireNinja\Accelerator\Model\TicketBoard;
use WireNinja\Accelerator\Model\TicketComment;
use WireNinja\Accelerator\Model\TicketRelation;
use WireNinja\Accelerator\Policies\TicketBoardPolicy;
use WireNinja\Accelerator\Policies\TicketCommentPolicy;
use WireNinja\Accelerator\Policies\TicketPolicy;
use WireNinja\Accelerator\Policies\TicketRelationPolicy;

final class TicketingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(TicketBoard::class, TicketBoardPolicy::class);
        Gate::policy(TicketComment::class, TicketCommentPolicy::class);
        Gate::policy(TicketRelation::class, TicketRelationPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([NotifyOverdueTicketsCommand::class]);
        }
    }
}

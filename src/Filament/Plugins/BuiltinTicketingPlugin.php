<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\Plugins;

use Filament\Contracts\Plugin as FilamentPlugin;
use Filament\Panel;
use Illuminate\Support\Facades\Gate;
use WireNinja\Accelerator\Filament\Pages\TicketingPage;
use WireNinja\Accelerator\Filament\Resources\Support\TicketBoards\TicketBoardResource;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\TicketResource;
use WireNinja\Accelerator\Model\Ticket;
use WireNinja\Accelerator\Model\TicketBoard;
use WireNinja\Accelerator\Model\TicketComment;
use WireNinja\Accelerator\Model\TicketRelation;
use WireNinja\Accelerator\Policies\TicketBoardPolicy;
use WireNinja\Accelerator\Policies\TicketCommentPolicy;
use WireNinja\Accelerator\Policies\TicketPolicy;
use WireNinja\Accelerator\Policies\TicketRelationPolicy;

class BuiltinTicketingPlugin implements FilamentPlugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'builtin-ticketing';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            TicketResource::class,
            TicketBoardResource::class,
        ]);

        $panel->pages([
            TicketingPage::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(TicketBoard::class, TicketBoardPolicy::class);
        Gate::policy(TicketComment::class, TicketCommentPolicy::class);
        Gate::policy(TicketRelation::class, TicketRelationPolicy::class);
    }
}

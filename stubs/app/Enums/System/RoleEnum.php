<?php

namespace App\Enums\System;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use WireNinja\Accelerator\Concerns\BetterEnum;
use WireNinja\Accelerator\Concerns\RoleEnumPermissions;

enum RoleEnum: string implements HasDescription, HasIcon, HasLabel
{
    use BetterEnum;
    use RoleEnumPermissions;

    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Manager = 'manager';
    case User = 'user';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Administrator',
            self::Admin => 'Administrator',
            self::Manager => 'Manager',
            self::User => 'Pengguna Biasa',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Akses penuh tanpa batas ke seluruh sistem.',
            self::Admin => 'Kelola operasional harian dan pengguna.',
            self::Manager => 'Akses terbatas untuk manajemen data spesifik.',
            self::User => 'Akses dasar untuk fitur publik dan profil.',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::SuperAdmin => 'lucide-shield-check',
            self::Admin => 'lucide-shield',
            self::Manager => 'lucide-briefcase',
            self::User => 'lucide-user',
        };
    }

    /**
     * Default permissions for this role, assigned during seeding.
     * Return an empty array to grant ALL generated permissions.
     * SuperAdmin bypasses Gate entirely \u2014 never needs explicit permissions.
     *
     * Permission naming follows ticket policy conventions:
     * Action:ModelName (e.g. ViewAny:Ticket, Create:TicketBoard)
     *
     * @return string[]
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            // SuperAdmin bypasses Gate \u2014 permissions are irrelevant.
            self::SuperAdmin => [],

            // Admin: full access to everything (empty = all generated permissions).
            self::Admin => [],

            // Manager: can manage boards, tickets (all views, assign, status),
            // comments, relations, and custom field definitions.
            // Cannot hard-delete any resource in bulk.
            self::Manager => [
                // TicketBoard
                'ViewAny:TicketBoard',
                'View:TicketBoard',
                'Create:TicketBoard',
                'Update:TicketBoard',
                'Delete:TicketBoard',

                // Ticket \u2014 full view/create/update/assign/status, no bulk delete
                'ViewAny:Ticket',
                'ViewAll:Ticket',
                'ViewOwn:Ticket',
                'ViewAssigned:Ticket',
                'View:Ticket',
                'Create:Ticket',
                'Update:Ticket',
                'UpdateOwn:Ticket',
                'UpdateAssigned:Ticket',
                'Delete:Ticket',
                'DeleteOwn:Ticket',
                'Assign:Ticket',
                'ChangeStatus:Ticket',

                // TicketComment \u2014 piggy-backs on View:Ticket & Update:Ticket in policy
                'ViewAny:Ticket',
                'View:Ticket',
                'Update:Ticket',

                // TicketRelation \u2014 piggy-backs on View:Ticket & Update:Ticket

                // TicketCustomFieldDefinition \u2014 read-only for managers
                'ViewAny:TicketCustomFieldDefinition',
                'View:TicketCustomFieldDefinition',
            ],

            // User (Pengguna Biasa): can submit tickets, view/update own & assigned tickets,
            // and comment on tickets they have access to.
            self::User => [
                // TicketBoard \u2014 read-only
                'ViewAny:TicketBoard',
                'View:TicketBoard',

                // Ticket \u2014 own/assigned scope only; watcher & is_public access
                // is handled by the policy and scope without needing View:Ticket.
                'ViewAny:Ticket',
                'ViewOwn:Ticket',
                'ViewAssigned:Ticket',
                'Create:Ticket',
                'UpdateOwn:Ticket',
                'DeleteOwn:Ticket',

                // TicketCustomFieldDefinition \u2014 read-only (needed for form rendering)
                'ViewAny:TicketCustomFieldDefinition',
                'View:TicketCustomFieldDefinition',
            ],
        };
    }
}

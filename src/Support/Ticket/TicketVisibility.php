<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support\Ticket;

use WireNinja\Accelerator\Model\AcceleratedUser;

class TicketVisibility
{
    public static function canViewAll(AcceleratedUser $user): bool
    {
        return $user->can('ViewAll:Ticket') || $user->can('ViewAny:Ticket');
    }

    public static function canManageRouting(AcceleratedUser $user): bool
    {
        return $user->can('Assign:Ticket')
            || $user->can('ChangeStatus:Ticket')
            || $user->can('Update:Ticket');
    }

    public static function describeAccess(AcceleratedUser $user): string
    {
        if (static::canViewAll($user)) {
            return 'Anda dapat melihat seluruh tiket lintas board.';
        }

        if ($user->can('ViewOwn:Ticket') && $user->can('ViewAssigned:Ticket')) {
            return 'Anda melihat tiket milik sendiri, tiket yang ditugaskan ke Anda, tiket yang di-CC ke Anda, dan tiket publik.';
        }

        if ($user->can('ViewAssigned:Ticket')) {
            return 'Anda melihat tiket yang ditugaskan ke Anda, tiket yang di-CC ke Anda, dan tiket publik.';
        }

        return 'Anda melihat tiket milik sendiri, tiket yang di-CC ke Anda, dan tiket publik.';
    }
}

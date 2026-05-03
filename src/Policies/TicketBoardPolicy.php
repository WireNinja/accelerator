<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use WireNinja\Accelerator\Model\AcceleratedUser;
use WireNinja\Accelerator\Model\TicketBoard;

class TicketBoardPolicy
{
    use HandlesAuthorization;

    public function viewAny(AcceleratedUser $user): bool
    {
        return $user->can('ViewAny:TicketBoard');
    }

    public function view(AcceleratedUser $user, TicketBoard $ticketBoard): bool
    {
        return $user->can('View:TicketBoard');
    }

    public function create(AcceleratedUser $user): bool
    {
        return $user->can('Create:TicketBoard');
    }

    public function update(AcceleratedUser $user, TicketBoard $ticketBoard): bool
    {
        return $user->can('Update:TicketBoard');
    }

    public function delete(AcceleratedUser $user, TicketBoard $ticketBoard): bool
    {
        // Prevent deleting the default board — it would break the ticketing flow.
        if ($ticketBoard->is_default) {
            return false;
        }

        return $user->can('Delete:TicketBoard');
    }

    public function deleteAny(AcceleratedUser $user): bool
    {
        return $user->can('DeleteAny:TicketBoard');
    }
}

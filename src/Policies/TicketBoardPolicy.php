<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use WireNinja\Accelerator\Contracts\AcceleratorUser;
use WireNinja\Accelerator\Model\TicketBoard;

class TicketBoardPolicy
{
    use HandlesAuthorization;

    public function viewAny(AcceleratorUser $user): bool
    {
        return $user->can('ViewAny:TicketBoard');
    }

    public function view(AcceleratorUser $user, TicketBoard $ticketBoard): bool
    {
        return $user->can('View:TicketBoard');
    }

    public function create(AcceleratorUser $user): bool
    {
        return $user->can('Create:TicketBoard');
    }

    public function update(AcceleratorUser $user, TicketBoard $ticketBoard): bool
    {
        return $user->can('Update:TicketBoard');
    }

    public function delete(AcceleratorUser $user, TicketBoard $ticketBoard): bool
    {
        // Prevent deleting the default board — it would break the ticketing flow.
        if ($ticketBoard->is_default) {
            return false;
        }

        return $user->can('Delete:TicketBoard');
    }

    public function deleteAny(AcceleratorUser $user): bool
    {
        return $user->can('DeleteAny:TicketBoard');
    }
}

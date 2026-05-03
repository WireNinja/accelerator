<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Policies;

use WireNinja\Accelerator\Model\AcceleratedUser;
use WireNinja\Accelerator\Model\TicketRelation;

class TicketRelationPolicy
{
    /**
     * Any user that can see tickets can list relations on those tickets.
     */
    public function viewAny(AcceleratedUser $user): bool
    {
        return $user->can('ViewAny:Ticket')
            || $user->can('ViewOwn:Ticket')
            || $user->can('ViewAssigned:Ticket');
    }

    /**
     * A relation is visible to anyone who can view the parent ticket.
     */
    public function view(AcceleratedUser $user, TicketRelation $relation): bool
    {
        return $user->can('View:Ticket')
            || $user->can('ViewOwn:Ticket')
            || $user->can('ViewAssigned:Ticket');
    }

    /**
     * Creating a relation between tickets is an agent action —
     * requires Update:Ticket permission.
     */
    public function create(AcceleratedUser $user): bool
    {
        return $user->can('Update:Ticket');
    }

    /**
     * Relations are immutable once created — their meaning is defined by type.
     * To change a relation, delete and recreate with the new type.
     * Only agents with Update:Ticket may edit (e.g. admin corrections).
     */
    public function update(AcceleratedUser $user, TicketRelation $relation): bool
    {
        return $user->can('Update:Ticket');
    }

    /**
     * Deleting a relation requires Update:Ticket — same gate as creating one.
     */
    public function delete(AcceleratedUser $user, TicketRelation $relation): bool
    {
        return $user->can('Update:Ticket');
    }
}

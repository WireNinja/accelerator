<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Policies;

use WireNinja\Accelerator\Contracts\AcceleratorUser;
use WireNinja\Accelerator\Model\TicketRelation;

class TicketRelationPolicy
{
    /**
     * Any user that can see tickets can list relations on those tickets.
     */
    public function viewAny(AcceleratorUser $user): bool
    {
        return $user->can('ViewAny:Ticket')
            || $user->can('ViewOwn:Ticket')
            || $user->can('ViewAssigned:Ticket');
    }

    /**
     * A relation is visible to anyone who can view the parent ticket.
     */
    public function view(AcceleratorUser $user, TicketRelation $relation): bool
    {
        return $user->can('View:Ticket')
            || $user->can('ViewOwn:Ticket')
            || $user->can('ViewAssigned:Ticket');
    }

    /**
     * Creating a relation between tickets is an agent action —
     * requires Update:Ticket permission.
     */
    public function create(AcceleratorUser $user): bool
    {
        return $user->can('Update:Ticket');
    }

    /**
     * Relations are immutable once created — their meaning is defined by type.
     * To change a relation, delete and recreate with the new type.
     * Only agents with Update:Ticket may edit (e.g. admin corrections).
     */
    public function update(AcceleratorUser $user, TicketRelation $relation): bool
    {
        return $user->can('Update:Ticket');
    }

    /**
     * Deleting a relation requires Update:Ticket — same gate as creating one.
     */
    public function delete(AcceleratorUser $user, TicketRelation $relation): bool
    {
        return $user->can('Update:Ticket');
    }
}

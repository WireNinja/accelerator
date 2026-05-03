<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Policies;

use WireNinja\Accelerator\Model\AcceleratedUser;
use WireNinja\Accelerator\Model\TicketComment;

class TicketCommentPolicy
{
    /**
     * Any user that can see tickets can list comments on those tickets.
     */
    public function viewAny(AcceleratedUser $user): bool
    {
        return $user->can('ViewAny:Ticket')
            || $user->can('ViewOwn:Ticket')
            || $user->can('ViewAssigned:Ticket');
    }

    /**
     * A user can view a comment if they can view the parent ticket.
     * Internal comments are an additional gate at the UI/query layer.
     */
    public function view(AcceleratedUser $user, TicketComment $comment): bool
    {
        return $user->can('View:Ticket')
            || $user->can('ViewOwn:Ticket')
            || $user->can('ViewAssigned:Ticket');
    }

    /**
     * Commenting is allowed on any ticket the user can view,
     * unless the ticket is archived.
     */
    public function create(AcceleratedUser $user): bool
    {
        return $user->can('View:Ticket')
            || $user->can('ViewOwn:Ticket')
            || $user->can('ViewAssigned:Ticket');
    }

    /**
     * A user may edit their own comment (for a reasonable window — enforced
     * at the UI layer). Agents with Update:Ticket may edit any comment.
     * Archived comments are read-only.
     */
    public function update(AcceleratedUser $user, TicketComment $comment): bool
    {
        if ($comment->archived_at !== null) {
            return false;
        }

        return $comment->user_id === $user->id
            || $user->can('Update:Ticket');
    }

    /**
     * Only the author or an agent with Update:Ticket may delete a comment.
     * Archived comments cannot be deleted.
     */
    public function delete(AcceleratedUser $user, TicketComment $comment): bool
    {
        if ($comment->archived_at !== null) {
            return false;
        }

        return $comment->user_id === $user->id
            || $user->can('Update:Ticket');
    }
}

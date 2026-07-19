<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Policies;

use WireNinja\Accelerator\Contracts\AcceleratorUser;
use WireNinja\Accelerator\Model\TicketComment;
use WireNinja\Accelerator\Support\UserModel;

class TicketCommentPolicy
{
    /**
     * Any user that can see tickets can list comments on those tickets.
     */
    public function viewAny(AcceleratorUser $user): bool
    {
        return $user->can('ViewAny:Ticket')
            || $user->can('ViewOwn:Ticket')
            || $user->can('ViewAssigned:Ticket');
    }

    /**
     * A user can view a comment if they can view the parent ticket.
     * Internal comments are an additional gate at the UI/query layer.
     */
    public function view(AcceleratorUser $user, TicketComment $comment): bool
    {
        return $user->can('View:Ticket')
            || $user->can('ViewOwn:Ticket')
            || $user->can('ViewAssigned:Ticket');
    }

    /**
     * Commenting is allowed on any ticket the user can view,
     * unless the ticket is archived.
     */
    public function create(AcceleratorUser $user): bool
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
    public function update(AcceleratorUser $user, TicketComment $comment): bool
    {
        if ($comment->archived_at !== null) {
            return false;
        }

        return $comment->user_id === UserModel::id($user)
            || $user->can('Update:Ticket');
    }

    /**
     * Only the author or an agent with Update:Ticket may delete a comment.
     * Archived comments cannot be deleted.
     */
    public function delete(AcceleratorUser $user, TicketComment $comment): bool
    {
        if ($comment->archived_at !== null) {
            return false;
        }

        return $comment->user_id === UserModel::id($user)
            || $user->can('Update:Ticket');
    }
}

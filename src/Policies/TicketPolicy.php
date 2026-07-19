<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use WireNinja\Accelerator\Contracts\AcceleratorUser;
use WireNinja\Accelerator\Enums\Ticket\TicketStatusEnum;
use WireNinja\Accelerator\Model\Ticket;
use WireNinja\Accelerator\Support\UserModel;

class TicketPolicy
{
    use HandlesAuthorization;

    // ─── List / Existence ────────────────────────────────────────────────────

    public function viewAny(AcceleratorUser $user): bool
    {
        return $user->can('ViewAny:Ticket')
            || $user->can('ViewAll:Ticket')
            || $user->can('ViewOwn:Ticket')
            || $user->can('ViewAssigned:Ticket');
    }

    // ─── Single Record ────────────────────────────────────────────────────────

    public function view(AcceleratorUser $user, Ticket $ticket): bool
    {
        if ($user->can('View:Ticket') || $user->can('ViewAll:Ticket')) {
            return true;
        }

        if ($user->can('ViewOwn:Ticket') && $this->isReporter($user, $ticket)) {
            return true;
        }

        if ($user->can('ViewAssigned:Ticket') && $this->isAssignee($user, $ticket)) {
            return true;
        }

        if ($this->isWatcher($user, $ticket)) {
            return true;
        }

        return $ticket->is_public;
    }

    // ─── Mutations ────────────────────────────────────────────────────────────

    public function create(AcceleratorUser $user): bool
    {
        return $user->can('Create:Ticket');
    }

    public function update(AcceleratorUser $user, Ticket $ticket): bool
    {
        if ($user->can('Update:Ticket')) {
            return true;
        }

        // Reporter can only update their own tickets while unrouted:
        // once assigned or moved past 'open', it belongs to the agent.
        if ($user->can('UpdateOwn:Ticket') && $this->isReporter($user, $ticket)) {
            return $this->isUnrouted($ticket);
        }

        // Assignee can update tickets assigned to them as long as it is active.
        return $user->can('UpdateAssigned:Ticket')
            && $this->isAssignee($user, $ticket)
            && ! $this->isClosed($ticket);
    }

    public function delete(AcceleratorUser $user, Ticket $ticket): bool
    {
        if ($user->can('Delete:Ticket')) {
            return true;
        }

        // Reporter can delete their own ticket only while it is unrouted.
        return $user->can('DeleteOwn:Ticket')
            && $this->isReporter($user, $ticket)
            && $this->isUnrouted($ticket);
    }

    public function deleteAny(AcceleratorUser $user): bool
    {
        return $user->can('DeleteAny:Ticket');
    }

    // ─── Scoped Visibility Helpers ────────────────────────────────────────────

    public function viewAll(AcceleratorUser $user, Ticket $ticket): bool
    {
        return $user->can('ViewAll:Ticket');
    }

    public function viewOwn(AcceleratorUser $user, Ticket $ticket): bool
    {
        return $user->can('ViewOwn:Ticket') && $this->isReporter($user, $ticket);
    }

    public function viewAssigned(AcceleratorUser $user, Ticket $ticket): bool
    {
        return $user->can('ViewAssigned:Ticket') && $this->isAssignee($user, $ticket);
    }

    // ─── Scoped Mutation Helpers ──────────────────────────────────────────────

    public function updateOwn(AcceleratorUser $user, Ticket $ticket): bool
    {
        return $user->can('UpdateOwn:Ticket')
            && $this->isReporter($user, $ticket)
            && $this->isUnrouted($ticket);
    }

    public function updateAssigned(AcceleratorUser $user, Ticket $ticket): bool
    {
        return $user->can('UpdateAssigned:Ticket')
            && $this->isAssignee($user, $ticket)
            && ! $this->isClosed($ticket);
    }

    public function deleteOwn(AcceleratorUser $user, Ticket $ticket): bool
    {
        return $user->can('DeleteOwn:Ticket')
            && $this->isReporter($user, $ticket)
            && $this->isUnrouted($ticket);
    }

    // ─── Workflow Actions ─────────────────────────────────────────────────────

    /**
     * Assign a ticket to someone. Requires Assign:Ticket or Update:Ticket.
     */
    public function assign(AcceleratorUser $user, Ticket $ticket): bool
    {
        return $user->can('Assign:Ticket') || $user->can('Update:Ticket');
    }

    /**
     * Change ticket status. Assignees can transition their own active tickets.
     */
    public function changeStatus(AcceleratorUser $user, Ticket $ticket): bool
    {
        if ($user->can('ChangeStatus:Ticket') || $user->can('Update:Ticket')) {
            return true;
        }

        return $user->can('UpdateAssigned:Ticket')
            && $this->isAssignee($user, $ticket)
            && ! $this->isClosed($ticket);
    }

    /**
     * Archive a ticket. Only agents with Update:Ticket or Assign:Ticket may archive.
     * Reporters cannot archive their tickets once routed.
     */
    public function archive(AcceleratorUser $user, Ticket $ticket): bool
    {
        if ($user->can('Update:Ticket')) {
            return true;
        }

        // Reporters may archive their own draft tickets only while unrouted.
        return $user->can('UpdateOwn:Ticket')
            && $this->isReporter($user, $ticket)
            && $this->isUnrouted($ticket);
    }

    /**
     * Restore an archived ticket. Requires Update:Ticket.
     */
    public function restore(AcceleratorUser $user, Ticket $ticket): bool
    {
        return $user->can('Update:Ticket');
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function isReporter(AcceleratorUser $user, Ticket $ticket): bool
    {
        return $ticket->reporter_id === UserModel::id($user);
    }

    private function isAssignee(AcceleratorUser $user, Ticket $ticket): bool
    {
        return $ticket->assignee_id !== null
            && $ticket->assignee_id === UserModel::id($user);
    }

    private function isWatcher(AcceleratorUser $user, Ticket $ticket): bool
    {
        return $ticket->watchers()->where('user_id', UserModel::id($user))->exists();
    }

    /**
     * A ticket is "unrouted" (still a pure draft) when it has no assignee
     * AND its status is still 'open'. Once an agent takes it or its status
     * advances, the reporter loses write access.
     */
    private function isUnrouted(Ticket $ticket): bool
    {
        return $ticket->assignee_id === null
            && $ticket->status === TicketStatusEnum::Open;
    }

    /**
     * A ticket is "closed" when it is resolved, closed, or archived.
     * In this state nobody can make meaningful edits anymore.
     */
    private function isClosed(Ticket $ticket): bool
    {
        return in_array($ticket->status, [
            TicketStatusEnum::Resolved,
            TicketStatusEnum::Closed,
        ], strict: true) || $ticket->archived_at !== null;
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RepairTicket;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Follows the Shield-generated policies. Verifying, rejecting, starting and
 * completing a repair map to "update"; approving it has its own permission so
 * the people who carry out repairs do not sign off their cost.
 */
class RepairTicketPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RepairTicket');
    }

    public function view(AuthUser $authUser, RepairTicket $repairTicket): bool
    {
        return $authUser->can('View:RepairTicket');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RepairTicket');
    }

    public function update(AuthUser $authUser, RepairTicket $repairTicket): bool
    {
        return $authUser->can('Update:RepairTicket');
    }

    public function approve(AuthUser $authUser, RepairTicket $repairTicket): bool
    {
        return $authUser->can('Approve:RepairTicket');
    }

    public function delete(AuthUser $authUser, RepairTicket $repairTicket): bool
    {
        return $authUser->can('Delete:RepairTicket');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RepairTicket');
    }

    public function restore(AuthUser $authUser, RepairTicket $repairTicket): bool
    {
        return $authUser->can('Restore:RepairTicket');
    }

    public function forceDelete(AuthUser $authUser, RepairTicket $repairTicket): bool
    {
        return $authUser->can('ForceDelete:RepairTicket');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RepairTicket');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RepairTicket');
    }

    public function replicate(AuthUser $authUser, RepairTicket $repairTicket): bool
    {
        return $authUser->can('Replicate:RepairTicket');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RepairTicket');
    }
}

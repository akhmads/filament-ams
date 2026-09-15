<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ItemRequest;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Follows the Shield-generated policies. Cancelling and issuing map to
 * "update"; approving and rejecting have their own permission so the people who
 * record requests do not sign them off.
 */
class ItemRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ItemRequest');
    }

    public function view(AuthUser $authUser, ItemRequest $itemRequest): bool
    {
        return $authUser->can('View:ItemRequest');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ItemRequest');
    }

    public function update(AuthUser $authUser, ItemRequest $itemRequest): bool
    {
        return $authUser->can('Update:ItemRequest');
    }

    public function approve(AuthUser $authUser, ItemRequest $itemRequest): bool
    {
        return $authUser->can('Approve:ItemRequest');
    }

    public function delete(AuthUser $authUser, ItemRequest $itemRequest): bool
    {
        return $authUser->can('Delete:ItemRequest');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ItemRequest');
    }

    public function restore(AuthUser $authUser, ItemRequest $itemRequest): bool
    {
        return $authUser->can('Restore:ItemRequest');
    }

    public function forceDelete(AuthUser $authUser, ItemRequest $itemRequest): bool
    {
        return $authUser->can('ForceDelete:ItemRequest');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ItemRequest');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ItemRequest');
    }

    public function replicate(AuthUser $authUser, ItemRequest $itemRequest): bool
    {
        return $authUser->can('Replicate:ItemRequest');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ItemRequest');
    }
}

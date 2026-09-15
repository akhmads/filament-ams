<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AssetDisposal;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Follows the Shield-generated policies. Cancelling and completing map to
 * "update"; approving and rejecting have their own permission so the people who
 * propose a disposal do not sign it off. Only a proposal still waiting for a
 * decision can be deleted.
 */
class AssetDisposalPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AssetDisposal');
    }

    public function view(AuthUser $authUser, AssetDisposal $assetDisposal): bool
    {
        return $authUser->can('View:AssetDisposal');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AssetDisposal');
    }

    public function update(AuthUser $authUser, AssetDisposal $assetDisposal): bool
    {
        return $authUser->can('Update:AssetDisposal');
    }

    public function approve(AuthUser $authUser, AssetDisposal $assetDisposal): bool
    {
        return $authUser->can('Approve:AssetDisposal');
    }

    public function delete(AuthUser $authUser, AssetDisposal $assetDisposal): bool
    {
        return $authUser->can('Delete:AssetDisposal') && $assetDisposal->isEditable();
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AssetDisposal');
    }

    public function restore(AuthUser $authUser, AssetDisposal $assetDisposal): bool
    {
        return $authUser->can('Restore:AssetDisposal');
    }

    public function forceDelete(AuthUser $authUser, AssetDisposal $assetDisposal): bool
    {
        return $authUser->can('ForceDelete:AssetDisposal') && $assetDisposal->isEditable();
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AssetDisposal');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AssetDisposal');
    }

    public function replicate(AuthUser $authUser, AssetDisposal $assetDisposal): bool
    {
        return $authUser->can('Replicate:AssetDisposal');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AssetDisposal');
    }
}

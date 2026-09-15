<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AssetAudit;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Follows the Shield-generated policies. Scanning, finishing the count and
 * cancelling map to "update"; choosing corrections and closing the audit have
 * their own permission, because they change where assets are recorded and
 * whether they are lost. A completed audit cannot be deleted.
 */
class AssetAuditPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AssetAudit');
    }

    public function view(AuthUser $authUser, AssetAudit $assetAudit): bool
    {
        return $authUser->can('View:AssetAudit');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AssetAudit');
    }

    public function update(AuthUser $authUser, AssetAudit $assetAudit): bool
    {
        return $authUser->can('Update:AssetAudit');
    }

    public function close(AuthUser $authUser, AssetAudit $assetAudit): bool
    {
        return $authUser->can('Close:AssetAudit');
    }

    public function delete(AuthUser $authUser, AssetAudit $assetAudit): bool
    {
        return $authUser->can('Delete:AssetAudit') && $assetAudit->status->isOpen();
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AssetAudit');
    }

    public function restore(AuthUser $authUser, AssetAudit $assetAudit): bool
    {
        return $authUser->can('Restore:AssetAudit');
    }

    public function forceDelete(AuthUser $authUser, AssetAudit $assetAudit): bool
    {
        return $authUser->can('ForceDelete:AssetAudit') && $assetAudit->status->isOpen();
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AssetAudit');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AssetAudit');
    }

    public function replicate(AuthUser $authUser, AssetAudit $assetAudit): bool
    {
        return $authUser->can('Replicate:AssetAudit');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AssetAudit');
    }
}

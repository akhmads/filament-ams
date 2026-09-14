<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AssetAssignment;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AssetAssignmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AssetAssignment');
    }

    public function view(AuthUser $authUser, AssetAssignment $assetAssignment): bool
    {
        return $authUser->can('View:AssetAssignment');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AssetAssignment');
    }

    public function update(AuthUser $authUser, AssetAssignment $assetAssignment): bool
    {
        return $authUser->can('Update:AssetAssignment');
    }

    public function delete(AuthUser $authUser, AssetAssignment $assetAssignment): bool
    {
        return $authUser->can('Delete:AssetAssignment');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AssetAssignment');
    }

    public function restore(AuthUser $authUser, AssetAssignment $assetAssignment): bool
    {
        return $authUser->can('Restore:AssetAssignment');
    }

    public function forceDelete(AuthUser $authUser, AssetAssignment $assetAssignment): bool
    {
        return $authUser->can('ForceDelete:AssetAssignment');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AssetAssignment');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AssetAssignment');
    }

    public function replicate(AuthUser $authUser, AssetAssignment $assetAssignment): bool
    {
        return $authUser->can('Replicate:AssetAssignment');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AssetAssignment');
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AssetModel;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AssetModelPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AssetModel');
    }

    public function view(AuthUser $authUser, AssetModel $assetModel): bool
    {
        return $authUser->can('View:AssetModel');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AssetModel');
    }

    public function update(AuthUser $authUser, AssetModel $assetModel): bool
    {
        return $authUser->can('Update:AssetModel');
    }

    public function delete(AuthUser $authUser, AssetModel $assetModel): bool
    {
        return $authUser->can('Delete:AssetModel');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AssetModel');
    }

    public function restore(AuthUser $authUser, AssetModel $assetModel): bool
    {
        return $authUser->can('Restore:AssetModel');
    }

    public function forceDelete(AuthUser $authUser, AssetModel $assetModel): bool
    {
        return $authUser->can('ForceDelete:AssetModel');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AssetModel');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AssetModel');
    }

    public function replicate(AuthUser $authUser, AssetModel $assetModel): bool
    {
        return $authUser->can('Replicate:AssetModel');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AssetModel');
    }
}

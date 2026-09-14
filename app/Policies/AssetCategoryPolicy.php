<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AssetCategory;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AssetCategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AssetCategory');
    }

    public function view(AuthUser $authUser, AssetCategory $assetCategory): bool
    {
        return $authUser->can('View:AssetCategory');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AssetCategory');
    }

    public function update(AuthUser $authUser, AssetCategory $assetCategory): bool
    {
        return $authUser->can('Update:AssetCategory');
    }

    public function delete(AuthUser $authUser, AssetCategory $assetCategory): bool
    {
        return $authUser->can('Delete:AssetCategory');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AssetCategory');
    }

    public function restore(AuthUser $authUser, AssetCategory $assetCategory): bool
    {
        return $authUser->can('Restore:AssetCategory');
    }

    public function forceDelete(AuthUser $authUser, AssetCategory $assetCategory): bool
    {
        return $authUser->can('ForceDelete:AssetCategory');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AssetCategory');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AssetCategory');
    }

    public function replicate(AuthUser $authUser, AssetCategory $assetCategory): bool
    {
        return $authUser->can('Replicate:AssetCategory');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AssetCategory');
    }
}

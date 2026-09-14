<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DepreciationPeriod;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Follows the Shield-generated policies. Calculating a month maps to "create" and
 * recalculating or posting a draft maps to "update", so the roles that may change
 * depreciation are managed from the Roles screen like everything else.
 */
class DepreciationPeriodPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DepreciationPeriod');
    }

    public function view(AuthUser $authUser, DepreciationPeriod $depreciationPeriod): bool
    {
        return $authUser->can('View:DepreciationPeriod');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DepreciationPeriod');
    }

    public function update(AuthUser $authUser, DepreciationPeriod $depreciationPeriod): bool
    {
        return $authUser->can('Update:DepreciationPeriod');
    }

    public function delete(AuthUser $authUser, DepreciationPeriod $depreciationPeriod): bool
    {
        return $authUser->can('Delete:DepreciationPeriod');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DepreciationPeriod');
    }

    public function restore(AuthUser $authUser, DepreciationPeriod $depreciationPeriod): bool
    {
        return $authUser->can('Restore:DepreciationPeriod');
    }

    public function forceDelete(AuthUser $authUser, DepreciationPeriod $depreciationPeriod): bool
    {
        return $authUser->can('ForceDelete:DepreciationPeriod');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DepreciationPeriod');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DepreciationPeriod');
    }

    public function replicate(AuthUser $authUser, DepreciationPeriod $depreciationPeriod): bool
    {
        return $authUser->can('Replicate:DepreciationPeriod');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DepreciationPeriod');
    }
}

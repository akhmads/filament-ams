<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StockDocument;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Follows the Shield-generated policies. Posting a receipt, issue or transfer
 * maps to "update"; posting an adjustment or stock count has its own permission,
 * because writing stock up or down without goods changing hands is where losses
 * get hidden. A posted document is part of the stock card and cannot be deleted.
 */
class StockDocumentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:StockDocument');
    }

    public function view(AuthUser $authUser, StockDocument $stockDocument): bool
    {
        return $authUser->can('View:StockDocument');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:StockDocument');
    }

    public function update(AuthUser $authUser, StockDocument $stockDocument): bool
    {
        return $authUser->can('Update:StockDocument');
    }

    public function post(AuthUser $authUser, StockDocument $stockDocument): bool
    {
        return $stockDocument->type->correctsStock()
            ? $authUser->can('Post:StockAdjustment')
            : $authUser->can('Update:StockDocument');
    }

    public function delete(AuthUser $authUser, StockDocument $stockDocument): bool
    {
        return $authUser->can('Delete:StockDocument') && $stockDocument->isEditable();
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:StockDocument');
    }

    public function restore(AuthUser $authUser, StockDocument $stockDocument): bool
    {
        return $authUser->can('Restore:StockDocument');
    }

    public function forceDelete(AuthUser $authUser, StockDocument $stockDocument): bool
    {
        return $authUser->can('ForceDelete:StockDocument') && $stockDocument->isEditable();
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:StockDocument');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:StockDocument');
    }

    public function replicate(AuthUser $authUser, StockDocument $stockDocument): bool
    {
        return $authUser->can('Replicate:StockDocument');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:StockDocument');
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LabelTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LabelTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LabelTemplate');
    }

    public function view(AuthUser $authUser, LabelTemplate $labelTemplate): bool
    {
        return $authUser->can('View:LabelTemplate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LabelTemplate');
    }

    public function update(AuthUser $authUser, LabelTemplate $labelTemplate): bool
    {
        return $authUser->can('Update:LabelTemplate');
    }

    public function delete(AuthUser $authUser, LabelTemplate $labelTemplate): bool
    {
        return $authUser->can('Delete:LabelTemplate');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LabelTemplate');
    }

    public function restore(AuthUser $authUser, LabelTemplate $labelTemplate): bool
    {
        return $authUser->can('Restore:LabelTemplate');
    }

    public function forceDelete(AuthUser $authUser, LabelTemplate $labelTemplate): bool
    {
        return $authUser->can('ForceDelete:LabelTemplate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LabelTemplate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LabelTemplate');
    }

    public function replicate(AuthUser $authUser, LabelTemplate $labelTemplate): bool
    {
        return $authUser->can('Replicate:LabelTemplate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LabelTemplate');
    }
}

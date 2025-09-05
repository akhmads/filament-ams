<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Brand;
use Illuminate\Auth\Access\HandlesAuthorization;

class BrandPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_brands::brand');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Brand $brand): bool
    {
        return $user->can('view_brands::brand');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_brands::brand');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Brand $brand): bool
    {
        return $user->can('update_brands::brand');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Brand $brand): bool
    {
        return $user->can('delete_brands::brand');
    }
}

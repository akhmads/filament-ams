<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Post;
use Illuminate\Auth\Access\HandlesAuthorization;

class PostPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Post');
    }

    public function view(AuthUser $authUser, Post $post): bool
    {
        return $authUser->can('View:Post');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Post');
    }

    public function update(AuthUser $authUser, Post $post): bool
    {
        return $authUser->can('Update:Post');
    }

    public function delete(AuthUser $authUser, Post $post): bool
    {
        return $authUser->can('Delete:Post');
    }

}
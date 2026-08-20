<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('iam.users.read') || $user->hasPermission('users.read');
    }

    public function view(User $user, User $model): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('iam.users.read') || $user->hasPermission('users.read');
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('iam.users.create') || $user->hasPermission('users.create');
    }

    public function update(User $user, User $model): bool
    {
        if ($model->isSuperAdmin() && !$user->isSuperAdmin()) {
            return false;
        }
        return $user->isSuperAdmin() || $user->hasPermission('iam.users.update') || $user->hasPermission('users.update');
    }

    public function delete(User $user, User $model): bool
    {
        if ($model->username === 'admin' || $model->isSuperAdmin()) return false;
        return $user->isSuperAdmin() || $user->hasPermission('iam.users.delete') || $user->hasPermission('users.delete');
    }
}

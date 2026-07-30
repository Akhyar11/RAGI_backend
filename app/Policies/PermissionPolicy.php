<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('superadmin') || $user->hasPermission('iam.permissions.read') || $user->hasPermission('roles.read');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('superadmin') || $user->hasPermission('iam.permissions.manage');
    }

    public function update(User $user, Permission $permission): bool
    {
        return $user->hasRole('admin') || $user->hasRole('superadmin') || $user->hasPermission('iam.permissions.manage');
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $user->hasRole('admin') || $user->hasRole('superadmin') || $user->hasPermission('iam.permissions.manage');
    }
}

<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.read') || $user->hasPermission('users.read');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        if ($role->slug === 'super-admin') return false; // Hard-coded protection
        return $user->hasPermission('roles.update');
    }

    public function delete(User $user, Role $role): bool
    {
        if ($role->slug === 'super-admin' || $role->slug === 'admin-iam') return false; // Bawaan sistem
        return $user->hasPermission('roles.delete');
    }
}

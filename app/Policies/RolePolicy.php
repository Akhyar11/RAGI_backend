<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('iam.roles.read') || $user->hasPermission('roles.read');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('iam.roles.read') || $user->hasPermission('roles.read');
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('iam.roles.create') || $user->hasPermission('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        $superAdminRole = \App\Models\SystemSetting::where('key', 'superadmin_role')->value('value') ?? 'superadmin';
        if ($role->slug === 'admin' || $role->slug === 'superadmin' || $role->slug === $superAdminRole) return false;
        return $user->isSuperAdmin() || $user->hasPermission('iam.roles.update') || $user->hasPermission('roles.update');
    }

    public function delete(User $user, Role $role): bool
    {
        $superAdminRole = \App\Models\SystemSetting::where('key', 'superadmin_role')->value('value') ?? 'superadmin';
        if ($role->slug === 'admin' || $role->slug === 'superadmin' || $role->slug === $superAdminRole) return false;
        return $user->isSuperAdmin() || $user->hasPermission('iam.roles.delete') || $user->hasPermission('roles.delete');
    }
}

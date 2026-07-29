<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.read');
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPermission('users.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    public function update(User $user, User $model): bool
    {
        // Tidak boleh edit super-admin jika bukan super-admin
        if ($model->hasRole('super-admin') && !$user->hasRole('super-admin')) {
            return false;
        }
        return $user->hasPermission('users.update');
    }

    public function delete(User $user, User $model): bool
    {
        if ($model->hasRole('super-admin')) return false; // Super admin tak boleh dihapus
        return $user->hasPermission('users.delete');
    }
}

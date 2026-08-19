<?php

namespace App\Policies\Sinapra;

use App\Models\User;
use App\Models\Ruangan;

class RuanganPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sinapra.ruangan.read');
    }

    public function view(User $user, Ruangan $ruangan): bool
    {
        return $user->hasPermission('sinapra.ruangan.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sinapra.ruangan.create');
    }

    public function update(User $user, Ruangan $ruangan): bool
    {
        return $user->hasPermission('sinapra.ruangan.update');
    }

    public function delete(User $user, Ruangan $ruangan): bool
    {
        return $user->hasPermission('sinapra.ruangan.delete');
    }
}

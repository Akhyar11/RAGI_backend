<?php

namespace App\Policies\Sinapra;

use App\Models\User;
use App\Models\Aset;

class AsetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sinapra.aset.read');
    }

    public function view(User $user, Aset $aset): bool
    {
        return $user->hasPermission('sinapra.aset.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sinapra.aset.create');
    }

    public function update(User $user, Aset $aset): bool
    {
        return $user->hasPermission('sinapra.aset.update');
    }

    public function delete(User $user, Aset $aset): bool
    {
        return $user->hasPermission('sinapra.aset.delete');
    }
}

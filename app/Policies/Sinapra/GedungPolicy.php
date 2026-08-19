<?php

namespace App\Policies\Sinapra;

use App\Models\User;
use App\Models\Gedung;

class GedungPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sinapra.gedung.read');
    }

    public function view(User $user, Gedung $gedung): bool
    {
        return $user->hasPermission('sinapra.gedung.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sinapra.gedung.create');
    }

    public function update(User $user, Gedung $gedung): bool
    {
        return $user->hasPermission('sinapra.gedung.update');
    }

    public function delete(User $user, Gedung $gedung): bool
    {
        return $user->hasPermission('sinapra.gedung.delete');
    }
}

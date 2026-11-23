<?php

namespace App\Policies\Sinapra;

use App\Models\BebasTanggungan;
use App\Models\User;

class BebasTanggunganPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sinapra.bebas_tanggungan.read');
    }

    public function view(User $user, BebasTanggungan $bebasTanggungan): bool
    {
        if ($user->hasPermission('sinapra.bebas_tanggungan.read') || $user->hasPermission('sinapra.bebas_tanggungan.approve')) {
            return true;
        }

        return $bebasTanggungan->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sinapra.bebas_tanggungan.create');
    }

    public function approve(User $user, BebasTanggungan $bebasTanggungan): bool
    {
        return $user->hasPermission('sinapra.bebas_tanggungan.approve');
    }
}

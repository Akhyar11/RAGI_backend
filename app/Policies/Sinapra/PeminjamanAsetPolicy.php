<?php

namespace App\Policies\Sinapra;

use App\Models\User;
use App\Models\PeminjamanAset;

class PeminjamanAsetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sinapra.peminjaman_aset.read');
    }

    public function view(User $user, PeminjamanAset $peminjamanAset): bool
    {
        return $user->hasPermission('sinapra.peminjaman_aset.read') || $user->id === $peminjamanAset->user_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sinapra.peminjaman_aset.create');
    }

    public function approve(User $user, PeminjamanAset $peminjamanAset): bool
    {
        return $user->hasPermission('sinapra.peminjaman_aset.approve');
    }

    public function delete(User $user, PeminjamanAset $peminjamanAset): bool
    {
        return $user->hasPermission('sinapra.peminjaman_aset.delete') || $user->id === $peminjamanAset->user_id;
    }
}

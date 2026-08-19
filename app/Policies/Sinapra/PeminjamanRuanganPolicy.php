<?php

namespace App\Policies\Sinapra;

use App\Models\User;
use App\Models\PeminjamanRuangan;

class PeminjamanRuanganPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sinapra.peminjaman_ruangan.read');
    }

    public function view(User $user, PeminjamanRuangan $peminjamanRuangan): bool
    {
        return $user->hasPermission('sinapra.peminjaman_ruangan.read') || $user->id === $peminjamanRuangan->user_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sinapra.peminjaman_ruangan.create');
    }

    public function approve(User $user, PeminjamanRuangan $peminjamanRuangan): bool
    {
        return $user->hasPermission('sinapra.peminjaman_ruangan.approve');
    }

    public function delete(User $user, PeminjamanRuangan $peminjamanRuangan): bool
    {
        return $user->hasPermission('sinapra.peminjaman_ruangan.delete') || $user->id === $peminjamanRuangan->user_id;
    }
}

<?php

namespace App\Policies\Sinapra;

use App\Models\User;
use App\Models\KategoriAset;

class KategoriAsetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sinapra.kategori_aset.read');
    }

    public function view(User $user, KategoriAset $kategoriAset): bool
    {
        return $user->hasPermission('sinapra.kategori_aset.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sinapra.kategori_aset.create');
    }

    public function update(User $user, KategoriAset $kategoriAset): bool
    {
        return $user->hasPermission('sinapra.kategori_aset.update');
    }

    public function delete(User $user, KategoriAset $kategoriAset): bool
    {
        return $user->hasPermission('sinapra.kategori_aset.delete');
    }
}

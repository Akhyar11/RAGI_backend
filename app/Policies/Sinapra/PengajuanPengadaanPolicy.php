<?php

namespace App\Policies\Sinapra;

use App\Models\User;
use App\Models\PengajuanPengadaan;

class PengajuanPengadaanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sinapra.pengadaan.read');
    }

    public function view(User $user, PengajuanPengadaan $pengajuan): bool
    {
        return $user->hasPermission('sinapra.pengadaan.read') || $user->id === $pengajuan->diajukan_oleh;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sinapra.pengadaan.create');
    }

    public function update(User $user, PengajuanPengadaan $pengajuan): bool
    {
        return $user->hasPermission('sinapra.pengadaan.approve') || ($user->id === $pengajuan->diajukan_oleh && $pengajuan->status === 'draft');
    }

    public function delete(User $user, PengajuanPengadaan $pengajuan): bool
    {
        return $user->hasPermission('sinapra.pengadaan.delete') || ($user->id === $pengajuan->diajukan_oleh && $pengajuan->status === 'draft');
    }
}

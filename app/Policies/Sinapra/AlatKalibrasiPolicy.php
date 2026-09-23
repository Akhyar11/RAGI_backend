<?php

namespace App\Policies\Sinapra;

use App\Models\AlatKalibrasi;
use App\Models\LaboranRuangan;
use App\Models\User;

class AlatKalibrasiPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sinapra.kalibrasi.read');
    }

    public function view(User $user, AlatKalibrasi $kalibrasi): bool
    {
        if (! $user->hasPermission('sinapra.kalibrasi.read')) {
            return false;
        }

        if ($user->hasRole('superadmin') || $user->hasRole('admin') || $user->hasRole('admin_sarpras')) {
            return true;
        }

        if ($user->hasRole('admin_laboratorium')) {
            return LaboranRuangan::where('user_id', $user->id)
                ->where('ruangan_id', $kalibrasi->aset?->ruangan_id)
                ->exists();
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sinapra.kalibrasi.manage');
    }

    public function update(User $user, AlatKalibrasi $kalibrasi): bool
    {
        if (! $user->hasPermission('sinapra.kalibrasi.manage')) {
            return false;
        }

        if ($user->hasRole('superadmin') || $user->hasRole('admin') || $user->hasRole('admin_sarpras')) {
            return true;
        }

        if ($user->hasRole('admin_laboratorium')) {
            return LaboranRuangan::where('user_id', $user->id)
                ->where('ruangan_id', $kalibrasi->aset?->ruangan_id)
                ->exists();
        }

        return true;
    }

    public function delete(User $user, AlatKalibrasi $kalibrasi): bool
    {
        return $this->update($user, $kalibrasi);
    }
}

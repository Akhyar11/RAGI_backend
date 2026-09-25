<?php

namespace App\Policies\Sinapra;

use App\Models\LabBhp;
use App\Models\LaboranRuangan;
use App\Models\User;

class LabBhpPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sinapra.bhp.read');
    }

    public function view(User $user, LabBhp $bhp): bool
    {
        if (! $user->hasPermission('sinapra.bhp.read')) {
            return false;
        }

        if ($user->hasRole('superadmin') || $user->hasRole('admin') || $user->hasRole('admin_sarpras')) {
            return true;
        }

        if ($user->hasRole('admin_laboratorium')) {
            return LaboranRuangan::where('user_id', $user->id)
                ->where('ruangan_id', $bhp->ruangan_id)
                ->exists();
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sinapra.bhp.manage');
    }

    public function update(User $user, LabBhp $bhp): bool
    {
        if (! $user->hasPermission('sinapra.bhp.manage')) {
            return false;
        }

        if ($user->hasRole('superadmin') || $user->hasRole('admin') || $user->hasRole('admin_sarpras')) {
            return true;
        }

        if ($user->hasRole('admin_laboratorium')) {
            return LaboranRuangan::where('user_id', $user->id)
                ->where('ruangan_id', $bhp->ruangan_id)
                ->exists();
        }

        return true;
    }

    public function delete(User $user, LabBhp $bhp): bool
    {
        return $this->update($user, $bhp);
    }

    public function manageStock(User $user, LabBhp $bhp): bool
    {
        return $this->update($user, $bhp);
    }
}

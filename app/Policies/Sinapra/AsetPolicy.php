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
        if (!$user->hasPermission('sinapra.aset.read')) {
            return false;
        }

        if ($user->isSinapraLaboranRestricted()) {
            return $this->isInLaboranScope($user, $aset);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sinapra.aset.create');
    }

    public function update(User $user, Aset $aset): bool
    {
        if (!$user->hasPermission('sinapra.aset.update')) {
            return false;
        }

        if ($user->isSinapraLaboranRestricted()) {
            return $this->isInLaboranScope($user, $aset);
        }

        return true;
    }

    public function delete(User $user, Aset $aset): bool
    {
        if (!$user->hasPermission('sinapra.aset.delete')) {
            return false;
        }

        if ($user->isSinapraLaboranRestricted()) {
            return $this->isInLaboranScope($user, $aset);
        }

        return true;
    }

    private function isInLaboranScope(User $user, Aset $aset): bool
    {
        $accessibleProdiIds = $user->getSinapraProdiIds();
        $accessibleRuanganIds = $user->getSinapraAccessibleRuanganIds();

        if ($aset->program_studi_id && $accessibleProdiIds->contains($aset->program_studi_id)) {
            return true;
        }

        if ($aset->ruangan_id && $accessibleRuanganIds->contains($aset->ruangan_id)) {
            return true;
        }

        if ($aset->ruangan && $aset->ruangan->program_studi_id && $accessibleProdiIds->contains($aset->ruangan->program_studi_id)) {
            return true;
        }

        return false;
    }
}

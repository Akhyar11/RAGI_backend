<?php

namespace App\Policies\Sinapra;

use App\Models\User;
use App\Models\Ruangan;

class RuanganPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sinapra.ruangan.read');
    }

    public function view(User $user, Ruangan $ruangan): bool
    {
        if (!$user->hasPermission('sinapra.ruangan.read')) {
            return false;
        }

        if ($user->isSinapraLaboranRestricted()) {
            return $this->isInLaboranScope($user, $ruangan);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sinapra.ruangan.create');
    }

    public function update(User $user, Ruangan $ruangan): bool
    {
        if (!$user->hasPermission('sinapra.ruangan.update')) {
            return false;
        }

        if ($user->isSinapraLaboranRestricted()) {
            return $this->isInLaboranScope($user, $ruangan);
        }

        return true;
    }

    public function delete(User $user, Ruangan $ruangan): bool
    {
        if (!$user->hasPermission('sinapra.ruangan.delete')) {
            return false;
        }

        if ($user->isSinapraLaboranRestricted()) {
            return $this->isInLaboranScope($user, $ruangan);
        }

        return true;
    }

    private function isInLaboranScope(User $user, Ruangan $ruangan): bool
    {
        $accessibleProdiIds = $user->getSinapraProdiIds();
        $accessibleRuanganIds = $user->getSinapraAccessibleRuanganIds();

        if ($ruangan->program_studi_id && $accessibleProdiIds->contains($ruangan->program_studi_id)) {
            return true;
        }

        if ($accessibleRuanganIds->contains($ruangan->id)) {
            return true;
        }

        return false;
    }

    public function manageLaboran(User $user, Ruangan $ruangan): bool
    {
        return $user->hasPermission('sinapra.laboran.manage');
    }
}

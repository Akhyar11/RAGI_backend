<?php

namespace App\Policies\Sinapra;

use App\Models\User;
use App\Models\MaintenanceLog;

class MaintenanceLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sinapra.maintenance.read');
    }

    public function view(User $user, MaintenanceLog $maintenanceLog): bool
    {
        return $user->hasPermission('sinapra.maintenance.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sinapra.maintenance.create');
    }

    public function update(User $user, MaintenanceLog $maintenanceLog): bool
    {
        return $user->hasPermission('sinapra.maintenance.update');
    }

    public function delete(User $user, MaintenanceLog $maintenanceLog): bool
    {
        return $user->hasPermission('sinapra.maintenance.delete');
    }
}

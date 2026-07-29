<?php

namespace App\Observers;

use App\Models\Role;
use App\Services\AuditLogService;

class RoleObserver
{
    /**
     * Handle the Role "created" event.
     */
    public function created(Role $role): void
    {
        AuditLogService::record(
            module: 'IAM',
            action: 'create',
            tableName: 'roles',
            recordId: $role->id,
            newValues: $role->toArray()
        );
    }

    /**
     * Handle the Role "updated" event.
     */
    public function updated(Role $role): void
    {
        if ($role->wasChanged()) {
            AuditLogService::record(
                module: 'IAM',
                action: 'update',
                tableName: 'roles',
                recordId: $role->id,
                oldValues: $role->getOriginal(),
                newValues: $role->getChanges()
            );
        }
    }

    /**
     * Handle the Role "deleted" event.
     */
    public function deleted(Role $role): void
    {
        AuditLogService::record(
            module: 'IAM',
            action: 'delete',
            tableName: 'roles',
            recordId: $role->id,
            oldValues: $role->toArray()
        );
    }
}

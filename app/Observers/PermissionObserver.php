<?php

namespace App\Observers;

use App\Models\Permission;
use App\Services\AuditLogService;

class PermissionObserver
{
    /**
     * Handle the Permission "created" event.
     */
    public function created(Permission $permission): void
    {
        AuditLogService::record(
            module: 'IAM',
            action: 'create',
            tableName: 'permissions',
            recordId: $permission->id,
            newValues: $permission->toArray()
        );
    }

    /**
     * Handle the Permission "updated" event.
     */
    public function updated(Permission $permission): void
    {
        if ($permission->wasChanged()) {
            AuditLogService::record(
                module: 'IAM',
                action: 'update',
                tableName: 'permissions',
                recordId: $permission->id,
                oldValues: $permission->getOriginal(),
                newValues: $permission->getChanges()
            );
        }
    }

    /**
     * Handle the Permission "deleted" event.
     */
    public function deleted(Permission $permission): void
    {
        AuditLogService::record(
            module: 'IAM',
            action: 'delete',
            tableName: 'permissions',
            recordId: $permission->id,
            oldValues: $permission->toArray()
        );
    }
}

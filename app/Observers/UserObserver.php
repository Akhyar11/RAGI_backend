<?php

namespace App\Observers;

use App\Models\User;
use App\Services\AuditLogService;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        AuditLogService::record(
            module: 'IAM',
            action: 'create',
            tableName: 'users',
            recordId: $user->id,
            newValues: $user->toArray()
        );
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // Hindari logging jika hanya `last_login_at` yang berubah (itu dicover oleh action: 'login')
        if ($user->wasChanged() && !$user->wasChanged('last_login_at')) {
            AuditLogService::record(
                module: 'IAM',
                action: 'update',
                tableName: 'users',
                recordId: $user->id,
                oldValues: $user->getOriginal(),
                newValues: $user->getChanges()
            );
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        AuditLogService::record(
            module: 'IAM',
            action: 'delete',
            tableName: 'users',
            recordId: $user->id,
            oldValues: $user->toArray()
        );
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        AuditLogService::record(
            module: 'IAM',
            action: 'restore',
            tableName: 'users',
            recordId: $user->id
        );
    }
}

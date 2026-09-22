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
        try {
            $newValues = collect($user->toArray())->except(['password', 'remember_token'])->toArray();
            AuditLogService::record(
                module: 'IAM',
                action: 'create',
                tableName: 'core_users',
                recordId: $user->id,
                oldValues: null,
                newValues: $newValues,
                request: request()
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // Hindari logging jika hanya `last_login_at` yang berubah (itu dicover oleh action: 'login')
        if ($user->wasChanged() && !$user->wasChanged('last_login_at')) {
            try {
                $changes = collect($user->getChanges())->except(['password', 'remember_token'])->toArray();
                $oldValues = collect($user->getOriginal())->only(array_keys($changes))->except(['password', 'remember_token'])->toArray();
                AuditLogService::record(
                    module: 'IAM',
                    action: 'update',
                    tableName: 'core_users',
                    recordId: $user->id,
                    oldValues: $oldValues,
                    newValues: $changes,
                    request: request()
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        try {
            $oldValues = collect($user->toArray())->except(['password', 'remember_token'])->toArray();
            AuditLogService::record(
                module: 'IAM',
                action: 'delete',
                tableName: 'core_users',
                recordId: $user->id,
                oldValues: $oldValues,
                newValues: null,
                request: request()
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        try {
            AuditLogService::record(
                module: 'IAM',
                action: 'restore',
                tableName: 'core_users',
                recordId: $user->id,
                request: request()
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

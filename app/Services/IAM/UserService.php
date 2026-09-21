<?php

namespace App\Services\IAM;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    /**
     * Create user with optional roles inside transaction
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $userData = [
                'username' => $data['username'],
                'name' => $data['name'] ?? $data['username'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'is_verified' => $data['is_verified'] ?? false,
            ];

            $user = User::create($userData);

            if (!empty($data['roles']) && is_array($data['roles'])) {
                $user->roles()->sync($data['roles']);

                try {
                    AuditLogService::record(
                        module: 'IAM',
                        action: 'create',
                        tableName: 'core_user_roles',
                        recordId: $user->id,
                        oldValues: null,
                        newValues: ['roles' => $data['roles']],
                        request: request()
                    );
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return $user->load('roles');
        });
    }

    /**
     * Update user and sync roles inside transaction
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $fields = ['username', 'name', 'email', 'phone', 'is_active', 'is_verified'];
            $updateData = [];

            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            if (!empty($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            if (!empty($updateData)) {
                $user->update($updateData);
            }

            if (isset($data['roles']) && is_array($data['roles'])) {
                $oldRoleIds = $user->roles()->pluck('core_roles.id')->toArray();
                $user->roles()->sync($data['roles']);

                try {
                    AuditLogService::record(
                        module: 'IAM',
                        action: 'update',
                        tableName: 'core_user_roles',
                        recordId: $user->id,
                        oldValues: ['roles' => $oldRoleIds],
                        newValues: ['roles' => $data['roles']],
                        request: request()
                    );
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return $user->load('roles');
        });
    }

    /**
     * Impersonate target user by admin
     */
    public function impersonate(User $targetUser, User $admin): array
    {
        if ($targetUser->id === $admin->id) {
            throw ValidationException::withMessages([
                'user_id' => ['Anda tidak dapat merasuki akun Anda sendiri.'],
            ]);
        }

        if (!$targetUser->is_active) {
            throw ValidationException::withMessages([
                'user_id' => ['Pengguna ini sedang non-aktif dan tidak dapat dirasuki.'],
            ]);
        }

        $tokenResult = $targetUser->createToken('impersonate-' . $admin->username);
        $token = $tokenResult->plainTextToken ?? $tokenResult->accessToken;

        try {
            AuditLogService::record(
                module: 'IAM',
                action: 'login',
                tableName: 'core_users',
                recordId: $targetUser->id,
                oldValues: [
                    'impersonated_by_id' => $admin->id,
                    'impersonated_by_username' => $admin->username,
                ],
                newValues: [
                    'target_user_id' => $targetUser->id,
                    'target_username' => $targetUser->username,
                ],
                request: request()
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return [
            'token' => $token,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $targetUser->load(['roles', 'roles.permissions']),
            'impersonated_by' => [
                'id' => $admin->id,
                'username' => $admin->username,
                'name' => $admin->name,
            ],
        ];
    }
}


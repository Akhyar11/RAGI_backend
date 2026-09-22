<?php

namespace App\Services\IAM;

use App\Models\User;
use App\Models\ImpersonationSession;
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

            $this->syncPegawaiForUser($user);

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

            $this->syncPegawaiForUser($user);

            return $user->load('roles');
        });
    }

    /**
     * Auto sync/create Pegawai record in SIMPEG if User has dosen or tendik role
     */
    public function syncPegawaiForUser(User $user): void
    {
        try {
            $user->loadMissing('roles');
            $roles = $user->roles;

            $hasDosen = $roles->contains(function ($role) {
                $slug = strtolower($role->slug ?? '');
                $name = strtolower($role->name ?? '');
                return str_contains($slug, 'dosen') || str_contains($name, 'dosen');
            });

            $hasTendik = $roles->contains(function ($role) {
                $slug = strtolower($role->slug ?? '');
                $name = strtolower($role->name ?? '');
                return str_contains($slug, 'tendik') || str_contains($name, 'tendik') || str_contains($slug, 'staf') || str_contains($name, 'staf');
            });

            if ($hasDosen || $hasTendik) {
                $pegawai = \App\Models\Simpeg\Pegawai::where('user_id', $user->id)->first();
                $jenisPegawai = $hasDosen ? 'dosen' : 'tendik';

                if (!$pegawai) {
                    $pegawai = \App\Models\Simpeg\Pegawai::create([
                        'user_id' => $user->id,
                        'nama_lengkap' => $user->name ?: ($user->username ?: 'Pegawai SSO'),
                        'jenis_pegawai' => $jenisPegawai,
                        'status_kepegawaian' => 'non_pns',
                        'status' => 'aktif',
                        'is_active' => true,
                        'nip' => (!empty($user->username) && is_numeric($user->username)) ? $user->username : null,
                    ]);
                } else {
                    $pegawai->update([
                        'nama_lengkap' => $user->name ?: $pegawai->nama_lengkap,
                        'jenis_pegawai' => $pegawai->jenis_pegawai ?: $jenisPegawai,
                        'is_active' => true,
                    ]);
                }

                if ($jenisPegawai === 'dosen' && class_exists(\App\Models\Siakad\Dosen::class)) {
                    \App\Models\Siakad\Dosen::firstOrCreate(
                        ['pegawai_id' => $pegawai->id],
                        [
                            'user_id' => $user->id,
                            'nama' => $pegawai->nama_lengkap,
                            'nidn' => $pegawai->nip,
                            'is_active' => true,
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Impersonate target user by admin.
     *
     * Mencatat satu baris sesi per-token per-device di
     * core_impersonation_sessions agar satu admin boleh merasuki
     * akun A di device 1 dan akun B di device 2 secara bersamaan.
     */
    public function impersonate(
        User $targetUser,
        User $admin,
        ?string $adminTokenId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
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

        return DB::transaction(function () use ($targetUser, $admin, $adminTokenId, $ipAddress, $userAgent) {
            $tokenName = 'impersonate-' . $admin->username . '-' . $targetUser->id . '-' . time();
            $tokenResult = $targetUser->createToken($tokenName);
            $token = $tokenResult->plainTextToken ?? $tokenResult->accessToken ?? null;

            // Passport v12: id token ada di accessTokenId / token relation.
            // Fallback ke lookup baris oauth terbaru agar sesi tetap tercatat
            // walau relasi lazy-load gagal (mis. koneksi berbeda saat testing).
            $impersonationTokenId = null;
            try {
                $impersonationTokenId = $tokenResult->token?->id ?? $tokenResult->accessTokenId ?? null;
            } catch (\Throwable $e) {
                $impersonationTokenId = null;
            }
            if (!$impersonationTokenId) {
                try {
                    $impersonationTokenId = DB::table('oauth_access_tokens')
                        ->where('user_id', $targetUser->id)
                        ->where('name', $tokenName)
                        ->orderByDesc('created_at')
                        ->value('id');
                } catch (\Throwable $e) {
                    $impersonationTokenId = null;
                }
            }

            $session = null;
            if ($impersonationTokenId) {
                $session = ImpersonationSession::create([
                    'admin_id' => $admin->id,
                    'target_user_id' => $targetUser->id,
                    'impersonation_token_id' => $impersonationTokenId,
                    'admin_token_id' => $adminTokenId,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'started_at' => now(),
                    'ended_at' => null,
                ]);
            }

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
                'impersonation_session_id' => $session?->id,
            ];
        });
    }

    /**
     * Cari sesi impersonasi aktif berdasarkan id token impersonasi.
     * Isolasi per-token: token device 1 tidak akan menemukan sesi device 2.
     */
    public function findActiveSession(string $impersonationTokenId): ?ImpersonationSession
    {
        return ImpersonationSession::with(['admin.roles', 'target.roles'])
            ->where('impersonation_token_id', $impersonationTokenId)
            ->whereNull('ended_at')
            ->first();
    }

    /**
     * Status sesi impersonasi untuk satu token pemanggil.
     * Kembalikan payload `data` siap kirim sebagai response JSON.
     */
    public function getImpersonationStatus(?string $tokenId, ?string $tokenName): array
    {
        if ($tokenId) {
            $session = $this->findActiveSession($tokenId);

            if ($session && $session->admin) {
                $admin = $session->admin;

                return [
                    'is_impersonating' => true,
                    'impersonation_session_id' => $session->id,
                    'started_at' => $session->started_at?->toISOString(),
                    'impersonated_by' => [
                        'id' => $admin->id,
                        'username' => $admin->username,
                        'name' => $admin->name,
                    ],
                ];
            }
        }

        // Fallback token lawas (dibuat sebelum tabel sesi ada):
        // nama token diawali 'impersonate-{username_admin}'.
        if ($tokenName && str_starts_with($tokenName, 'impersonate-')) {
            $parts = explode('-', $tokenName);
            $adminUsername = $parts[1] ?? null;
            $admin = $adminUsername ? User::where('username', $adminUsername)->first() : null;

            return [
                'is_impersonating' => true,
                'impersonation_session_id' => null,
                'is_legacy' => true,
                'impersonated_by' => $admin ? [
                    'id' => $admin->id,
                    'username' => $admin->username,
                    'name' => $admin->name,
                ] : null,
            ];
        }

        return ['is_impersonating' => false];
    }

    /**
     * Akhiri sesi impersonasi milik satu token pemanggil.
     *
     * Hanya menutup baris milik token tersebut — sesi device lain milik
     * admin yang sama tidak ikut tertutup. Menerbitkan token admin baru
     * agar tab baru tanpa simpanan adminToken tetap bisa kembali.
     *
     * @return array|null Payload `data` (admin + token baru) atau null untuk token lawas.
     *
     * @throws \Illuminate\Validation\ValidationException Jika token bukan sesi impersonasi.
     */
    public function leaveImpersonation(string $tokenId, ?string $tokenName, int $targetUserId): ?array
    {
        return DB::transaction(function () use ($tokenId, $tokenName, $targetUserId) {
            $session = $this->findActiveSession($tokenId);
            $isLegacyToken = $tokenName && str_starts_with($tokenName, 'impersonate-');

            if (!$session && !$isLegacyToken) {
                throw ValidationException::withMessages([
                    'impersonation' => ['Tidak sedang dalam mode impersonasi.'],
                ]);
            }

            try {
                AuditLogService::record(
                    module: 'IAM',
                    action: 'logout',
                    tableName: 'core_users',
                    recordId: $targetUserId,
                    oldValues: ['impersonation_ended' => true],
                    newValues: null,
                    request: request()
                );
            } catch (\Throwable $e) {
                report($e);
            }

            if ($session) {
                $session->update(['ended_at' => now()]);
            }

            DB::table('oauth_access_tokens')->where('id', $tokenId)->delete();

            if ($session && $session->admin_id) {
                $admin = User::with(['roles', 'roles.permissions'])->find($session->admin_id);

                if ($admin) {
                    $adminTokenResult = $admin->createToken('auth_token-restored-' . time());
                    $adminToken = $adminTokenResult->plainTextToken ?? $adminTokenResult->accessToken;

                    return [
                        'admin' => $admin,
                        'access_token' => $adminToken,
                        'token' => $adminToken,
                        'token_type' => 'Bearer',
                    ];
                }
            }

            return null;
        });
    }
}


<?php

namespace App\Services\Spmb;

use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\Spmb\GelombangPenerimaan;
use App\Models\Spmb\PendaftaranBuktiPembayaran;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Storage\FileStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SpmbPendaftaranManualService
{
    public function __construct(
        private readonly SpmbReferralService $referralService,
        private readonly FileStorageService $fileStorage,
    ) {}

    public function create(array $data, int $adminId, Request $request): array
    {
        $proof = $data['bukti_transfer'] ?? null;
        $referrerId = $data['referrer_user_id'] ?? null;
        $email = $data['email'];
        $thirdPartyIds = array_values($data['pihak_ketiga_user_ids'] ?? []);
        unset($data['email'], $data['bukti_transfer'], $data['referrer_user_id'], $data['pihak_ketiga_user_ids']);

        $proofPath = $proof
            ? $this->fileStorage->store($proof, 'spmb/bukti_transfer_manual', null, true)
            : null;

        try {
            return DB::transaction(function () use ($data, $email, $adminId, $request, $referrerId, $thirdPartyIds, $proof, $proofPath) {
                $gelombang = GelombangPenerimaan::query()->lockForUpdate()->findOrFail($data['gelombang_id']);
                if ($gelombang->status !== 'aktif') {
                    throw ValidationException::withMessages([
                        'gelombang_id' => 'Pendaftaran manual hanya dapat menggunakan gelombang berstatus aktif.',
                    ]);
                }

                $calonRole = Role::where('slug', 'calon_mhs')->first();
                if (! $calonRole) {
                    throw ValidationException::withMessages([
                        'email' => 'Role calon mahasiswa belum tersedia. Jalankan seeder role sebelum membuat akun.',
                    ]);
                }

                $temporaryPassword = Str::random(20);
                $user = User::create([
                    'username' => $this->generateUsername($email),
                    'name' => $data['nama_lengkap'],
                    'email' => $email,
                    'password' => Hash::make($temporaryPassword),
                    'phone' => $data['no_hp'],
                    'is_active' => true,
                    'is_verified' => true,
                ]);
                $user->email_verified_at = now();
                $user->save();
                $user->roles()->syncWithoutDetaching([$calonRole->id]);

                $pendaftaran = PendaftaranCalonMhs::create(array_merge($data, [
                    'user_id' => $user->id,
                    'no_pendaftaran' => $this->generateRegistrationNumber(),
                    'kewarganegaraan' => $data['kewarganegaraan'] ?? 'WNI',
                    'status' => PendaftaranCalonMhs::STATUS_SUBMITTED,
                    'status_pembayaran' => PendaftaranCalonMhs::STATUS_PEMBAYARAN_BELUM,
                    'sumber_pendaftaran' => 'manual',
                    'diinput_oleh' => $adminId,
                ]));

                if ($referrerId) {
                    $referrer = User::query()
                        ->whereKey($referrerId)
                        ->where('is_active', true)
                        ->whereNotNull('referral_code')
                        ->where('referral_code', '!=', '')
                        ->firstOrFail();
                    $this->referralService->attachToPendaftaran($pendaftaran, $referrer->referral_code);
                }

                $thirdPartyUsers = User::query()->whereIn('id', $thirdPartyIds)->get(['id', 'name']);
                foreach ($thirdPartyUsers as $index => $thirdPartyUser) {
                    $pendaftaran->pihakKetiga()->create([
                        'user_id' => $thirdPartyUser->id,
                        'nama' => $thirdPartyUser->name,
                        'urutan' => $index,
                    ]);
                }

                if ($proof && $proofPath) {
                    $originalName = basename(str_replace('\\', '/', $proof->getClientOriginalName()));
                    $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?: 'bukti-transfer';

                    $pendaftaran->buktiPembayaranManual()->create([
                        'file_path' => $proofPath,
                        'original_name' => substr($originalName, 0, 255),
                        'status' => PendaftaranBuktiPembayaran::STATUS_MENUNGGU_VERIFIKASI,
                        'diunggah_oleh' => $adminId,
                    ]);
                }

                try {
                    AuditLogService::record(
                        module: 'SPMB',
                        action: 'pendaftaran_manual_dibuat',
                        tableName: $pendaftaran->getTable(),
                        recordId: $pendaftaran->id,
                        newValues: [
                            'no_pendaftaran' => $pendaftaran->no_pendaftaran,
                            'user_id' => $user->id,
                            'sumber_pendaftaran' => 'manual',
                            'status' => $pendaftaran->status,
                            'referrer_user_id' => $referrerId,
                            'jumlah_pihak_ketiga' => count($thirdPartyIds),
                            'bukti_transfer_diunggah' => (bool) $proofPath,
                            'diinput_oleh' => $adminId,
                        ],
                        request: $request,
                    );
                } catch (Throwable $exception) {
                    \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log pendaftaran manual: '.$exception->getMessage());
                }

                $pendaftaran->load([
                    'gelombangPenerimaan.jalurMasuk',
                    'programStudi',
                    'programStudiPilihan2',
                    'user:id,username,name,email',
                    'referrer:id,username,name,referral_code',
                    'pihakKetiga',
                    'buktiPembayaranManual',
                ]);

                return [
                    'pendaftaran' => $pendaftaran,
                    'akun' => [
                        'username' => $user->username,
                        'email' => $user->email,
                        'password_sementara' => $temporaryPassword,
                    ],
                ];
            });
        } catch (Throwable $exception) {
            if ($proofPath) {
                $this->fileStorage->delete($proofPath, null, true);
            }

            throw $exception;
        }
    }

    public function createThirdPartyAccount(array $data, int $adminId, Request $request): array
    {
        $roleSlug = SystemSetting::get('third_party_registration_role');
        $role = $roleSlug ? Role::query()->where('slug', $roleSlug)->where('is_active', true)->first() : null;

        if (! $role) {
            throw ValidationException::withMessages([
                'role' => 'Role pihak ketiga belum ditentukan atau tidak aktif di Pengaturan Sistem.',
            ]);
        }

        return DB::transaction(function () use ($data, $adminId, $request, $role) {
            $temporaryPassword = Str::random(20);
            $user = User::create([
                'username' => $this->generateUsername($data['email'], 'pt'),
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($temporaryPassword),
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
                'is_verified' => true,
            ]);
            $user->email_verified_at = now();
            $user->save();
            $user->roles()->syncWithoutDetaching([$role->id]);

            try {
                AuditLogService::record(
                    module: 'SPMB',
                    action: 'akun_pihak_ketiga_dibuat',
                    tableName: $user->getTable(),
                    recordId: $user->id,
                    newValues: ['role_id' => $role->id, 'email' => $user->email],
                    request: $request,
                );
            } catch (Throwable $exception) {
                \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log akun pihak ketiga: '.$exception->getMessage());
            }

            return [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'password_sementara' => $temporaryPassword,
                'role' => $role->name,
            ];
        });
    }

    private function generateUsername(string $email, string $prefix = 'cm'): string
    {
        $base = Str::slug(Str::before($email, '@'), '_');
        $base = substr($base !== '' ? $base : 'account', 0, 24);

        do {
            $username = $prefix.'_'.$base.'_'.Str::lower(Str::random(6));
        } while (User::withTrashed()->where('username', $username)->exists());

        return $username;
    }

    private function generateRegistrationNumber(): string
    {
        do {
            $number = 'REG-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (PendaftaranCalonMhs::withTrashed()->where('no_pendaftaran', $number)->exists());

        return $number;
    }
}

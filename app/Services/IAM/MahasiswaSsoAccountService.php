<?php

namespace App\Services\IAM;

use App\Models\Role;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\MahasiswaStatus;
use App\Models\Simpeg\Pegawai;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Membuat/menghubungkan akun SSO (core_users) untuk mahasiswa hasil sinkronisasi PDDIKTI.
 *
 * Aturan: username = NIM, kata sandi bawaan dari konfigurasi (bukan dari kode), role `mahasiswa`.
 * Akun yang sudah ada TIDAK diubah (username/kata sandi dibiarkan); akun yang sudah dihapus atau
 * sudah milik orang lain dilewati dengan alasan, tidak ditebak. Idempoten: hanya memproses
 * mahasiswa hasil Feeder yang belum punya user_id.
 */
class MahasiswaSsoAccountService
{
    public const CHUNK_SIZE = 500;
    private const SKIPPED_ITEM_LIMIT = 100;

    private ?string $passwordHash = null;
    private ?int $roleId = null;

    public function isConfigured(): bool
    {
        return filled(config('iam.sso.mahasiswa_default_password'));
    }

    public function pendingCount(): int
    {
        return $this->pendingQuery()->count();
    }

    /**
     * @param  callable(int $processed, int $total): void|null  $onProgress
     * @return array{sso_created:int,sso_linked:int,sso_skipped:int,sso_inactive:int,sso_skipped_items:array<int,array<string,mixed>>}
     */
    public function provisionPending(?int $actorId = null, ?callable $onProgress = null): array
    {
        $result = [
            'sso_created' => 0,
            'sso_linked' => 0,
            'sso_skipped' => 0,
            'sso_inactive' => 0,
            'sso_skipped_items' => [],
        ];
        if (!$this->isConfigured()) {
            return $result;
        }

        $total = $this->pendingCount();
        $processed = 0;

        $this->pendingQuery()->chunkById(self::CHUNK_SIZE, function ($students) use (&$result, &$processed, $total, $actorId, $onProgress): void {
            $before = $result['sso_created'] + $result['sso_linked'];
            foreach ($students as $student) {
                $processed++;
                try {
                    $outcome = DB::transaction(fn () => $this->provisionOne($student));
                } catch (\Throwable $e) {
                    report($e);
                    $outcome = ['status' => 'skipped', 'reason' => 'Gagal membuat akun SSO: ' . class_basename($e) . '.'];
                }

                match ($outcome['status']) {
                    'created' => $result['sso_created']++,
                    'linked' => $result['sso_linked']++,
                    default => $this->recordSkip($result, $student, $outcome['reason'] ?? 'Tidak diketahui.'),
                };
                if (($outcome['inactive'] ?? false) === true) {
                    $result['sso_inactive']++;
                }
            }

            $this->recordAudit($actorId, $result['sso_created'] + $result['sso_linked'] - $before);
            if ($onProgress !== null) {
                $onProgress($processed, $total);
            }
        });

        return $result;
    }

    /** @return array{status:string,reason?:string,inactive?:bool} */
    public function provisionOne(Mahasiswa $student): array
    {
        $nim = trim((string) $student->nim);
        if ($nim === '') {
            return ['status' => 'skipped', 'reason' => 'NIM kosong; username SSO tidak dapat dibentuk.'];
        }

        $roleId = $this->roleId();
        if ($roleId === null) {
            return ['status' => 'skipped', 'reason' => 'Role mahasiswa belum terdaftar di core_roles.'];
        }

        $matches = User::withTrashed()->where('username', $nim)->get();
        if ($matches->count() > 1) {
            return ['status' => 'skipped', 'reason' => 'Lebih dari satu akun cocok dengan username NIM.'];
        }

        $existing = $matches->first();
        if ($existing !== null) {
            if ($existing->trashed()) {
                return ['status' => 'skipped', 'reason' => 'Akun dengan username NIM ini sudah dihapus; perlu rekonsiliasi.'];
            }
            $claimedByOtherStudent = Mahasiswa::withTrashed()
                ->where('user_id', $existing->id)
                ->where('id', '!=', $student->id)
                ->exists();
            if ($claimedByOtherStudent
                || Dosen::withTrashed()->where('user_id', $existing->id)->exists()
                || Pegawai::withTrashed()->where('user_id', $existing->id)->exists()) {
                return ['status' => 'skipped', 'reason' => 'Akun dengan username NIM ini sudah terhubung ke dosen/pegawai/mahasiswa lain.'];
            }

            // Hubungkan saja; kata sandi dan data akun yang sudah ada tidak diubah.
            $existing->roles()->syncWithoutDetaching([$roleId]);
            Mahasiswa::whereKey($student->id)->update(['user_id' => $existing->id]);

            return ['status' => 'linked'];
        }

        $email = $this->resolveEmail($student->email, $nim);
        if ($email === null) {
            return ['status' => 'skipped', 'reason' => 'Email akun tidak dapat dibentuk secara unik.'];
        }

        $isActive = strtoupper((string) $student->status) !== MahasiswaStatus::NON_AKTIF;
        $phone = trim((string) $student->telepon);

        $user = User::create([
            'username' => $nim,
            'name' => trim((string) $student->nama_lengkap) !== '' ? trim((string) $student->nama_lengkap) : $nim,
            'email' => $email,
            'password' => $this->passwordHash(),
            'phone' => $phone !== '' ? mb_substr($phone, 0, 255) : null,
            'is_active' => $isActive,
            'is_verified' => true,
        ]);
        $user->roles()->syncWithoutDetaching([$roleId]);
        Mahasiswa::whereKey($student->id)->update(['user_id' => $user->id]);

        return ['status' => 'created', 'inactive' => !$isActive];
    }

    /** Email PDDIKTI bila valid & belum dipakai; selain itu NIM@domain-cadangan (null bila bentrok). */
    private function resolveEmail(?string $candidate, string $nim): ?string
    {
        $candidate = strtolower(trim((string) $candidate));
        if ($candidate !== ''
            && strlen($candidate) <= 255
            && filter_var($candidate, FILTER_VALIDATE_EMAIL)
            && !User::withTrashed()->where('email', $candidate)->exists()) {
            return $candidate;
        }

        $local = preg_replace('/[^a-z0-9._-]/', '', strtolower($nim));
        if ($local === '' || $local === null) {
            return null;
        }
        $fallback = $local . '@' . config('iam.sso.placeholder_email_domain', 'campus.ac.id');

        return User::withTrashed()->where('email', $fallback)->exists() ? null : $fallback;
    }

    private function passwordHash(): string
    {
        // Satu kali hash per proses: bcrypt per akun akan memakan puluhan menit untuk ribuan mahasiswa.
        return $this->passwordHash ??= Hash::make((string) config('iam.sso.mahasiswa_default_password'));
    }

    private function roleId(): ?int
    {
        return $this->roleId ??= Role::where('slug', 'mahasiswa')->value('id');
    }

    private function pendingQuery()
    {
        return Mahasiswa::query()->whereNull('user_id')->whereNotNull('id_feeder');
    }

    private function recordSkip(array &$result, Mahasiswa $student, string $reason): void
    {
        $result['sso_skipped']++;
        if (count($result['sso_skipped_items']) < self::SKIPPED_ITEM_LIMIT) {
            $result['sso_skipped_items'][] = [
                'source_id' => $student->id_feeder,
                'item_ref' => $student->nim,
                'reason' => $reason,
            ];
        }
    }

    private function recordAudit(?int $actorId, int $count): void
    {
        if ($count <= 0) {
            return;
        }
        try {
            AuditLogService::record(
                module: 'IAM',
                action: 'create',
                tableName: 'core_users',
                recordId: null,
                oldValues: null,
                newValues: ['sumber' => 'sync_pddikti_mahasiswa', 'akun_diproses' => $count],
                actorId: $actorId,
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit pembuatan akun SSO mahasiswa.', ['error' => $e->getMessage()]);
        }
    }
}

<?php

namespace App\Services\Spmb;

use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\ReferralUsage;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SpmbReferralService
{
    /**
     * Normalisasi kode referral: trim & uppercase.
     */
    public function normalizeCode(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $normalized = strtoupper(trim($code));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Cari pemilik kode referral yang valid & aktif.
     */
    public function findReferrer(?string $code): ?User
    {
        $normalized = $this->normalizeCode($code);

        if (! $normalized) {
            return null;
        }

        return User::where('referral_code', $normalized)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Samarkan nama pemilik kode untuk kebutuhan konfirmasi di UI.
     */
    public function maskName(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return 'Pengguna';
        }

        $parts = preg_split('/\s+/', $name);
        $masked = array_map(function (string $part) {
            $length = mb_strlen($part);
            if ($length <= 2) {
                return mb_substr($part, 0, 1).'*';
            }

            return mb_substr($part, 0, 1).str_repeat('*', $length - 2).mb_substr($part, -1);
        }, $parts);

        return implode(' ', $masked);
    }

    /**
     * Validasi kode referral dan kembalikan ringkasan pemiliknya.
     *
     * @throws ValidationException
     */
    public function validate(?string $code, ?int $selfUserId = null): array
    {
        $normalized = $this->normalizeCode($code);

        if (! $normalized) {
            throw ValidationException::withMessages([
                'referral_code' => ['Kode referral wajib diisi.'],
            ]);
        }

        $referrer = $this->findReferrer($normalized);

        if (! $referrer) {
            throw ValidationException::withMessages([
                'referral_code' => ['Kode referral tidak ditemukan atau tidak aktif.'],
            ]);
        }

        if ($selfUserId && $referrer->id === $selfUserId) {
            throw ValidationException::withMessages([
                'referral_code' => ['Anda tidak dapat menggunakan kode referral milik sendiri.'],
            ]);
        }

        return [
            'referral_code' => $referrer->referral_code,
            'referrer_user_id' => $referrer->id,
            'referrer_name' => $this->maskName($referrer->name),
            'is_valid' => true,
        ];
    }

    /**
     * Lampirkan / perbarui kode referral pada pendaftaran calon mahasiswa.
     *
     * @throws ValidationException
     */
    public function attachToPendaftaran(PendaftaranCalonMhs $pendaftaran, ?string $code): ?ReferralUsage
    {
        $normalized = $this->normalizeCode($code);

        if (! $normalized) {
            return $pendaftaran->referralUsage;
        }

        if (! in_array($pendaftaran->status, [PendaftaranCalonMhs::STATUS_DRAFT, PendaftaranCalonMhs::STATUS_SUBMITTED], true)) {
            throw ValidationException::withMessages([
                'used_referral_code' => ['Kode referral tidak dapat diubah setelah pendaftaran diverifikasi.'],
            ]);
        }

        $summary = $this->validate($normalized, $pendaftaran->user_id);

        return DB::transaction(function () use ($pendaftaran, $summary) {
            $pendaftaran->update([
                'used_referral_code' => $summary['referral_code'],
                'referrer_user_id' => $summary['referrer_user_id'],
                'referral_validated_at' => now(),
            ]);

            $usage = ReferralUsage::updateOrCreate(
                ['pendaftaran_id' => $pendaftaran->id],
                [
                    'referee_user_id' => $pendaftaran->user_id,
                    'referrer_user_id' => $summary['referrer_user_id'],
                    'referral_code' => $summary['referral_code'],
                    'status' => ReferralUsage::STATUS_CLAIMED,
                    'qualified_at' => null,
                    'keterangan' => 'Kode referral diterapkan pada pendaftaran '.$pendaftaran->no_pendaftaran,
                ]
            );

            AuditLogService::record(
                module: 'SPMB',
                action: 'update',
                tableName: 'spmb_referral_usages',
                recordId: $usage->id,
                oldValues: null,
                newValues: $usage->toArray()
            );

            return $usage;
        });
    }

    /**
     * Tandai penggunaan referral sebagai qualified (lolos administrasi).
     */
    public function qualify(PendaftaranCalonMhs $pendaftaran): ?ReferralUsage
    {
        $usage = ReferralUsage::where('pendaftaran_id', $pendaftaran->id)->first();

        if (! $usage || $usage->status !== ReferralUsage::STATUS_CLAIMED) {
            return $usage;
        }

        $usage->update([
            'status' => ReferralUsage::STATUS_QUALIFIED,
            'qualified_at' => now(),
        ]);

        AuditLogService::record(
            module: 'SPMB',
            action: 'approve',
            tableName: 'spmb_referral_usages',
            recordId: $usage->id,
            oldValues: ['status' => ReferralUsage::STATUS_CLAIMED],
            newValues: ['status' => ReferralUsage::STATUS_QUALIFIED]
        );

        return $usage;
    }

    /**
     * Batalkan penggunaan referral (mis. gagal administrasi).
     */
    public function cancel(PendaftaranCalonMhs $pendaftaran, ?string $keterangan = null): ?ReferralUsage
    {
        $usage = ReferralUsage::where('pendaftaran_id', $pendaftaran->id)->first();

        if (! $usage || $usage->status === ReferralUsage::STATUS_CANCELLED) {
            return $usage;
        }

        $old = ['status' => $usage->status];

        $usage->update([
            'status' => ReferralUsage::STATUS_CANCELLED,
            'keterangan' => $keterangan ?? 'Pendaftaran tidak lolos administrasi.',
        ]);

        AuditLogService::record(
            module: 'SPMB',
            action: 'reject',
            tableName: 'spmb_referral_usages',
            recordId: $usage->id,
            oldValues: $old,
            newValues: ['status' => ReferralUsage::STATUS_CANCELLED]
        );

        return $usage;
    }

    /**
     * Ringkasan statistik referral milik satu pengguna.
     */
    public function statsForUser(User $user): array
    {
        $base = ReferralUsage::where('referrer_user_id', $user->id);

        return [
            'referral_code' => $user->referral_code,
            'total' => (clone $base)->count(),
            'claimed' => (clone $base)->where('status', ReferralUsage::STATUS_CLAIMED)->count(),
            'qualified' => (clone $base)->where('status', ReferralUsage::STATUS_QUALIFIED)->count(),
            'rewarded' => (clone $base)->where('status', ReferralUsage::STATUS_REWARDED)->count(),
            'cancelled' => (clone $base)->where('status', ReferralUsage::STATUS_CANCELLED)->count(),
        ];
    }
}

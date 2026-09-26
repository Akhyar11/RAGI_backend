<?php

namespace App\Services\Spmb;

use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Spmb\HasilSeleksi;
use App\Models\Spmb\KomponenBiayaRoleReward;
use App\Models\Spmb\PayoutReferral;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\ReferralUsage;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

            // Jejak audit ditangani otomatis oleh ReferralUsageObserver.

            return $usage;
        });
    }

    /**
     * Tandai penggunaan referral sebagai qualified (lolos administrasi).
     */
    public function qualify(PendaftaranCalonMhs $pendaftaran): ?ReferralUsage
    {
        $usage = ReferralUsage::where('pendaftaran_id', $pendaftaran->id)->first();

        // Boleh dipulihkan dari 'cancelled' bila verifikasi diubah menjadi lulus.
        if (! $usage || in_array($usage->status, [ReferralUsage::STATUS_QUALIFIED, ReferralUsage::STATUS_REWARDED], true)) {
            return $usage;
        }

        $old = ['status' => $usage->status];

        $usage->update([
            'status' => ReferralUsage::STATUS_QUALIFIED,
            'qualified_at' => now(),
            'keterangan' => 'Lolos administrasi.',
        ]);

        // Jejak audit ditangani otomatis oleh ReferralUsageObserver.

        return $usage;
    }

    /**
     * Batalkan penggunaan referral (mis. gagal administrasi).
     */
    public function cancel(PendaftaranCalonMhs $pendaftaran, ?string $keterangan = null): ?ReferralUsage
    {
        $usage = ReferralUsage::where('pendaftaran_id', $pendaftaran->id)->first();

        // Jangan batalkan bila sudah dicairkan (payout) atau sudah rewarded.
        if (! $usage
            || $usage->status === ReferralUsage::STATUS_CANCELLED
            || $usage->status === ReferralUsage::STATUS_REWARDED
            || $usage->payout_id) {
            return $usage;
        }

        $usage->update([
            'status' => ReferralUsage::STATUS_CANCELLED,
            'keterangan' => $keterangan ?? 'Pendaftaran tidak lolos administrasi.',
        ]);

        // Jejak audit ditangani otomatis oleh ReferralUsageObserver.

        return $usage;
    }

    /**
     * Nominal reward per 1 referral untuk referrer, mengikuti mapping
     * komponen biaya SPMB -> role.
     */
    public function rewardPerReferral(User $referrer): float
    {
        $roleIds = $referrer->roles()->pluck('core_roles.id')->all();

        if (empty($roleIds)) {
            return 0.0;
        }

        return (float) KomponenBiayaRoleReward::query()
            ->whereIn('role_id', $roleIds)
            ->whereHas('komponenBiaya', fn ($q) => $q->referralReward()->where('is_active', true))
            ->sum('nominal');
    }

    /**
     * Query referral yang LAYAK DICAIRKAN:
     * - status qualified & belum ditandai payout.
     * - pendaftar "selesai" (lulus/mahasiswa baru atau daftar ulang lunas).
     * - sudah membayar biaya daftar ulang minimal sesuai config.
     */
    public function eligiblePayoutQuery(User $referrer)
    {
        $minPayment = (float) config('spmb.referral.min_daftar_ulang_payment', 100000);
        $completed = (array) config('spmb.referral.completed_statuses', [
            PendaftaranCalonMhs::STATUS_LULUS_ADMINISTRASI,
            PendaftaranCalonMhs::STATUS_MAHASISWA_BARU,
        ]);

        return ReferralUsage::query()
            ->where('referrer_user_id', $referrer->id)
            ->where('status', ReferralUsage::STATUS_QUALIFIED)
            ->whereNull('payout_id')
            ->whereHas('pendaftaran', function ($q) use ($completed, $minPayment) {
                $q->where(function ($w) use ($completed) {
                    $w->whereIn('status', $completed)
                        ->orWhereHas('hasilSeleksi', fn ($h) => $h->where('status_daftar_ulang', HasilSeleksi::STATUS_DAFTAR_ULANG_LUNAS));
                })->whereExists(function ($sub) use ($minPayment) {
                    $sub->select(DB::raw(1))
                        ->from((new TagihanMahasiswa)->getTable())
                        ->whereColumn('calon_mahasiswa_id', 'spmb_pendaftaran_calon_mhs.id')
                        ->where('tipe_referensi', TagihanMahasiswa::TIPE_SPMB_DAFTAR_ULANG)
                        ->where('total_bayar', '>=', $minPayment);
                });
            });
    }

    /**
     * Ringkasan nominal yang bisa dicairkan.
     */
    public function withdrawableSummary(User $referrer): array
    {
        $count = (clone $this->eligiblePayoutQuery($referrer))->count();
        $perReferral = $this->rewardPerReferral($referrer);

        return [
            'referral_code' => $referrer->referral_code,
            'count' => $count,
            'reward_per_referral' => $perReferral,
            'total_nominal' => $perReferral * $count,
        ];
    }

    /**
     * Buat bukti pencairan (payout) dan tandai referral terkait.
     * Status referral TIDAK diubah (tetap qualified), hanya ditandai payout.
     */
    public function createPayout(User $referrer, ?string $keterangan = null): PayoutReferral
    {
        return DB::transaction(function () use ($referrer, $keterangan) {
            $usages = $this->eligiblePayoutQuery($referrer)->lockForUpdate()->get();
            $perReferral = $this->rewardPerReferral($referrer);

            $payout = PayoutReferral::create([
                'referrer_user_id' => $referrer->id,
                'referral_count' => $usages->count(),
                'total_nominal' => $perReferral * $usages->count(),
                'nomor_bukti' => 'PAYOUT-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
                'generated_at' => now(),
                'keterangan' => $keterangan,
            ]);

            if ($usages->isNotEmpty()) {
                // update() per model agar observer mencatat jejak perubahan.
                foreach ($usages as $usage) {
                    $usage->update(['payout_id' => $payout->id]);
                }
            }

            // Jejak audit pembuatan payout ditangani PayoutReferralObserver.

            return $payout->load('usages.pendaftaran');
        });
    }

    /**
     * Daftar referral milik pengguna (paginasi + filter + mapping data).
     *
     * @return array{paginator: \Illuminate\Contracts\Pagination\LengthAwarePaginator, items: \Illuminate\Support\Collection, sort_by: string, sort_order: string}
     */
    public function getUsagesForUser(User $user, array $filters = [], int $perPage = 15): array
    {
        $allowedSort = ['created_at', 'status', 'referral_code'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSort, true) ? $filters['sort_by'] : 'created_at';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = ReferralUsage::query()
            ->with([
                'pendaftaran:id,no_pendaftaran,nama_lengkap,status,status_pembayaran,gelombang_id',
                'pendaftaran.gelombangPenerimaan:id,nama',
            ])
            ->where('referrer_user_id', $user->id);

        $allowedStatuses = [
            ReferralUsage::STATUS_CLAIMED,
            ReferralUsage::STATUS_QUALIFIED,
            ReferralUsage::STATUS_REWARDED,
            ReferralUsage::STATUS_CANCELLED,
        ];

        if (! empty($filters['status']) && in_array($filters['status'], $allowedStatuses, true)) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('referral_code', 'like', "%{$search}%")
                    ->orWhereHas('pendaftaran', function ($pq) use ($search) {
                        $pq->where('nama_lengkap', 'like', "%{$search}%")
                            ->orWhere('no_pendaftaran', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['start_date'])) {
            $query->whereDate((new ReferralUsage)->qualifyColumn('created_at'), '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate((new ReferralUsage)->qualifyColumn('created_at'), '<=', $filters['end_date']);
        }

        $query->orderBy($sortBy, $sortOrder);

        $paginator = $query->paginate($perPage);
        $perReferral = $this->rewardPerReferral($user);

        $items = collect($paginator->items())->map(function (ReferralUsage $usage) use ($perReferral) {
            return [
                'id' => $usage->id,
                'referral_code' => $usage->referral_code,
                'status' => $usage->status,
                'qualified_at' => $usage->qualified_at,
                'rewarded_at' => $usage->rewarded_at,
                'payout_id' => $usage->payout_id,
                'reward_nominal' => $usage->status === ReferralUsage::STATUS_QUALIFIED ? $perReferral : 0,
                'nama_pendaftar' => $usage->pendaftaran?->nama_lengkap,
                'no_pendaftaran' => $usage->pendaftaran?->no_pendaftaran,
                'status_pendaftaran' => $usage->pendaftaran?->status,
                'status_pembayaran' => $usage->pendaftaran?->status_pembayaran,
                'gelombang' => $usage->pendaftaran?->gelombangPenerimaan?->nama,
                'created_at' => $usage->created_at,
            ];
        })->values();

        return [
            'paginator' => $paginator,
            'items' => $items,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * Generate bukti pencairan referral sebagai PDF (binary string).
     */
    public function generatePayoutPdf(PayoutReferral $payout, User $user): string
    {
        $payout->loadMissing('usages.pendaftaran');

        $html = view('spmb.referral-payout', [
            'payout' => $payout,
            'user' => $user,
            'generatedAt' => now(),
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
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

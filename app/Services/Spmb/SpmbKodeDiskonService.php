<?php

namespace App\Services\Spmb;

use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\SpmbKodeDiskon;
use App\Models\Spmb\SpmbKodeDiskonPenggunaan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SpmbKodeDiskonService
{
    public function __construct(private readonly MasterBiayaService $masterBiayaService) {}

    public function create(array $data): SpmbKodeDiskon
    {
        return DB::transaction(fn () => SpmbKodeDiskon::create($data));
    }

    public function update(SpmbKodeDiskon $kodeDiskon, array $data): SpmbKodeDiskon
    {
        return DB::transaction(function () use ($kodeDiskon, $data) {
            $kodeDiskon->update($data);

            return $kodeDiskon->fresh();
        });
    }

    public function delete(SpmbKodeDiskon $kodeDiskon): bool
    {
        return (bool) $kodeDiskon->delete();
    }

    /**
     * Preview or retrieve a discount against explicit Master Biaya registration items.
     * Legacy SIKEU fallback tariffs are intentionally excluded.
     *
     * @return array{kode:string,subtotal_komponen:float,nominal_diskon:float,total_setelah_diskon:float,tipe_diskon:string,nilai:float,already_applied:bool}
     */
    public function preview(
        string $kode,
        int $masterTipeJalurId,
        int $programStudiId,
        int $gelombangId,
        ?PendaftaranCalonMhs $pendaftaran = null,
    ): array {
        $normalizedCode = Str::upper(trim($kode));
        $existingUsage = $pendaftaran
            ? SpmbKodeDiskonPenggunaan::where('pendaftaran_id', $pendaftaran->id)->first()
            : null;

        if ($existingUsage) {
            if ($existingUsage->kode_diskon !== $normalizedCode) {
                $this->invalid('Kode diskon lain sudah diterapkan pada pendaftaran ini.');
            }

            $discount = (float) $existingUsage->nominal_diskon;
            $subtotal = (float) $existingUsage->subtotal_komponen;

            return [
                'kode' => $existingUsage->kode_diskon,
                'subtotal_komponen' => $subtotal,
                'nominal_diskon' => $discount,
                'total_setelah_diskon' => max(0, round($subtotal - $discount, 2)),
                'tipe_diskon' => $existingUsage->kodeDiskon?->tipe_diskon ?? 'nominal',
                'nilai' => (float) ($existingUsage->kodeDiskon?->nilai ?? $discount),
                'already_applied' => true,
            ];
        }

        if ($pendaftaran && $this->hasRegistrationBill($pendaftaran->id)) {
            $this->invalid('Tagihan pendaftaran sudah dibuat. Kode diskon harus dimasukkan sebelum tagihan diterbitkan.');
        }

        $discountCode = SpmbKodeDiskon::query()->where('kode', $normalizedCode)->first();
        if (! $discountCode) {
            $this->invalid('Kode diskon tidak ditemukan atau sudah tidak berlaku.');
        }

        $this->assertCodeIsUsable($discountCode);

        $details = $this->registrationDetails($masterTipeJalurId, $programStudiId);
        $otherDiscount = $pendaftaran
            ? app(SpmbPotonganCalonService::class)->buildPotonganPayload($pendaftaran, 'pendaftaran', $details)
            : [];
        $subtotal = $this->sumNominal($details);
        $alreadyDiscounted = array_sum(array_map(
            static fn (array $item): float => (float) ($item['nominal_potongan'] ?? 0),
            $otherDiscount,
        ));
        $amount = $this->calculateDiscount($discountCode, $subtotal, $alreadyDiscounted);

        $this->assertClaimQuotaAvailable($discountCode);

        return [
            'kode' => $discountCode->kode,
            'subtotal_komponen' => $subtotal,
            'nominal_diskon' => $amount,
            'total_setelah_diskon' => max(0, round($subtotal - $alreadyDiscounted - $amount, 2)),
            'tipe_diskon' => $discountCode->tipe_diskon,
            'nilai' => (float) $discountCode->nilai,
            'already_applied' => false,
        ];
    }

    /**
     * Reserve a code once per registration and persist its monetary snapshot.
     * The coupon row lock prevents concurrent requests from exceeding its usage limit.
     *
     * @param array<int, array<string, mixed>> $details
     * @param array<int, array<string, mixed>> $existingPotongan
     */
    public function applyToRegistration(
        PendaftaranCalonMhs $pendaftaran,
        ?string $kode,
        array $details,
        array $existingPotongan = [],
    ): ?SpmbKodeDiskonPenggunaan {
        $existingUsage = SpmbKodeDiskonPenggunaan::where('pendaftaran_id', $pendaftaran->id)->first();
        $normalizedCode = Str::upper(trim((string) $kode));

        if ($existingUsage) {
            if ($normalizedCode !== '' && $existingUsage->kode_diskon !== $normalizedCode) {
                $this->invalid('Kode diskon sudah diterapkan dan tidak dapat diganti.');
            }

            return $existingUsage;
        }

        if ($normalizedCode === '') {
            return null;
        }

        if ($this->hasRegistrationBill($pendaftaran->id)) {
            $this->invalid('Tagihan pendaftaran sudah dibuat. Kode diskon harus dimasukkan sebelum tagihan diterbitkan.');
        }

        return DB::transaction(function () use ($pendaftaran, $normalizedCode, $details, $existingPotongan) {
            PendaftaranCalonMhs::query()->whereKey($pendaftaran->id)->lockForUpdate()->firstOrFail();

            $discountCode = SpmbKodeDiskon::query()
                ->where('kode', $normalizedCode)
                ->lockForUpdate()
                ->first();

            if (! $discountCode) {
                $this->invalid('Kode diskon tidak ditemukan atau sudah tidak berlaku.');
            }

            $existingUsage = SpmbKodeDiskonPenggunaan::where('pendaftaran_id', $pendaftaran->id)->first();
            if ($existingUsage) {
                if ($existingUsage->kode_diskon !== $normalizedCode) {
                    $this->invalid('Kode diskon lain sudah diterapkan pada pendaftaran ini.');
                }

                return $existingUsage;
            }

            $this->assertCodeIsUsable($discountCode);
            $eligibleDetails = array_values(array_filter(
                $details,
                static fn (array $item): bool => ! empty($item['komponen_biaya_id']) && (float) ($item['nominal'] ?? 0) > 0,
            ));
            if ($eligibleDetails === []) {
                $this->invalid('Kode diskon hanya dapat digunakan pada komponen Master Biaya yang dibebankan saat pendaftaran.');
            }

            $subtotal = $this->sumNominal($eligibleDetails);
            $alreadyDiscounted = array_sum(array_map(
                static fn (array $item): float => (float) ($item['nominal_potongan'] ?? 0),
                $existingPotongan,
            ));
            $amount = $this->calculateDiscount($discountCode, $subtotal, $alreadyDiscounted);

            if ($amount <= 0) {
                $this->invalid('Tidak ada sisa biaya pendaftaran yang dapat dipotong dengan kode ini.');
            }

            $this->assertClaimQuotaAvailable($discountCode);

            return SpmbKodeDiskonPenggunaan::create([
                'kode_diskon_id' => $discountCode->id,
                'pendaftaran_id' => $pendaftaran->id,
                'kode_diskon' => $discountCode->kode,
                'subtotal_komponen' => $subtotal,
                'nominal_diskon' => $amount,
                'digunakan_at' => now(),
            ]);
        });
    }

    public function ensureCodeMatchesRegistration(PendaftaranCalonMhs $pendaftaran, ?string $kode): void
    {
        $usage = SpmbKodeDiskonPenggunaan::where('pendaftaran_id', $pendaftaran->id)->first();
        $normalizedCode = Str::upper(trim((string) $kode));

        if ($usage && $normalizedCode !== '' && $usage->kode_diskon !== $normalizedCode) {
            $this->invalid('Kode diskon sudah diterapkan dan tidak dapat diganti.');
        }

        if (! $usage && $normalizedCode !== '') {
            $this->invalid('Tagihan pendaftaran sudah dibuat tanpa kode diskon. Kode tidak dapat ditambahkan setelah tagihan diterbitkan.');
        }
    }

    public function toTagihanPotongan(?SpmbKodeDiskonPenggunaan $penggunaan): array
    {
        if (! $penggunaan || (float) $penggunaan->nominal_diskon <= 0) {
            return [];
        }

        return [[
            'tipe' => 'diskon',
            'nominal_potongan' => (float) $penggunaan->nominal_diskon,
            'keterangan' => 'Kode diskon '.$penggunaan->kode_diskon,
        ]];
    }

    public function attachTagihan(SpmbKodeDiskonPenggunaan $penggunaan, int $tagihanId): SpmbKodeDiskonPenggunaan
    {
        $penggunaan->update(['tagihan_id' => $tagihanId]);

        return $penggunaan->fresh();
    }

    /** @return array<int, array<string, mixed>> */
    private function registrationDetails(int $masterTipeJalurId, int $programStudiId): array
    {
        $details = $this->masterBiayaService->buildDetailTagihan($masterTipeJalurId, $programStudiId, true);
        $eligible = array_values(array_filter(
            $details,
            static fn (array $item): bool => ! empty($item['komponen_biaya_id']) && (float) ($item['nominal'] ?? 0) > 0,
        ));

        if ($eligible === []) {
            $this->invalid('Belum ada komponen Master Biaya yang disetel sebagai beban pendaftaran untuk pilihan ini.');
        }

        return $eligible;
    }

    private function assertCodeIsUsable(SpmbKodeDiskon $discountCode): void
    {
        $today = now(config('spmb.timezone', 'Asia/Jakarta'))->toDateString();

        if (! $discountCode->is_active) {
            $this->invalid('Kode diskon sedang tidak aktif.');
        }
        if ($discountCode->berlaku_mulai && $discountCode->berlaku_mulai->toDateString() > $today) {
            $this->invalid('Kode diskon belum memasuki masa berlaku.');
        }
        if ($discountCode->berlaku_sampai && $discountCode->berlaku_sampai->toDateString() < $today) {
            $this->invalid('Masa berlaku kode diskon sudah berakhir.');
        }
    }

    private function assertClaimQuotaAvailable(SpmbKodeDiskon $discountCode): void
    {
        if ($discountCode->batas_penggunaan !== null
            && $discountCode->penggunaan()->count() >= $discountCode->batas_penggunaan) {
            $this->invalid('Kuota klaim kode diskon sudah habis.');
        }
    }

    private function calculateDiscount(SpmbKodeDiskon $discountCode, float $subtotal, float $alreadyDiscounted = 0): float
    {
        $remaining = max(0, $subtotal - $alreadyDiscounted);
        $amount = $discountCode->tipe_diskon === 'persen'
            ? round($subtotal * ((float) $discountCode->nilai / 100), 2)
            : (float) $discountCode->nilai;

        if ($discountCode->maksimum_potongan !== null) {
            $amount = min($amount, (float) $discountCode->maksimum_potongan);
        }

        return round(min($amount, $remaining), 2);
    }

    /** @param array<int, array<string, mixed>> $details */
    private function sumNominal(array $details): float
    {
        return round(array_sum(array_map(
            static fn (array $item): float => (float) ($item['nominal'] ?? 0),
            $details,
        )), 2);
    }

    private function hasRegistrationBill(int $pendaftaranId): bool
    {
        return TagihanMahasiswa::query()
            ->where('calon_mahasiswa_id', $pendaftaranId)
            ->where('source_system', 'SPMB')
            ->where(function ($query) {
                $query->where('tipe_referensi', TagihanMahasiswa::TIPE_SPMB_PENDAFTARAN)
                    ->orWhereNull('tipe_referensi');
            })
            ->exists();
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['kode_diskon' => $message]);
    }
}

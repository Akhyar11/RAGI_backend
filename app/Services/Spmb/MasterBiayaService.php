<?php

namespace App\Services\Spmb;

use App\Models\Sikeu\TarifSpmb;
use App\Models\Spmb\GelombangPenerimaan;
use App\Models\Spmb\MasterBiaya;
use App\Models\Spmb\MasterBiayaItem;
use App\Models\Spmb\MasterKomponenBiaya;
use App\Services\Sikeu\SpmbSikeuService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MasterBiayaService
{
    public function createKomponen(array $data): MasterKomponenBiaya
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['kode'])) {
                $data['kode'] = strtoupper(Str::slug($data['nama'], '_'));
            }

            $positionType = $data['position_type'] ?? 'end';
            $referenceId = $data['reference_id'] ?? null;

            $others = MasterKomponenBiaya::orderBy('urutan')->orderBy('id')->get();
            $newList = collect();

            if ($positionType === 'start') {
                $newList->push(null);
            }

            foreach ($others as $item) {
                if ($positionType === 'before' && $item->id == $referenceId) {
                    $newList->push(null);
                }
                $newList->push($item);
                if ($positionType === 'after' && $item->id == $referenceId) {
                    $newList->push(null);
                }
            }

            if ($positionType === 'end' || ! $newList->contains(null)) {
                $newList->push(null);
            }

            $targetUrutan = $newList->search(null) + 1;

            $order = 1;
            foreach ($newList as $item) {
                if ($item !== null) {
                    if ($order === $targetUrutan) {
                        $order++;
                    }
                    if ($item->urutan !== $order) {
                        $item->update(['urutan' => $order]);
                    }
                    $order++;
                }
            }

            $data['urutan'] = $targetUrutan;
            $data['kategori'] = $data['kategori'] ?? 'daftar_ulang';
            $data['tipe_potongan'] = $data['tipe_potongan'] ?? false;
            $data['is_active'] = $data['is_active'] ?? true;

            $roleRewards = $data['role_rewards'] ?? null;

            unset($data['position_type'], $data['reference_id'], $data['role_rewards']);

            $komponen = MasterKomponenBiaya::create($data);
            $this->syncRoleRewards($komponen, (bool) ($data['is_referral_reward'] ?? false), $roleRewards);

            return $komponen;
        });
    }

    public function updateKomponen(MasterKomponenBiaya $komponen, array $data): MasterKomponenBiaya
    {
        return DB::transaction(function () use ($komponen, $data) {
            $positionType = $data['position_type'] ?? 'keep';
            $referenceId = $data['reference_id'] ?? null;

            if ($positionType && $positionType !== 'keep') {
                $others = MasterKomponenBiaya::where('id', '!=', $komponen->id)->orderBy('urutan')->orderBy('id')->get();
                $newList = collect();

                if ($positionType === 'start') {
                    $newList->push($komponen);
                }

                foreach ($others as $item) {
                    if ($positionType === 'before' && $item->id == $referenceId) {
                        $newList->push($komponen);
                    }
                    $newList->push($item);
                    if ($positionType === 'after' && $item->id == $referenceId) {
                        $newList->push($komponen);
                    }
                }

                if ($positionType === 'end' || ! $newList->contains('id', $komponen->id)) {
                    $newList->push($komponen);
                }

                foreach ($newList as $index => $item) {
                    $newUrutan = $index + 1;
                    if ($item->id === $komponen->id) {
                        $data['urutan'] = $newUrutan;
                    } else {
                        if ($item->urutan !== $newUrutan) {
                            $item->update(['urutan' => $newUrutan]);
                        }
                    }
                }
            }

            $roleRewards = $data['role_rewards'] ?? null;

            unset($data['position_type'], $data['reference_id'], $data['role_rewards']);

            $komponen->update($data);

            $this->syncRoleRewards($komponen, (bool) ($komponen->is_referral_reward), $roleRewards);

            return $komponen->fresh();
        });
    }

    /**
     * Sinkronkan mapping nominal reward referral per role untuk komponen biaya.
     * Dipanggil di dalam DB::transaction create/update komponen.
     */
    protected function syncRoleRewards(MasterKomponenBiaya $komponen, bool $isReward, ?array $rewards): void
    {
        if (! $isReward) {
            // Hapus per-instance agar observer (audit log) terpicu.
            $komponen->roleRewards()->get()->each->delete();

            return;
        }

        if ($rewards === null) {
            return;
        }

        // Hapus per-instance agar observer (audit log) terpicu.
        $komponen->roleRewards()->get()->each->delete();

        foreach ($rewards as $row) {
            if (empty($row['role_id'])) {
                continue;
            }

            $komponen->roleRewards()->create([
                'role_id' => (int) $row['role_id'],
                'nominal' => (float) ($row['nominal'] ?? 0),
            ]);
        }
    }

    public function deleteKomponen(MasterKomponenBiaya $komponen): bool
    {
        return (bool) $komponen->delete();
    }

    public function restoreKomponen(MasterKomponenBiaya $komponen): bool
    {
        return (bool) $komponen->restore();
    }

    public function createBiaya(array $data): MasterBiaya
    {
        return DB::transaction(function () use ($data) {
            $masterBiaya = MasterBiaya::updateOrCreate(
                [
                    'master_tipe_jalur_id' => $data['master_tipe_jalur_id'],
                    'program_studi_id' => $data['program_studi_id'],
                ],
                [
                    'is_active' => $data['is_active'] ?? true,
                    'keterangan' => $data['keterangan'] ?? null,
                ]
            );

            $total = 0;
            if (! empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $nominal = (float) $item['nominal'];
                    $total += $nominal;

                    MasterBiayaItem::updateOrCreate(
                        [
                            'master_biaya_id' => $masterBiaya->id,
                            'komponen_biaya_id' => $item['komponen_biaya_id'],
                        ],
                        [
                            'nominal' => $nominal,
                            'dibebankan_saat_pendaftaran' => (bool) ($item['dibebankan_saat_pendaftaran'] ?? false),
                            'berlaku_diskon' => (bool) ($item['berlaku_diskon'] ?? false),
                            'keterangan' => $item['keterangan'] ?? null,
                        ]
                    );
                }
            }

            $masterBiaya->update(['total_biaya' => $total]);

            return $masterBiaya->load(['items.komponenBiaya', 'programStudi', 'masterTipeJalur']);
        });
    }

    public function updateBiaya(MasterBiaya $biaya, array $data): MasterBiaya
    {
        return DB::transaction(function () use ($biaya, $data) {
            $biaya->update([
                'master_tipe_jalur_id' => $data['master_tipe_jalur_id'] ?? $biaya->master_tipe_jalur_id,
                'program_studi_id' => $data['program_studi_id'] ?? $biaya->program_studi_id,
                'is_active' => $data['is_active'] ?? $biaya->is_active,
                'keterangan' => $data['keterangan'] ?? $biaya->keterangan,
            ]);

            if (isset($data['items'])) {
                $komponenIds = collect($data['items'])->pluck('komponen_biaya_id')->all();

                // Hapus item yang tidak lagi dipilih pada rincian master biaya.
                MasterBiayaItem::where('master_biaya_id', $biaya->id)
                    ->whereNotIn('komponen_biaya_id', $komponenIds)
                    ->get()
                    ->each
                    ->delete();

                $total = 0;
                foreach ($data['items'] as $item) {
                    $nominal = (float) $item['nominal'];
                    $total += $nominal;

                    MasterBiayaItem::updateOrCreate(
                        [
                            'master_biaya_id' => $biaya->id,
                            'komponen_biaya_id' => $item['komponen_biaya_id'],
                        ],
                        [
                            'nominal' => $nominal,
                            'dibebankan_saat_pendaftaran' => (bool) ($item['dibebankan_saat_pendaftaran'] ?? false),
                            'berlaku_diskon' => (bool) ($item['berlaku_diskon'] ?? false),
                            'keterangan' => $item['keterangan'] ?? null,
                        ]
                    );
                }

                $biaya->update(['total_biaya' => $total]);
            } else {
                $biaya->recalculateTotal();
            }

            return $biaya->load(['items.komponenBiaya', 'programStudi', 'masterTipeJalur']);
        });
    }

    public function deleteBiaya(MasterBiaya $biaya): bool
    {
        return (bool) $biaya->delete();
    }

    public function restoreBiaya(MasterBiaya $biaya): bool
    {
        return (bool) $biaya->restore();
    }

    /**
     * Ambil komponen biaya berdasarkan tahap beban (pendaftaran / daftar ulang)
     * untuk tipe jalur masuk + program studi tertentu.
     *
     * @return Collection<int, MasterBiayaItem>
     */
    public function getKomponenBeban(?int $masterTipeJalurId, ?int $programStudiId, bool $saatPendaftaran): Collection
    {
        if (! $masterTipeJalurId || ! $programStudiId) {
            return collect();
        }

        $biaya = MasterBiaya::with('items.komponenBiaya')
            ->where('master_tipe_jalur_id', $masterTipeJalurId)
            ->where('program_studi_id', $programStudiId)
            ->where('is_active', true)
            ->first();

        if (! $biaya) {
            return collect();
        }

        return $biaya->items
            ->filter(fn ($item) => (bool) $item->dibebankan_saat_pendaftaran === $saatPendaftaran
                && (float) $item->nominal > 0
                && $item->komponenBiaya)
            ->values();
    }

    /**
     * Susun rincian tagihan (details) dari komponen biaya pada tahap tertentu.
     *
     * @return array<int, array{master_biaya_kode: string, nominal: float, keterangan: string}>
     */
    public function buildDetailTagihan(?int $masterTipeJalurId, ?int $programStudiId, bool $saatPendaftaran): array
    {
        return $this->getKomponenBeban($masterTipeJalurId, $programStudiId, $saatPendaftaran)
            ->map(fn ($item) => [
                'komponen_biaya_id' => $item->komponen_biaya_id,
                'master_biaya_kode' => $item->komponenBiaya->kode,
                'nominal' => (float) $item->nominal,
                'berlaku_diskon' => (bool) $item->berlaku_diskon,
                'keterangan' => $item->komponenBiaya->nama,
            ])
            ->all();
    }

    /**
     * Detail beban awal pendaftaran, dengan fallback ke tarif SIKEU bila
     * komponen pendaftaran belum dikonfigurasi. Parameter `$gelombangId`
     * hanya dipakai untuk fallback tarif (bila konfigurasi master biaya
     * berbasis tipe jalur belum ada).
     */
    public function buildDetailBebanPendaftaran(?int $masterTipeJalurId, ?int $programStudiId, ?int $gelombangId = null): array
    {
        $details = $this->buildDetailTagihan($masterTipeJalurId, $programStudiId, true);
        if (! empty($details)) {
            return $details;
        }

        $gelombang = $gelombangId ? GelombangPenerimaan::find($gelombangId) : null;

        Log::warning('SPMB: Master Biaya pendaftaran tidak ditemukan untuk tipe jalur + prodi; memakai fallback tarif SIKEU.', [
            'master_tipe_jalur_id' => $masterTipeJalurId,
            'program_studi_id' => $programStudiId,
            'gelombang_id' => $gelombangId,
        ]);

        $sikeuService = app(SpmbSikeuService::class);
        $nominal = $sikeuService->getTarifPendaftaranSpmb($gelombang->jalur_masuk_id ?? null, $gelombangId);

        if ($nominal <= 0) {
            throw ValidationException::withMessages([
                'biaya' => 'Biaya pendaftaran belum dikonfigurasi untuk gelombang ini. Hubungi panitia SPMB.',
            ]);
        }

        $masterBiaya = \App\Models\Sikeu\MasterBiaya::where('is_active', true)
            ->where(function ($q) {
                $q->where('kode', 'SPMB_ADM')->orWhere('tipe', 'spmb_adm');
            })
            ->first();

        return [[
            'komponen_biaya_id' => null,
            'master_biaya_kode' => $masterBiaya->kode ?? 'SPMB_ADM',
            'nominal' => $nominal,
            'keterangan' => $masterBiaya->nama ?? 'Biaya Formulir Pendaftaran SPMB',
        ]];
    }

    /**
     * Detail beban daftar ulang, dengan fallback ke tarif UKT SIKEU bila
     * komponen daftar ulang belum dikonfigurasi.
     */
    public function buildDetailBebanDaftarUlang(?int $masterTipeJalurId, ?int $programStudiId): array
    {
        $details = $this->buildDetailTagihan($masterTipeJalurId, $programStudiId, false);
        if (! empty($details)) {
            return $details;
        }

        Log::warning('SPMB: Master Biaya daftar ulang tidak ditemukan untuk tipe jalur + prodi; memakai fallback tarif SIKEU.', [
            'master_tipe_jalur_id' => $masterTipeJalurId,
            'program_studi_id' => $programStudiId,
        ]);

        $biayaDaftarUlang = TarifSpmb::with('masterBiaya')
            ->whereHas('masterBiaya', function ($q) {
                $q->where('kode', 'like', '%UKT%')->orWhere('nama', 'like', '%UKT%');
            })->first();

        $masterBiaya = $biayaDaftarUlang->masterBiaya ?? null;

        return [[
            'komponen_biaya_id' => null,
            'master_biaya_kode' => $masterBiaya->kode ?? 'UKT_SMT1',
            'nominal' => $biayaDaftarUlang->nominal ?? 5000000,
            'keterangan' => 'Biaya UKT Semester 1',
        ]];
    }
}

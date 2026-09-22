<?php

namespace App\Services\Spmb;

use App\Models\Spmb\MasterBiaya;
use App\Models\Spmb\MasterBiayaItem;
use App\Models\Spmb\MasterKomponenBiaya;
use App\Models\Spmb\MasterProgramStudi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

            if ($positionType === 'end' || !$newList->contains(null)) {
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

            unset($data['position_type'], $data['reference_id']);

            return MasterKomponenBiaya::create($data);
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

                if ($positionType === 'end' || !$newList->contains('id', $komponen->id)) {
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

            unset($data['position_type'], $data['reference_id']);

            $komponen->update($data);
            return $komponen->fresh();
        });
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
                    'gelombang_id' => $data['gelombang_id'],
                    'program_studi_id' => $data['program_studi_id'],
                ],
                [
                    'is_active' => $data['is_active'] ?? true,
                    'keterangan' => $data['keterangan'] ?? null,
                ]
            );

            $total = 0;
            if (!empty($data['items'])) {
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
                            'keterangan' => $item['keterangan'] ?? null,
                        ]
                    );
                }
            }

            $masterBiaya->update(['total_biaya' => $total]);
            return $masterBiaya->load(['items.komponenBiaya', 'programStudi', 'gelombang']);
        });
    }

    public function updateBiaya(MasterBiaya $biaya, array $data): MasterBiaya
    {
        return DB::transaction(function () use ($biaya, $data) {
            $biaya->update([
                'gelombang_id' => $data['gelombang_id'] ?? $biaya->gelombang_id,
                'program_studi_id' => $data['program_studi_id'] ?? $biaya->program_studi_id,
                'is_active' => $data['is_active'] ?? $biaya->is_active,
                'keterangan' => $data['keterangan'] ?? $biaya->keterangan,
            ]);

            if (isset($data['items'])) {
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
                            'keterangan' => $item['keterangan'] ?? null,
                        ]
                    );
                }

                $biaya->update(['total_biaya' => $total]);
            } else {
                $biaya->recalculateTotal();
            }

            return $biaya->load(['items.komponenBiaya', 'programStudi', 'gelombang']);
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
     * untuk gelombang + program studi tertentu.
     *
     * @return Collection<int, MasterBiayaItem>
     */
    public function getKomponenBeban(?int $gelombangId, ?int $programStudiId, bool $saatPendaftaran): Collection
    {
        if (!$gelombangId || !$programStudiId) {
            return collect();
        }

        $biaya = MasterBiaya::with('items.komponenBiaya')
            ->where('gelombang_id', $gelombangId)
            ->where('program_studi_id', $programStudiId)
            ->where('is_active', true)
            ->first();

        if (!$biaya) {
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
    public function buildDetailTagihan(?int $gelombangId, ?int $programStudiId, bool $saatPendaftaran): array
    {
        return $this->getKomponenBeban($gelombangId, $programStudiId, $saatPendaftaran)
            ->map(fn ($item) => [
                'master_biaya_kode' => $item->komponenBiaya->kode,
                'nominal' => (float) $item->nominal,
                'keterangan' => $item->komponenBiaya->nama,
            ])
            ->all();
    }

    /**
     * Detail beban awal pendaftaran, dengan fallback ke tarif SIKEU bila
     * komponen pendaftaran belum dikonfigurasi.
     */
    public function buildDetailBebanPendaftaran(?int $gelombangId, ?int $programStudiId): array
    {
        $details = $this->buildDetailTagihan($gelombangId, $programStudiId, true);
        if (!empty($details)) {
            return $details;
        }

        $sikeuService = app(\App\Services\Sikeu\SpmbSikeuService::class);
        $gelombang = \App\Models\Spmb\GelombangPenerimaan::with('masterBiaya')->find($gelombangId);
        $nominal = $sikeuService->getTarifPendaftaranSpmb($gelombang->jalur_masuk_id ?? 1, $gelombang->id ?? 1);
        if ($nominal <= 0) {
            $nominal = ($gelombang && $gelombang->biaya_pendaftaran > 0)
                ? (float) $gelombang->biaya_pendaftaran
                : 250000.00;
        }

        return [[
            'master_biaya_kode' => ($gelombang && $gelombang->masterBiaya) ? $gelombang->masterBiaya->kode : 'SPMB_ADM',
            'nominal' => $nominal,
            'keterangan' => ($gelombang && $gelombang->masterBiaya) ? $gelombang->masterBiaya->nama : 'Biaya Formulir Pendaftaran SPMB',
        ]];
    }

    /**
     * Detail beban daftar ulang, dengan fallback ke tarif UKT SIKEU bila
     * komponen daftar ulang belum dikonfigurasi.
     */
    public function buildDetailBebanDaftarUlang(?int $gelombangId, ?int $programStudiId): array
    {
        $details = $this->buildDetailTagihan($gelombangId, $programStudiId, false);
        if (!empty($details)) {
            return $details;
        }

        $biayaDaftarUlang = \App\Models\Sikeu\TarifSpmb::with('masterBiaya')
            ->whereHas('masterBiaya', function ($q) {
                $q->where('kode', 'like', '%UKT%')->orWhere('nama', 'like', '%UKT%');
            })->first();

        $masterBiaya = $biayaDaftarUlang->masterBiaya ?? null;

        return [[
            'master_biaya_kode' => $masterBiaya->kode ?? 'UKT_SMT1',
            'nominal' => $biayaDaftarUlang->nominal ?? 5000000,
            'keterangan' => 'Biaya UKT Semester 1',
        ]];
    }

    public function batchUpdate(array $data): void
    {
        DB::transaction(function () use ($data) {
            $gelombangId = $data['gelombang_id'];

            foreach ($data['rows'] as $row) {
                $prodiId = $row['program_studi_id'];
                $isActive = $row['is_active'] ?? true;

                $masterBiaya = MasterBiaya::updateOrCreate(
                    [
                        'gelombang_id' => $gelombangId,
                        'program_studi_id' => $prodiId,
                    ],
                    [
                        'is_active' => $isActive,
                    ]
                );

                $total = 0;
                foreach ($row['items'] as $item) {
                    $nom = (float) $item['nominal'];
                    $total += $nom;

                    MasterBiayaItem::updateOrCreate(
                        [
                            'master_biaya_id' => $masterBiaya->id,
                            'komponen_biaya_id' => $item['komponen_biaya_id'],
                        ],
                        [
                            'nominal' => $nom,
                            'dibebankan_saat_pendaftaran' => (bool) ($item['dibebankan_saat_pendaftaran'] ?? false),
                        ]
                    );
                }

                $masterBiaya->update(['total_biaya' => $total]);
            }
        });
    }

    public function copyFromGelombang(array $data): int
    {
        $fromGelombang = $data['from_gelombang_id'];
        $toGelombang = $data['to_gelombang_id'];

        $sourceRecords = MasterBiaya::with('items')
            ->where('gelombang_id', $fromGelombang)
            ->get();

        if ($sourceRecords->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($sourceRecords, $toGelombang) {
            foreach ($sourceRecords as $src) {
                $target = MasterBiaya::updateOrCreate(
                    [
                        'gelombang_id' => $toGelombang,
                        'program_studi_id' => $src->program_studi_id,
                    ],
                    [
                        'total_biaya' => $src->total_biaya,
                        'is_active' => $src->is_active,
                        'keterangan' => $src->keterangan,
                    ]
                );

                foreach ($src->items as $srcItem) {
                    MasterBiayaItem::updateOrCreate(
                        [
                            'master_biaya_id' => $target->id,
                            'komponen_biaya_id' => $srcItem->komponen_biaya_id,
                        ],
                        [
                            'nominal' => $srcItem->nominal,
                            'dibebankan_saat_pendaftaran' => (bool) $srcItem->dibebankan_saat_pendaftaran,
                            'keterangan' => $srcItem->keterangan,
                        ]
                    );
                }
            }
        });

        return $sourceRecords->count();
    }
}

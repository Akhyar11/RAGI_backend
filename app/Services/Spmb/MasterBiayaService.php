<?php

namespace App\Services\Spmb;

use App\Models\Spmb\MasterBiaya;
use App\Models\Spmb\MasterBiayaItem;
use App\Models\Spmb\MasterKomponenBiaya;
use App\Models\Sikeu\PaymentGatewayConfig;
use App\Services\Storage\FileStorageService;
use Illuminate\Support\Collection;
use App\Models\Spmb\JalurMasuk;
use App\Models\Siakad\ProgramStudi;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

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
            $this->resolvePaymentGatewayMapping($data);

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

            $this->resolvePaymentGatewayMapping($data);
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

    private function resolvePaymentGatewayMapping(array &$data): void
    {
        if (! array_key_exists('payment_gateway_name', $data) && ! array_key_exists('payment_gateway_config_id', $data)) {
            return;
        }

        $gatewayName = $data['payment_gateway_name'] ?? null;
        $gatewayId = $data['payment_gateway_config_id'] ?? null;

        if ($gatewayName === null && $gatewayId) {
            $gatewayName = PaymentGatewayConfig::find($gatewayId)?->gateway_name;
        }

        $data['payment_gateway_name'] = $gatewayName;
        $data['payment_gateway_config_id'] = $gatewayName
            ? PaymentGatewayConfig::query()->where('gateway_name', $gatewayName)->where('is_active', true)->value('id')
            : null;
    }

    public function deleteKomponen(MasterKomponenBiaya $komponen): bool
    {
        return (bool) $komponen->delete();
    }

    public function restoreKomponen(MasterKomponenBiaya $komponen): bool
    {
        return (bool) $komponen->restore();
    }

    /**
     * Hapus berkas evidence hanya bila tidak lagi dirujuk master biaya lain (berkas dapat dipakai
     * bersama oleh baris yang dibuat dalam satu permintaan banyak kombinasi).
     */
    private function deleteEvidenceIfUnused(?string $path, int $exceptId): void
    {
        if (! $path) {
            return;
        }

        $stillUsed = MasterBiaya::withTrashed()
            ->where('dokumen_pendukung_path', $path)
            ->where('id', '!=', $exceptId)
            ->exists();

        if (! $stillUsed) {
            app(FileStorageService::class)->delete($path, private: true);
        }
    }

    public function createBiaya(array $data): MasterBiaya
    {
        $file = $data['dokumen_pendukung'] ?? null;
        $evidenceType = $data['evidence_type'];
        unset($data['dokumen_pendukung'], $data['evidence_type']);
        $uploadedPath = $file ? app(FileStorageService::class)->store($file, 'spmb/master-biaya', private: true) : null;
        $oldPath = null;
        $oldOwnerId = null;

        try {
            $created = DB::transaction(function () use ($data, $evidenceType, $uploadedPath, &$oldPath, &$oldOwnerId) {
                $existing = MasterBiaya::query()
                    ->where('jalur_masuk_id', $data['jalur_masuk_id'])
                    ->where('program_studi_id', $data['program_studi_id'])
                    ->first();
                $oldPath = $existing?->dokumen_pendukung_path;
                $oldOwnerId = $existing?->id;

                $masterBiaya = MasterBiaya::updateOrCreate(
                    [
                        'jalur_masuk_id' => $data['jalur_masuk_id'],
                        'program_studi_id' => $data['program_studi_id'],
                    ],
                    [
                        'nomor_sk' => $evidenceType === 'arsip' ? $data['nomor_sk'] : null,
                        'dokumen_pendukung_path' => $evidenceType === 'upload' ? $uploadedPath : null,
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

                return $masterBiaya->load(['items.komponenBiaya', 'programStudi', 'jalurMasuk', 'arsipNomorSurat']);
            });

            if ($oldPath && $oldPath !== $uploadedPath) {
                $this->deleteEvidenceIfUnused($oldPath, (int) $oldOwnerId);
            }

            return $created;
        } catch (Throwable $exception) {
            if ($uploadedPath) {
                app(FileStorageService::class)->delete($uploadedPath, private: true);
            }

            throw $exception;
        }
    }

    /**
     * Buat master biaya untuk banyak kombinasi jalur × program studi (satu baris per kombinasi).
     * Kombinasi yang sudah ada (termasuk yang dihapus lunak) dilewati, tidak ditimpa.
     *
     * @return array{dibuat: \Illuminate\Support\Collection<int, MasterBiaya>, dilewati: list<array<string,mixed>>}
     */
    public function createBiayaBulk(array $data, ?Request $request = null): array
    {
        $jalurIds = $this->collectIds($data, 'jalur_masuk_id', 'jalur_masuk_ids');
        $prodiIds = $this->collectIds($data, 'program_studi_id', 'program_studi_ids');
        $evidenceType = $data['evidence_type'];
        $file = $data['dokumen_pendukung'] ?? null;

        // Unique index (jalur, prodi) mencakup baris terhapus, jadi periksa dengan withTrashed.
        $existing = MasterBiaya::withTrashed()
            ->whereIn('jalur_masuk_id', $jalurIds)
            ->whereIn('program_studi_id', $prodiIds)
            ->get(['id', 'jalur_masuk_id', 'program_studi_id', 'deleted_at'])
            ->keyBy(fn ($row) => $row->jalur_masuk_id.'-'.$row->program_studi_id);

        $jalurNames = JalurMasuk::whereIn('id', $jalurIds)->pluck('nama', 'id');
        $prodiNames = ProgramStudi::whereIn('id', $prodiIds)->get(['id', 'nama', 'jenjang'])
            ->mapWithKeys(fn ($p) => [$p->id => trim(($p->jenjang ? "[{$p->jenjang}] " : '').$p->nama)]);

        $combinations = [];
        $skipped = [];
        foreach ($jalurIds as $jalurId) {
            foreach ($prodiIds as $prodiId) {
                $found = $existing->get($jalurId.'-'.$prodiId);
                if ($found) {
                    $skipped[] = [
                        'jalur_masuk_id' => $jalurId,
                        'program_studi_id' => $prodiId,
                        'jalur' => $jalurNames[$jalurId] ?? null,
                        'program_studi' => $prodiNames[$prodiId] ?? null,
                        'alasan' => $found->deleted_at ? 'terhapus' : 'sudah_ada',
                    ];

                    continue;
                }
                $combinations[] = [$jalurId, $prodiId];
            }
        }

        if ($combinations === []) {
            throw ValidationException::withMessages([
                'program_studi_ids' => 'Semua kombinasi jalur dan program studi yang dipilih sudah memiliki master biaya.',
            ]);
        }

        $uploadedPath = $file ? app(FileStorageService::class)->store($file, 'spmb/master-biaya', private: true) : null;

        try {
            $created = DB::transaction(function () use ($combinations, $data, $evidenceType, $uploadedPath) {
                $rows = collect();
                foreach ($combinations as [$jalurId, $prodiId]) {
                    $biaya = MasterBiaya::create([
                        'jalur_masuk_id' => $jalurId,
                        'program_studi_id' => $prodiId,
                        'nomor_sk' => $evidenceType === 'arsip' ? $data['nomor_sk'] : null,
                        'dokumen_pendukung_path' => $evidenceType === 'upload' ? $uploadedPath : null,
                        'is_active' => $data['is_active'] ?? true,
                        'keterangan' => $data['keterangan'] ?? null,
                    ]);

                    $total = 0;
                    foreach ($data['items'] ?? [] as $item) {
                        $nominal = (float) $item['nominal'];
                        $total += $nominal;
                        MasterBiayaItem::create([
                            'master_biaya_id' => $biaya->id,
                            'komponen_biaya_id' => $item['komponen_biaya_id'],
                            'nominal' => $nominal,
                            'dibebankan_saat_pendaftaran' => (bool) ($item['dibebankan_saat_pendaftaran'] ?? false),
                            'berlaku_diskon' => (bool) ($item['berlaku_diskon'] ?? false),
                            'keterangan' => $item['keterangan'] ?? null,
                        ]);
                    }
                    $biaya->update(['total_biaya' => $total]);
                    $rows->push($biaya);
                }

                return $rows;
            });
        } catch (Throwable $exception) {
            if ($uploadedPath) {
                app(FileStorageService::class)->delete($uploadedPath, private: true);
            }

            throw $exception;
        }

        $created->each->load(['items.komponenBiaya', 'programStudi', 'jalurMasuk', 'arsipNomorSurat']);

        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'master_biaya_dibuat_massal',
                tableName: (new MasterBiaya)->getTable(),
                newValues: [
                    'dibuat' => $created->pluck('id')->all(),
                    'dilewati' => count($skipped),
                    'jalur_masuk_ids' => $jalurIds,
                    'program_studi_ids' => $prodiIds,
                ],
                request: $request,
            );
        } catch (Throwable $exception) {
            Log::warning('Gagal mencatat audit log master biaya massal: '.$exception->getMessage());
        }

        return ['dibuat' => $created, 'dilewati' => $skipped];
    }

    /** @return list<int> */
    private function collectIds(array $data, string $singleKey, string $manyKey): array
    {
        $ids = array_map('intval', $data[$manyKey] ?? []);
        if (! empty($data[$singleKey])) {
            $ids[] = (int) $data[$singleKey];
        }

        return array_values(array_unique($ids));
    }

    public function updateBiaya(MasterBiaya $biaya, array $data): MasterBiaya
    {
        $file = $data['dokumen_pendukung'] ?? null;
        $evidenceType = $data['evidence_type'] ?? null;
        unset($data['dokumen_pendukung'], $data['evidence_type']);
        $uploadedPath = $file ? app(FileStorageService::class)->store($file, 'spmb/master-biaya', private: true) : null;
        $oldPath = null;

        try {
            $updated = DB::transaction(function () use ($biaya, $data, $evidenceType, $uploadedPath, &$oldPath) {
                $changes = [
                'jalur_masuk_id' => $data['jalur_masuk_id'] ?? $biaya->jalur_masuk_id,
                'program_studi_id' => $data['program_studi_id'] ?? $biaya->program_studi_id,
                'is_active' => $data['is_active'] ?? $biaya->is_active,
                'keterangan' => $data['keterangan'] ?? $biaya->keterangan,
                ];

                if ($evidenceType === 'arsip') {
                    $changes['nomor_sk'] = $data['nomor_sk'];
                    $changes['dokumen_pendukung_path'] = null;
                    $oldPath = $biaya->dokumen_pendukung_path;
                } elseif ($evidenceType === 'upload') {
                    $changes['nomor_sk'] = null;
                    if ($uploadedPath) {
                        $changes['dokumen_pendukung_path'] = $uploadedPath;
                        $oldPath = $biaya->dokumen_pendukung_path;
                    }
                } elseif (array_key_exists('nomor_sk', $data)) {
                    $changes['nomor_sk'] = $data['nomor_sk'];
                }

                $biaya->update($changes);

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

                return $biaya->load(['items.komponenBiaya', 'programStudi', 'jalurMasuk', 'arsipNomorSurat']);
            });

            if ($oldPath) {
                $this->deleteEvidenceIfUnused($oldPath, (int) $biaya->id);
            }

            return $updated;
        } catch (Throwable $exception) {
            if ($uploadedPath) {
                app(FileStorageService::class)->delete($uploadedPath, private: true);
            }

            throw $exception;
        }
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
    * untuk jalur masuk + program studi tertentu.
     *
     * @return Collection<int, MasterBiayaItem>
     */
    public function getKomponenBeban(?int $jalurMasukId, ?int $programStudiId, bool $saatPendaftaran): Collection
    {
        if (! $jalurMasukId || ! $programStudiId) {
            return collect();
        }

        $biaya = MasterBiaya::with('items.komponenBiaya')
            ->where('jalur_masuk_id', $jalurMasukId)
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
    public function buildDetailTagihan(?int $jalurMasukId, ?int $programStudiId, bool $saatPendaftaran): array
    {
        return $this->getKomponenBeban($jalurMasukId, $programStudiId, $saatPendaftaran)
            ->map(fn ($item) => [
                'komponen_biaya_id' => $item->komponen_biaya_id,
                'payment_gateway_config_id' => $item->komponenBiaya->payment_gateway_config_id,
                'payment_gateway_name' => $item->komponenBiaya->payment_gateway_name,
                'master_biaya_kode' => $item->komponenBiaya->kode,
                'nominal' => (float) $item->nominal,
                'berlaku_diskon' => (bool) $item->berlaku_diskon,
                'keterangan' => $item->komponenBiaya->nama,
            ])
            ->all();
    }

    /** Detail biaya pendaftaran hanya dari komponen Master Biaya SPMB. */
    public function buildDetailBebanPendaftaran(?int $jalurMasukId, ?int $programStudiId, ?int $gelombangId = null): array
    {
        $details = $this->buildDetailTagihan($jalurMasukId, $programStudiId, true);
        if (! empty($details)) {
            return $details;
        }

        Log::warning('SPMB: Komponen Master Biaya pendaftaran belum dikonfigurasi.', [
            'jalur_masuk_id' => $jalurMasukId,
            'program_studi_id' => $programStudiId,
            'gelombang_id' => $gelombangId,
        ]);

        throw ValidationException::withMessages([
            'biaya' => 'Komponen biaya pendaftaran belum dikonfigurasi di Master Biaya SPMB untuk jalur masuk dan program studi ini.',
        ]);
    }

    /** Detail biaya daftar ulang hanya dari komponen Master Biaya SPMB. */
    public function buildDetailBebanDaftarUlang(?int $jalurMasukId, ?int $programStudiId): array
    {
        $details = $this->buildDetailTagihan($jalurMasukId, $programStudiId, false);
        if (! empty($details)) {
            return $details;
        }

        Log::warning('SPMB: Komponen Master Biaya daftar ulang belum dikonfigurasi.', [
            'jalur_masuk_id' => $jalurMasukId,
            'program_studi_id' => $programStudiId,
        ]);

        throw ValidationException::withMessages([
            'biaya' => 'Komponen biaya daftar ulang belum dikonfigurasi di Master Biaya SPMB untuk jalur masuk dan program studi ini.',
        ]);
    }
}

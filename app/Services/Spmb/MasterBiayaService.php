<?php

namespace App\Services\Spmb;

use App\Models\Spmb\MasterBiaya;
use App\Models\Spmb\MasterBiayaItem;
use App\Models\Spmb\MasterKomponenBiaya;
use App\Models\Sikeu\PaymentGatewayConfig;
use App\Services\Storage\FileStorageService;
use Illuminate\Support\Collection;
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

    public function createBiaya(array $data): MasterBiaya
    {
        $file = $data['dokumen_pendukung'] ?? null;
        $evidenceType = $data['evidence_type'];
        unset($data['dokumen_pendukung'], $data['evidence_type']);
        $uploadedPath = $file ? app(FileStorageService::class)->store($file, 'spmb/master-biaya', private: true) : null;
        $oldPath = null;

        try {
            $created = DB::transaction(function () use ($data, $evidenceType, $uploadedPath, &$oldPath) {
                $existing = MasterBiaya::query()
                    ->where('master_tipe_jalur_id', $data['master_tipe_jalur_id'])
                    ->where('program_studi_id', $data['program_studi_id'])
                    ->first();
                $oldPath = $existing?->dokumen_pendukung_path;

                $masterBiaya = MasterBiaya::updateOrCreate(
                    [
                        'master_tipe_jalur_id' => $data['master_tipe_jalur_id'],
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

                return $masterBiaya->load(['items.komponenBiaya', 'programStudi', 'masterTipeJalur', 'arsipNomorSurat']);
            });

            if ($oldPath && $oldPath !== $uploadedPath) {
                app(FileStorageService::class)->delete($oldPath, private: true);
            }

            return $created;
        } catch (Throwable $exception) {
            if ($uploadedPath) {
                app(FileStorageService::class)->delete($uploadedPath, private: true);
            }

            throw $exception;
        }
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
                'master_tipe_jalur_id' => $data['master_tipe_jalur_id'] ?? $biaya->master_tipe_jalur_id,
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

                return $biaya->load(['items.komponenBiaya', 'programStudi', 'masterTipeJalur', 'arsipNomorSurat']);
            });

            if ($oldPath) {
                app(FileStorageService::class)->delete($oldPath, private: true);
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
    public function buildDetailBebanPendaftaran(?int $masterTipeJalurId, ?int $programStudiId, ?int $gelombangId = null): array
    {
        $details = $this->buildDetailTagihan($masterTipeJalurId, $programStudiId, true);
        if (! empty($details)) {
            return $details;
        }

        Log::warning('SPMB: Komponen Master Biaya pendaftaran belum dikonfigurasi.', [
            'master_tipe_jalur_id' => $masterTipeJalurId,
            'program_studi_id' => $programStudiId,
            'gelombang_id' => $gelombangId,
        ]);

        throw ValidationException::withMessages([
            'biaya' => 'Komponen biaya pendaftaran belum dikonfigurasi di Master Biaya SPMB untuk tipe jalur dan program studi ini.',
        ]);
    }

    /** Detail biaya daftar ulang hanya dari komponen Master Biaya SPMB. */
    public function buildDetailBebanDaftarUlang(?int $masterTipeJalurId, ?int $programStudiId): array
    {
        $details = $this->buildDetailTagihan($masterTipeJalurId, $programStudiId, false);
        if (! empty($details)) {
            return $details;
        }

        Log::warning('SPMB: Komponen Master Biaya daftar ulang belum dikonfigurasi.', [
            'master_tipe_jalur_id' => $masterTipeJalurId,
            'program_studi_id' => $programStudiId,
        ]);

        throw ValidationException::withMessages([
            'biaya' => 'Komponen biaya daftar ulang belum dikonfigurasi di Master Biaya SPMB untuk tipe jalur dan program studi ini.',
        ]);
    }
}

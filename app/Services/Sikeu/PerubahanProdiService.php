<?php

namespace App\Services\Sikeu;

use App\Models\Siakad\Mahasiswa;
use App\Models\Sikeu\MahasiswaTipeTagihan;
use App\Models\Sikeu\PerubahanProdi;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Perpindahan program studi / perubahan jenis pendaftaran.
 *
 * Tagihan lama tidak disentuh. Perubahan hanya menentukan konteks
 * efektif (TA + semester) untuk penagihan berikutnya.
 */
class PerubahanProdiService
{
    private function catatAudit(string $action, string $table, ?int $recordId, ?array $old = null, ?array $new = null): void
    {
        try {
            AuditLogService::record(
                module: 'SIKEU',
                action: $action,
                tableName: $table,
                recordId: $recordId,
                oldValues: $old,
                newValues: $new,
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log perubahan prodi: ' . $e->getMessage());
        }
    }

    public function dampak(int $mahasiswaId): array
    {
        $mhs = Mahasiswa::with('programStudi')->find($mahasiswaId);

        $tagihanAktif = TagihanMahasiswa::where('mahasiswa_id', $mahasiswaId)
            ->whereIn('status', ['belum_bayar', 'sebagian', 'dispensasi', 'pending_approval'])
            ->orderBy('id')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'nomor_tagihan' => $t->nomor_tagihan,
                'semester' => $t->semester,
                'total_tagihan' => (float) $t->total_tagihan,
                'total_bayar' => (float) $t->total_bayar,
                'sisa' => max(0, (float) $t->total_tagihan + (float) ($t->total_denda ?? 0) - (float) $t->total_potongan - (float) $t->total_bayar),
                'status' => $t->status,
            ])->values()->all();

        return [
            'mahasiswa' => $mhs ? [
                'id' => $mhs->id,
                'nim' => $mhs->nim,
                'nama' => $mhs->nama_lengkap,
                'program_studi_id' => $mhs->program_studi_id,
                'prodi' => $mhs->programStudi?->nama ?? '-',
            ] : null,
            'tagihan_aktif' => $tagihanAktif,
            'total_sisa_aktif' => array_sum(array_column($tagihanAktif, 'sisa')),
        ];
    }

    public function createRequest(int $actorId, array $data): PerubahanProdi
    {
        return DB::transaction(function () use ($actorId, $data) {
            $mhs = Mahasiswa::findOrFail((int) $data['mahasiswa_id']);

            $row = PerubahanProdi::create([
                'mahasiswa_id' => $mhs->id,
                'old_program_studi_id' => $mhs->program_studi_id,
                'new_program_studi_id' => (int) $data['new_program_studi_id'],
                'tahun_akademik_id' => $data['tahun_akademik_id'] ?? null,
                'semester_efektif' => $data['semester_efektif'] ?? null,
                'tanggal_efektif' => $data['tanggal_efektif'] ?? null,
                'jenis_pendaftaran_lama' => $data['jenis_pendaftaran_lama'] ?? null,
                'jenis_pendaftaran_baru' => $data['jenis_pendaftaran_baru'] ?? null,
                'alasan' => $data['alasan'],
                'referensi_surat' => $data['referensi_surat'] ?? null,
                'dokumen_path' => $data['dokumen_path'] ?? null,
                'status' => 'pending',
                'requested_by' => $actorId,
            ]);
            $this->catatAudit('create', 'sikeu_mahasiswa_perubahan_prodi', $row->id, null, $row->getChanges());

            return $row;
        });
    }

    public function approve(PerubahanProdi $item, int $actorId, ?string $catatan = null): PerubahanProdi
    {
        if ($item->status !== 'pending') {
            throw new \RuntimeException('Hanya pengajuan pending yang bisa disetujui.');
        }
        $oldApprove = $item->getOriginal();
        $item->update([
            'status' => 'approved',
            'approved_by' => $actorId,
            'approved_at' => now(),
            'catatan_approval' => $catatan ?? $item->catatan_approval,
        ]);
        $this->catatAudit('approve', 'sikeu_mahasiswa_perubahan_prodi', $item->id, $oldApprove, $item->getChanges());

        return $item->fresh();
    }

    public function reject(PerubahanProdi $item, int $actorId, ?string $catatan = null): PerubahanProdi
    {
        if (!in_array($item->status, ['pending', 'approved'], true)) {
            throw new \RuntimeException('Hanya pengajuan pending/approved yang bisa ditolak.');
        }
        $oldReject = $item->getOriginal();
        $item->update([
            'status' => 'rejected',
            'catatan_approval' => $catatan ?? $item->catatan_approval,
        ]);
        $this->catatAudit('reject', 'sikeu_mahasiswa_perubahan_prodi', $item->id, $oldReject, $item->getChanges());

        return $item->fresh();
    }

    /**
     * Terapkan: update SIAKAD prodi + versioning tipe tagihan SIKEU.
     */
    public function apply(PerubahanProdi $item, int $actorId): PerubahanProdi
    {
        if ($item->status !== 'approved') {
            throw new \RuntimeException('Hanya pengajuan yang sudah disetujui yang bisa diterapkan.');
        }

        return DB::transaction(function () use ($item, $actorId) {
            $fresh = PerubahanProdi::where('id', $item->id)->lockForUpdate()->firstOrFail();
            if ($fresh->status !== 'approved') {
                throw new \RuntimeException('Pengajuan sudah diproses sebelumnya.');
            }

            $mhs = Mahasiswa::where('id', $fresh->mahasiswa_id)->lockForUpdate()->firstOrFail();
            $oldProdiDarurat = $mhs->program_studi_id;

            if (Schema::hasColumn('siakad_mahasiswa', 'program_studi_id')) {
                $mhs->update(['program_studi_id' => $fresh->new_program_studi_id]);
            }

            try {
                AuditLogService::record(
                    module: 'SIKEU',
                    action: 'update',
                    tableName: 'siakad_mahasiswa',
                    recordId: $mhs->id,
                    oldValues: ['program_studi_id' => $oldProdiDarurat],
                    newValues: ['program_studi_id' => $fresh->new_program_studi_id],
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log mutasi prodi (SIKEU darurat): ' . $e->getMessage());
            }

            $tipe = MahasiswaTipeTagihan::where('mahasiswa_id', $mhs->id)->first();
            if ($tipe) {
                $oldVersion = (int) ($tipe->version ?? 1);
                $tipe->update([
                    'status_pendaftaran' => $fresh->jenis_pendaftaran_baru ?? $tipe->status_pendaftaran,
                    'catatan_perubahan' => trim(($tipe->catatan_perubahan ? $tipe->catatan_perubahan . ' | ' : '') . "Pindah prodi ke {$fresh->new_program_studi_id} efektif TA {$fresh->tahun_akademik_id}/Smt {$fresh->semester_efektif} ({$fresh->referensi_surat})"),
                    'is_active' => true,
                    'version' => $oldVersion + 1,
                    'effective_tahun_akademik_id' => $fresh->tahun_akademik_id,
                    'effective_semester' => $fresh->semester_efektif,
                    'perubahan_prodi_id' => $fresh->id,
                    'updated_by' => $actorId,
                ]);

                try {
                    AuditLogService::record(
                        module: 'SIKEU',
                        action: 'update',
                        tableName: 'sikeu_mahasiswa_tipe_tagihan',
                        recordId: $tipe->id,
                        oldValues: ['version' => $oldVersion],
                        newValues: ['version' => $oldVersion + 1, 'perubahan_prodi_id' => $fresh->id],
                    );
                } catch (\Throwable $e) {
                    Log::warning('Gagal mencatat audit log versioning tipe tagihan: ' . $e->getMessage());
                }
            }

            $oldApplyRow = $fresh->getOriginal();
            $fresh->update([
                'status' => 'applied',
                'applied_by' => $actorId,
                'applied_at' => now(),
            ]);
            $this->catatAudit('approve', 'sikeu_mahasiswa_perubahan_prodi', $fresh->id, $oldApplyRow, $fresh->getChanges());

            return $fresh->fresh();
        });
    }
}

<?php

namespace App\Services\Siakad;

use App\Events\Siakad\MahasiswaPindahProdi;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\PindahProdi;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PindahProdiService
{
    private function catatAudit(string $action, string $table, ?int $recordId, ?array $old = null, ?array $new = null): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: $action,
                tableName: $table,
                recordId: $recordId,
                oldValues: $old,
                newValues: $new,
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log pindah prodi: ' . $e->getMessage());
        }
    }

    /**
     * Pemilik mutasi prodi: SIAKAD.
     * Approval akademik mensyaratkan sign-off keuangan lebih dulu
     * (hasil_cek_keuangan wajib terisi) agar tunggakan tertangani
     * sebelum mahasiswa resmi tercatat di prodi tujuan.
     */
    public function createRequest(int $actorId, array $data): PindahProdi
    {
        return DB::transaction(function () use ($actorId, $data) {
            $mhs = Mahasiswa::findOrFail((int) $data['mahasiswa_id']);

            if ((int) $mhs->program_studi_id === (int) $data['new_program_studi_id']) {
                throw new \InvalidArgumentException('Program studi tujuan sama dengan prodi mahasiswa saat ini.');
            }

            $row = PindahProdi::create([
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
            $this->catatAudit('create', 'siakad_mahasiswa_pindah_prodi', $row->id, null, $row->getChanges());

            return $row;
        });
    }

    /**
     * Sign-off keuangan: catat hasil cek tunggakan SIKEU.
     */
    public function cekKeuangan(PindahProdi $item, int $actorId, string $hasil, ?string $catatan = null): PindahProdi
    {
        if (!in_array($hasil, PindahProdi::HASIL_CEK_KEUANGAN, true)) {
            throw new \InvalidArgumentException('Hasil cek keuangan harus layak atau ada_tunggakan.');
        }
        if ($item->status !== 'pending') {
            throw new \RuntimeException('Hanya pengajuan pending yang bisa dicek keuangan.');
        }

        $oldCek = $item->getOriginal();
        $item->update([
            'dicek_keuangan_oleh' => $actorId,
            'dicek_keuangan_pada' => now(),
            'hasil_cek_keuangan' => $hasil,
            'catatan_keuangan' => $catatan,
        ]);
        $this->catatAudit('update', 'siakad_mahasiswa_pindah_prodi', $item->id, $oldCek, $item->getChanges());

        return $item->fresh();
    }

    public function approve(PindahProdi $item, int $actorId, ?string $catatan = null): PindahProdi
    {
        if ($item->status !== 'pending') {
            throw new \RuntimeException('Hanya pengajuan pending yang bisa disetujui.');
        }
        if ($item->hasil_cek_keuangan === null) {
            throw new \RuntimeException('Approval akademik mensyaratkan sign-off keuangan terlebih dahulu.');
        }

        $oldApprove = $item->getOriginal();
        $item->update([
            'status' => 'approved',
            'approved_by' => $actorId,
            'approved_at' => now(),
            'catatan_approval' => $catatan ?? $item->catatan_approval,
        ]);
        $this->catatAudit('approve', 'siakad_mahasiswa_pindah_prodi', $item->id, $oldApprove, $item->getChanges());

        return $item->fresh();
    }

    public function reject(PindahProdi $item, int $actorId, ?string $catatan = null): PindahProdi
    {
        if (!in_array($item->status, ['pending', 'approved'], true)) {
            throw new \RuntimeException('Hanya pengajuan pending/approved yang bisa ditolak.');
        }

        $oldReject = $item->getOriginal();
        $item->update([
            'status' => 'rejected',
            'catatan_approval' => $catatan ?? $item->catatan_approval,
        ]);
        $this->catatAudit('reject', 'siakad_mahasiswa_pindah_prodi', $item->id, $oldReject, $item->getChanges());

        return $item->fresh();
    }

    /**
     * Terapkan mutasi + dispatch event untuk SIKEU/SPMB.
     */
    public function apply(PindahProdi $item, int $actorId): PindahProdi
    {
        if ($item->status !== 'approved') {
            throw new \RuntimeException('Hanya pengajuan yang sudah disetujui yang bisa diterapkan.');
        }

        $hasil = DB::transaction(function () use ($item, $actorId) {
            $fresh = PindahProdi::where('id', $item->id)->lockForUpdate()->firstOrFail();
            if ($fresh->status !== 'approved') {
                throw new \RuntimeException('Pengajuan sudah diproses sebelumnya.');
            }
            $oldApplyRow = $fresh->getOriginal();

            $mhs = Mahasiswa::where('id', $fresh->mahasiswa_id)->lockForUpdate()->firstOrFail();
            $oldProdi = $mhs->program_studi_id;
            $mhs->update(['program_studi_id' => $fresh->new_program_studi_id]);

            try {
                AuditLogService::record(
                    module: 'SIAKAD',
                    action: 'update',
                    tableName: 'siakad_mahasiswa',
                    recordId: $mhs->id,
                    oldValues: ['program_studi_id' => $oldProdi],
                    newValues: ['program_studi_id' => $fresh->new_program_studi_id],
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log mutasi prodi: ' . $e->getMessage());
            }

            $fresh->update([
                'status' => 'applied',
                'applied_by' => $actorId,
                'applied_at' => now(),
            ]);
            $this->catatAudit('approve', 'siakad_mahasiswa_pindah_prodi', $fresh->id, $oldApplyRow, $fresh->getChanges());

            return $fresh->fresh();
        });

        MahasiswaPindahProdi::dispatch(
            $hasil->id,
            $hasil->mahasiswa_id,
            $hasil->old_program_studi_id,
            $hasil->new_program_studi_id,
            $hasil->tahun_akademik_id,
            $hasil->semester_efektif,
            $hasil->jenis_pendaftaran_baru,
            $hasil->referensi_surat,
            $actorId,
        );

        return $hasil;
    }
}

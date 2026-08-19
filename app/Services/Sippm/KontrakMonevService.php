<?php

namespace App\Services\Sippm;

use App\Models\Sippm\ProposalKegiatan;
use App\Models\Sippm\KontrakKegiatan;
use App\Models\Sippm\PencairanDanaHibah;
use App\Models\Sippm\LaporanKegiatan;
use Illuminate\Support\Facades\DB;

class KontrakMonevService
{
    /**
     * Create SPK Kontrak for accepted proposal.
     */
    public function createKontrak(ProposalKegiatan $proposal, array $data): KontrakKegiatan
    {
        return DB::transaction(function () use ($proposal, $data) {
            $year = date('Y');
            $count = KontrakKegiatan::whereYear('created_at', $year)->count() + 1;
            $nomorKontrak = 'SPK/LPPM/' . $year . '/' . str_pad($count, 3, '0', STR_PAD_LEFT);

            $kontrak = KontrakKegiatan::create([
                'proposal_id' => $proposal->id,
                'nomor_kontrak' => $data['nomor_kontrak'] ?? $nomorKontrak,
                'dana_disetujui' => $data['dana_disetujui'] ?? $proposal->anggaran_disetujui,
                'tgl_mulai' => $data['tgl_mulai'],
                'tgl_selesai' => $data['tgl_selesai'],
                'file_kontrak' => $data['file_kontrak'] ?? ('spk_draft_' . $proposal->id . '.pdf'),
                'status' => 'aktif',
            ]);

            $proposal->update(['status' => 'berjalan']);

            return $kontrak->load(['proposal', 'pencairanDana', 'laporan']);
        });
    }

    /**
     * Request funding disbursement (Termin).
     */
    public function requestPencairan(KontrakKegiatan $kontrak, int $terminKe, float $persenPencairan): PencairanDanaHibah
    {
        $nominal = ($kontrak->dana_disetujui * $persenPencairan) / 100;

        return PencairanDanaHibah::create([
            'kontrak_id' => $kontrak->id,
            'termin_ke' => $terminKe,
            'persen_pencairan' => $persenPencairan,
            'nominal' => $nominal,
            'status' => 'pengajuan',
        ]);
    }

    /**
     * Submit Laporan Kemajuan or Laporan Akhir.
     */
    public function submitLaporan(KontrakKegiatan $kontrak, string $jenisLaporan, array $data): LaporanKegiatan
    {
        return LaporanKegiatan::updateOrCreate(
            ['kontrak_id' => $kontrak->id, 'jenis_laporan' => $jenisLaporan],
            [
                'file_laporan' => $data['file_laporan'],
                'file_logbook' => $data['file_logbook'] ?? null,
                'file_penggunaan_anggaran' => $data['file_penggunaan_anggaran'] ?? null,
                'persentase_capaian' => $data['persentase_capaian'] ?? 0,
                'status_verifikasi' => 'diajukan',
                'submitted_at' => now(),
            ]
        );
    }

    /**
     * Upload signed SPK (tanda tangan basah) by Ketua Pengusul.
     */
    public function uploadSpkTtdBasah(KontrakKegiatan $kontrak, string $filePath): KontrakKegiatan
    {
        return DB::transaction(function () use ($kontrak, $filePath) {
            $kontrak->update([
                'file_spk_ttd' => $filePath,
                'status_spk' => 'uploaded',
            ]);

            // Ensure Termin 1 pencairan row exists with status_termin = 'waiting_document'
            PencairanDanaHibah::updateOrCreate(
                ['kontrak_id' => $kontrak->id, 'termin_ke' => 1],
                [
                    'persen_pencairan' => 70.00,
                    'nominal' => ($kontrak->dana_disetujui * 0.70),
                    'status' => 'pengajuan',
                    'status_termin' => 'waiting_document',
                ]
            );

            \App\Services\AuditLogService::record(
                module: 'SIPPM',
                action: 'update',
                tableName: 'kontrak_kegiatan',
                recordId: $kontrak->id,
                newValues: ['file_spk_ttd' => $filePath, 'status_spk' => 'uploaded']
            );

            return $kontrak->fresh(['proposal', 'pencairanDana', 'laporan']);
        });
    }

    /**
     * Approve SPK document by Admin SIPPM -> Moves Termin 1 to waiting_disburse.
     */
    public function approveSpkDokumen(KontrakKegiatan $kontrak, ?string $catatan = null): PencairanDanaHibah
    {
        return DB::transaction(function () use ($kontrak, $catatan) {
            $kontrak->update(['status_spk' => 'approved']);

            $pencairan = PencairanDanaHibah::updateOrCreate(
                ['kontrak_id' => $kontrak->id, 'termin_ke' => 1],
                [
                    'persen_pencairan' => 70.00,
                    'nominal' => ($kontrak->dana_disetujui * 0.70),
                    'status' => 'disetujui',
                    'status_termin' => 'waiting_disburse',
                    'catatan_verifikasi' => $catatan ?? 'Dokumen SPK resmi bertanda tangan basah telah diverifikasi & disetujui Admin SIPPM.',
                ]
            );

            \App\Services\AuditLogService::record(
                module: 'SIPPM',
                action: 'approve',
                tableName: 'pencairan_dana_hibah',
                recordId: $pencairan->id,
                newValues: ['status_termin' => 'waiting_disburse', 'catatan' => $catatan]
            );

            return $pencairan;
        });
    }

    /**
     * Upload transfer receipt by Admin SIKEU -> Moves Termin 1 to already_disburse.
     */
    public function uploadResiSikeu(int $pencairanId, string $buktiTransferPath): PencairanDanaHibah
    {
        return DB::transaction(function () use ($pencairanId, $buktiTransferPath) {
            $pencairan = PencairanDanaHibah::findOrFail($pencairanId);

            $pencairan->update([
                'bukti_transfer' => $buktiTransferPath,
                'status_termin' => 'already_disburse',
                'status' => 'cair',
                'tgl_cair' => now(),
            ]);

            \App\Services\AuditLogService::record(
                module: 'SIKEU',
                action: 'update',
                tableName: 'pencairan_dana_hibah',
                recordId: $pencairan->id,
                newValues: ['bukti_transfer' => $buktiTransferPath, 'status_termin' => 'already_disburse']
            );

            return $pencairan;
        });
    }
}


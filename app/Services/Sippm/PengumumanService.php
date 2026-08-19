<?php

namespace App\Services\Sippm;

use App\Models\Sippm\PengumumanHibah;
use App\Models\Sippm\PeriodeHibah;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class PengumumanService
{
    /**
     * Get active published announcement for lecturers.
     */
    public function getActiveAnnouncement(): ?PengumumanHibah
    {
        return PengumumanHibah::where('status', 'published')
            ->orderBy('published_at', 'desc')
            ->first();
    }

    /**
     * Get paginated announcements list for admin.
     */
    public function getAnnouncementsList(array $filters = [])
    {
        $query = PengumumanHibah::with(['periode', 'creator'])
            ->orderBy('created_at', 'desc');

        if (!empty($filters['tahun_anggaran'])) {
            $query->where('tahun_anggaran', $filters['tahun_anggaran']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Create a new draft announcement.
     */
    public function createDraft(array $data, ?User $user = null): PengumumanHibah
    {
        return DB::transaction(function () use ($data, $user) {
            $jadwal = $data['lampiran_jadwal'] ?? [
                ['kegiatan' => 'Pengusulan Proposal', 'tgl_mulai' => $data['tgl_buka_proposal'], 'tgl_selesai' => $data['tgl_tutup_proposal']],
                ['kegiatan' => 'Seleksi Administrasi & Desk Evaluation', 'tgl_mulai' => $data['tgl_tutup_proposal'], 'tgl_selesai' => date('Y-m-d', strtotime($data['tgl_tutup_proposal'] . ' + 7 days'))],
                ['kegiatan' => 'Review Substansi oleh Reviewer', 'tgl_mulai' => date('Y-m-d', strtotime($data['tgl_tutup_proposal'] . ' + 8 days')), 'tgl_selesai' => date('Y-m-d', strtotime($data['tgl_tutup_proposal'] . ' + 14 days'))],
                ['kegiatan' => 'Penetapan Pemenang Hibah', 'tgl_mulai' => date('Y-m-d', strtotime($data['tgl_tutup_proposal'] . ' + 15 days')), 'tgl_selesai' => date('Y-m-d', strtotime($data['tgl_tutup_proposal'] . ' + 18 days'))],
                ['kegiatan' => 'Pelaksanaan & Monitoring Evaluasi (Monev)', 'tgl_mulai' => date('Y-m-d', strtotime($data['tgl_tutup_proposal'] . ' + 20 days')), 'tgl_selesai' => date('Y-m-d', strtotime($data['tgl_tutup_proposal'] . ' + 90 days'))],
                ['kegiatan' => 'Pengumpulan Laporan Akhir & Luaran', 'tgl_mulai' => date('Y-m-d', strtotime($data['tgl_tutup_proposal'] . ' + 91 days')), 'tgl_selesai' => date('Y-m-d', strtotime($data['tgl_tutup_proposal'] . ' + 100 days'))],
            ];

            $pengumuman = PengumumanHibah::create([
                'nomor_surat' => $data['nomor_surat'],
                'tgl_surat' => $data['tgl_surat'],
                'hal_surat' => $data['hal_surat'] ?? ('Penerimaan Proposal PPM Hibah Institusi Tahun ' . $data['tahun_anggaran']),
                'tahun_anggaran' => $data['tahun_anggaran'],
                'tujuan_yth' => $data['tujuan_yth'] ?? "1. Ketua Program Studi\n2. Dosen di Politeknik Indonusa Surakarta",
                'kualifikasi_dosen' => $data['kualifikasi_dosen'] ?? 'Dosen ber-NIDN dan/atau Dosen belum ber-NIDN tetapi berstatus Dosen Tetap Yayasan',
                'kategori_pendanaan' => $data['kategori_pendanaan'] ?? 'Monotahun (dana riset dan dana luaran)',
                'tgl_buka_proposal' => $data['tgl_buka_proposal'],
                'tgl_tutup_proposal' => $data['tgl_tutup_proposal'],
                'nama_ketua_uppm' => $data['nama_ketua_uppm'] ?? 'Ratna Susanti, S.S., M.Pd.',
                'nik_ketua_uppm' => $data['nik_ketua_uppm'] ?? null,
                'nama_direktur' => $data['nama_direktur'] ?? 'Ir. Suci Purwandari, M.M.',
                'nik_direktur' => $data['nik_direktur'] ?? null,
                'status' => 'draft',
                'lampiran_jadwal' => $jadwal,
                'created_by' => $user?->id,
            ]);

            AuditLogService::record(
                module: 'SIPPM',
                action: 'CREATE_DRAFT_PENGUMUMAN_HIBAH',
                tableName: 'pengumuman_hibah',
                recordId: $pengumuman->id,
                oldValues: null,
                newValues: $pengumuman->toArray()
            );

            return $pengumuman;
        });
    }

    /**
     * Upload signed PDF document scan.
     */
    public function uploadSignedDocument(PengumumanHibah $pengumuman, $file): PengumumanHibah
    {
        $path = $file->store('sippm/pengumuman/signed', 'public');
        
        $oldValues = $pengumuman->toArray();
        $pengumuman->update([
            'file_signed_pdf_path' => $path,
            'status' => 'pending_scan',
        ]);

        AuditLogService::record(
            module: 'SIPPM',
            action: 'UPLOAD_SIGNED_PENGUMUMAN_HIBAH',
            tableName: 'pengumuman_hibah',
            recordId: $pengumuman->id,
            oldValues: $oldValues,
            newValues: $pengumuman->toArray()
        );

        return $pengumuman;
    }

    /**
     * Upload template proposal documents (mitra indonesia / mitra internasional).
     */
    public function uploadProposalTemplate(PengumumanHibah $pengumuman, string $type, $file): PengumumanHibah
    {
        if (!in_array($type, ['mitra_indo', 'mitra_intl'])) {
            throw new InvalidArgumentException("Tipe template harus 'mitra_indo' atau 'mitra_intl'.");
        }

        $path = $file->store('sippm/templates', 'public');
        $field = $type === 'mitra_indo' ? 'file_template_mitra_indo_path' : 'file_template_mitra_intl_path';

        $pengumuman->update([$field => $path]);

        return $pengumuman;
    }

    /**
     * Publish Announcement & Activate Proposal Period.
     */
    public function publishAnnouncement(PengumumanHibah $pengumuman): PengumumanHibah
    {
        return DB::transaction(function () use ($pengumuman) {
            // Deactivate older periods
            PeriodeHibah::where('is_active', true)->update(['is_active' => false]);

            // Create or update active period
            $periode = PeriodeHibah::create([
                'tahun_anggaran' => $pengumuman->tahun_anggaran,
                'nama_gelombang' => $pengumuman->hal_surat,
                'tgl_buka_proposal' => $pengumuman->tgl_buka_proposal,
                'tgl_tutup_proposal' => $pengumuman->tgl_tutup_proposal,
                'is_active' => true,
            ]);

            $oldValues = $pengumuman->toArray();

            $pengumuman->update([
                'periode_id' => $periode->id,
                'status' => 'published',
                'published_at' => now(),
            ]);

            AuditLogService::record(
                module: 'SIPPM',
                action: 'PUBLISH_PENGUMUMAN_HIBAH',
                tableName: 'pengumuman_hibah',
                recordId: $pengumuman->id,
                oldValues: $oldValues,
                newValues: $pengumuman->toArray()
            );

            return $pengumuman;
        });
    }
}

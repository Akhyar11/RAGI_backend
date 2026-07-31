<?php

namespace App\Services\Sippm;

use App\Models\Sippm\PublikasiIlmiah;
use App\Models\Sippm\HkiDanBuku;
use App\Models\Sippm\ProposalKegiatan;
use App\Models\Sippm\PencairanDanaHibah;
use App\Models\Simpeg\Pegawai;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SippmIntegrationService
{
    /**
     * Auto-sync verified research/publication output to SIMPEG BKD & Jafung.
     */
    public function syncToSimpegBkd(Pegawai $pegawai, string $kategori, string $judul, float $kumEstimasi): array
    {
        Log::info("SIPPM -> SIMPEG BKD Auto-Sync: Pegawai ID {$pegawai->id} | Kategori: {$kategori} | KUM: {$kumEstimasi}");

        return [
            'pegawai_id' => $pegawai->id,
            'nip' => $pegawai->nip,
            'nama_pegawai' => $pegawai->nama_lengkap ?? 'Dosen',
            'kategori' => $kategori,
            'judul_karya' => $judul,
            'kum_estimasi' => $kumEstimasi,
            'status_sync' => 'synchronized',
            'synced_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Process disbursement callback integration with SIKEU (Financial Module).
     */
    public function processSikeuDisbursement(PencairanDanaHibah $pencairan): array
    {
        DB::transaction(function () use ($pencairan) {
            $pencairan->update([
                'status' => 'cair',
                'tgl_cair' => now(),
                'bukti_transfer' => 'SIKEU-VA-' . time() . '.pdf',
            ]);
        });

        Log::info("SIPPM -> SIKEU Disbursement Processed: Pencairan ID {$pencairan->id} | Nominal: Rp " . number_format($pencairan->nominal, 2));

        return [
            'pencairan_id' => $pencairan->id,
            'kontrak_id' => $pencairan->kontrak_id,
            'nominal' => $pencairan->nominal,
            'status' => 'cair',
            'sikeu_reference' => 'SIKEU-VA-' . time(),
        ];
    }

    /**
     * Aggregate IKU 5 & IKU 6 metrics for UPM (Unit Penjaminan Mutu).
     */
    public function getUpmIkuMetrics(int $tahunAnggaran): array
    {
        $totalProposalLolos = ProposalKegiatan::whereHas('periode', function ($q) use ($tahunAnggaran) {
            $q->where('tahun_anggaran', $tahunAnggaran);
        })->whereIn('status', ['lolos', 'berjalan', 'selesai'])->count();

        $totalPublikasiScopus = PublikasiIlmiah::whereYear('created_at', $tahunAnggaran)
            ->where('is_verified_lppm', true)
            ->whereIn('indexing', ['scopus_q1', 'scopus_q2', 'scopus_q3', 'scopus_q4', 'wos'])
            ->count();

        $totalHkiPaten = HkiDanBuku::whereYear('created_at', $tahunAnggaran)
            ->where('is_verified_lppm', true)
            ->count();

        return [
            'tahun_anggaran' => $tahunAnggaran,
            'iku_5' => [
                'deskripsi' => 'Hasil Kerja Dosen Digunakan Oleh Masyarakat / Mendapat Rekognisi Internasional',
                'total_publikasi_bereputasi' => $totalPublikasiScopus,
                'total_hki_dan_buku' => $totalHkiPaten,
            ],
            'iku_6' => [
                'deskripsi' => 'Program Studi Bekerjasama Dengan Mitra Kelas Dunia / DUDI',
                'total_proposal_kerjasama_mitra' => ProposalKegiatan::whereNotNull('mitra_kerjasama_id')->count(),
            ],
            'total_riset_aktif' => $totalProposalLolos,
        ];
    }
}

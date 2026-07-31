<?php

namespace Database\Seeders\Sippm;

use Illuminate\Database\Seeder;
use App\Models\Simpeg\Pegawai;
use App\Models\Sippm\SkemaKegiatan;
use App\Models\Sippm\PeriodeHibah;
use App\Models\Sippm\ProposalKegiatan;
use App\Models\Sippm\AnggotaKegiatan;
use App\Models\Sippm\ReviewerKegiatan;
use App\Models\Sippm\PenilaianProposal;
use App\Models\Sippm\KontrakKegiatan;
use App\Models\Sippm\PencairanDanaHibah;
use App\Models\Sippm\LaporanKegiatan;
use App\Models\Sippm\PublikasiIlmiah;
use App\Models\Sippm\HkiDanBuku;

class SippmSampleDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dosenList = Pegawai::where('jenis_pegawai', 'dosen')->get();
        if ($dosenList->isEmpty()) {
            $dosenList = Pegawai::limit(3)->get();
        }

        if ($dosenList->isEmpty()) {
            return;
        }

        $ketua = $dosenList->first();
        $anggotaDosen = $dosenList->count() > 1 ? $dosenList->skip(1)->first() : $ketua;
        $reviewerDosen = $dosenList->count() > 2 ? $dosenList->skip(2)->first() : $ketua;

        $skema = SkemaKegiatan::where('kode', 'SKM-PEN-PDU')->first() ?? SkemaKegiatan::first();
        $periode = PeriodeHibah::first();

        if (!$skema || !$periode) {
            return;
        }

        // 1. Create Proposal Sample
        $proposal = ProposalKegiatan::updateOrCreate(
            ['kode_proposal' => 'PRP-2026-001'],
            [
                'periode_id' => $periode->id,
                'skema_id' => $skema->id,
                'ketua_pegawai_id' => $ketua->id,
                'judul' => 'Pengembangan Sistem Arsitektur Microservices SSO Kampus Berbasis Artificial Intelligence',
                'abstrak' => 'Penelitian ini bertujuan untuk merancang arsitektur Single Sign-On (SSO) terintegrasi pada ekosistem perguruan tinggi dengan perlindungan otentikasi multi-faktor dan optimasi performa query.',
                'rumpun_ilmu' => 'Teknik Informatika & Sistem Informasi',
                'target_tkt' => 6,
                'anggaran_diajukan' => 35000000.00,
                'anggaran_disetujui' => 30000000.00,
                'file_proposal' => 'proposals/2026/PRP-2026-001_proposal.pdf',
                'status' => 'berjalan',
            ]
        );

        // 2. Add Anggota Kegiatan
        AnggotaKegiatan::updateOrCreate(
            ['proposal_id' => $proposal->id, 'peran' => 'ketua'],
            [
                'jenis_anggota' => 'dosen',
                'pegawai_id' => $ketua->id,
                'tugas_kegiatan' => 'Penanggung jawab utama penelitian dan penyusunan modul arsitektur.',
            ]
        );

        if ($anggotaDosen->id !== $ketua->id) {
            AnggotaKegiatan::updateOrCreate(
                ['proposal_id' => $proposal->id, 'pegawai_id' => $anggotaDosen->id],
                [
                    'jenis_anggota' => 'dosen',
                    'peran' => 'anggota',
                    'tugas_kegiatan' => 'Analisis data performa dan pengujian keamanan otentikasi.',
                ]
            );
        }

        // 3. Add Reviewer & Penilaian
        $reviewer = ReviewerKegiatan::updateOrCreate(
            ['proposal_id' => $proposal->id, 'reviewer_pegawai_id' => $reviewerDosen->id],
            [
                'tgl_penugasan' => '2026-02-15',
                'status_review' => 'selesai',
            ]
        );

        PenilaianProposal::updateOrCreate(
            ['reviewer_kegiatan_id' => $reviewer->id],
            [
                'skor_rekam_jejak' => 88.50,
                'skor_substansi' => 92.00,
                'skor_rencana_anggaran' => 85.00,
                'skor_total' => 89.50,
                'rekomendasi' => 'diterima',
                'catatan_revisi' => 'Proposal sangat baik dan laik didanai. Harap lengkapi instrumen pengujian performa pada monev.',
                'file_penilaian' => 'rubrik/2026/REV-001_review.pdf',
                'submitted_at' => now(),
            ]
        );

        // 4. Create Kontrak Perjanjian
        $kontrak = KontrakKegiatan::updateOrCreate(
            ['proposal_id' => $proposal->id],
            [
                'nomor_kontrak' => 'SPK/LPPM/2026/088',
                'dana_disetujui' => 30000000.00,
                'tgl_mulai' => '2026-04-01',
                'tgl_selesai' => '2026-11-15',
                'file_kontrak' => 'kontrak/2026/SPK-088_signed.pdf',
                'status' => 'aktif',
            ]
        );

        // 5. Create Pencairan Dana Termin 1
        PencairanDanaHibah::updateOrCreate(
            ['kontrak_id' => $kontrak->id, 'termin_ke' => 1],
            [
                'persen_pencairan' => 70.00,
                'nominal' => 21000000.00,
                'status' => 'cair',
                'tgl_cair' => '2026-04-10',
                'bukti_transfer' => 'transfer/2026/TRF-70Persen.pdf',
            ]
        );

        // 6. Create Laporan Kemajuan (Monev)
        LaporanKegiatan::updateOrCreate(
            ['kontrak_id' => $kontrak->id, 'jenis_laporan' => 'kemajuan'],
            [
                'file_laporan' => 'laporan/2026/Laporan_Kemajuan_PRP-001.pdf',
                'file_logbook' => 'laporan/2026/Logbook_PRP-001.pdf',
                'file_penggunaan_anggaran' => 'laporan/2026/SPJ_Termin1.pdf',
                'persentase_capaian' => 75,
                'status_verifikasi' => 'disetujui',
                'catatan_lppm' => 'Laporan kemajuan telah diverifikasi oleh tim LPPM. Capaian sesuai target 75%.',
                'submitted_at' => now(),
            ]
        );

        // 7. Create Publikasi Ilmiah Sample (Scopus Q1)
        PublikasiIlmiah::updateOrCreate(
            ['judul_artikel' => 'High-Performance Unified Authentication for Higher Education Enterprise Architecture'],
            [
                'proposal_id' => $proposal->id,
                'pegawai_id' => $ketua->id,
                'jenis_publikasi' => 'jurnal_internasional_bereputasi',
                'nama_jurnal_prosiding' => 'IEEE Transactions on Education & Software Engineering',
                'indexing' => 'scopus_q1',
                'volume_issue_tahun' => 'Vol. 14 No. 2 (2026)',
                'doi' => '10.1109/TSE.2026.301294',
                'url_artikel' => 'https://doi.org/10.1109/TSE.2026.301294',
                'file_artikel' => 'publikasi/2026/IEEE_Published_Article.pdf',
                'is_verified_lppm' => true,
            ]
        );

        // 8. Create HKI Sample (Hak Cipta Software)
        HkiDanBuku::updateOrCreate(
            ['nomor_pencatatan_isbn' => '0004928172-HKI-2026'],
            [
                'proposal_id' => $proposal->id,
                'pegawai_id' => $ketua->id,
                'jenis_luaran' => 'hak_cipta',
                'judul' => 'Program Komputer: Sistem Single Sign-On Campus (SSO Campus) V1.0',
                'penerbit_lembaga' => 'Direktorat Jenderal Kekayaan Intelektual (DJKI) Kemenkumham RI',
                'tgl_terbit_catat' => '2026-05-20',
                'file_sertifikat_buku' => 'hki/2026/Sertifikat_HKI_SSO.pdf',
                'is_verified_lppm' => true,
            ]
        );
    }
}

<?php

namespace Database\Seeders\Sippm;

use Illuminate\Database\Seeder;
use App\Models\Simpeg\Pegawai;
use App\Models\Sippm\SkemaKegiatan;
use App\Models\Sippm\PeriodeHibah;
use App\Models\Sippm\ProposalKegiatan;
use App\Models\Sippm\AnggotaKegiatan;
use App\Models\Sippm\RubrikIndikator;
use App\Models\Sippm\PenilaianRubrikDetail;

class SippmReviewWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $periode = PeriodeHibah::first();
        $skemaPenelitian = SkemaKegiatan::where('jenis_kegiatan', 'penelitian')->first() ?? SkemaKegiatan::first();
        $skemaPengabdian = SkemaKegiatan::where('jenis_kegiatan', 'pengabdian')->first() ?? SkemaKegiatan::latest()->first();
        $dosenList = Pegawai::where('jenis_pegawai', 'dosen')->with('unitKerja')->get();

        if ($dosenList->isEmpty() || !$periode) {
            return;
        }

        $dosenTi = $dosenList->firstWhere('unit_kerja_id', 1) ?? $dosenList[0];
        $dosenDkv = $dosenList[1] ?? $dosenList[0];
        $dosenSi = $dosenList[2] ?? $dosenList[0];

        $rubrikKaprodi = RubrikIndikator::where('tipe_reviewer', 'kaprodi')->get();
        $rubrikAdmin = RubrikIndikator::where('tipe_reviewer', 'admin')->get();

        // ------------------------------------------------------------
        // PROPOSAL 1: Tahap 1 - Menunggu Review Kaprodi (Penelitian)
        // ------------------------------------------------------------
        $p1 = ProposalKegiatan::updateOrCreate(
            ['kode_proposal' => 'PRP-2026-T1-001'],
            [
                'periode_id' => $periode->id,
                'skema_id' => $skemaPenelitian->id,
                'ketua_pegawai_id' => $dosenTi->id,
                'judul' => 'Penerapan Machine Learning & Computer Vision untuk Precision Agriculture',
                'abstrak' => 'Penelitian ini merancang sistem deteksi penyakit daun secara real-time menggunakan arsitektur Convolutional Neural Networks (CNN) yang terintegrasi dengan perangkat edge computing.',
                'rumpun_ilmu' => $dosenTi->unitKerja?->nama ?? 'S1 Teknik Informatika',
                'target_tkt' => 4,
                'anggaran_diajukan' => 45000000.00,
                'anggaran_disetujui' => 0.00,
                'file_proposal' => 'proposals/2026/PRP-2026-T1-001_proposal.pdf',
                'status' => 'diajukan',
            ]
        );

        AnggotaKegiatan::updateOrCreate(
            ['proposal_id' => $p1->id, 'pegawai_id' => $dosenTi->id],
            [
                'jenis_tim' => 'dosen',
                'pegawai_id' => $dosenTi->id,
                'peran_dalam_tim' => 'Ketua Pengusul',
                'tugas_kegiatan' => 'Penanggung jawab perancangan algoritma CNN dan supervisi lapangan.',
            ]
        );

        // ------------------------------------------------------------
        // PROPOSAL 2: Tahap 1 - Menunggu Review Kaprodi (Pengabdian)
        // ------------------------------------------------------------
        $p2 = ProposalKegiatan::updateOrCreate(
            ['kode_proposal' => 'PRP-2026-T1-002'],
            [
                'periode_id' => $periode->id,
                'skema_id' => $skemaPengabdian->id,
                'ketua_pegawai_id' => $dosenDkv->id,
                'judul' => 'Pemberdayaan UMKM Kriya Lokal Melalui Redesain Packaging & Digital Branding',
                'abstrak' => 'Program pengabdian masyarakat ini bertujuan untuk meningkatkan nilai jual produk UMKM kriya melalui pendampingan redesain identitas visual, kemasan ramah lingkungan, dan optimalisasi e-commerce.',
                'rumpun_ilmu' => $dosenDkv->unitKerja?->nama ?? 'S1 Desain Komunikasi Visual',
                'target_tkt' => 3,
                'anggaran_diajukan' => 25000000.00,
                'anggaran_disetujui' => 0.00,
                'file_proposal' => 'proposals/2026/PRP-2026-T1-002_proposal.pdf',
                'status' => 'diajukan',
            ]
        );

        // ------------------------------------------------------------
        // PROPOSAL 3: Tahap 2 - Disetujui Kaprodi, Menunggu Admin SIPPM
        // ------------------------------------------------------------
        $p3 = ProposalKegiatan::updateOrCreate(
            ['kode_proposal' => 'PRP-2026-T2-003'],
            [
                'periode_id' => $periode->id,
                'skema_id' => $skemaPenelitian->id,
                'ketua_pegawai_id' => $dosenSi->id,
                'judul' => 'Pengembangan Sistem Informasi Manajemen Rantai Pasok Berbasis Blockchain',
                'abstrak' => 'Implementasi smart contract ethereum untuk menjamin transparansi lacak balak (traceability) komoditas ekspor daerah secara terdesentralisasi.',
                'rumpun_ilmu' => $dosenSi->unitKerja?->nama ?? 'S1 Sistem Informasi',
                'target_tkt' => 5,
                'anggaran_diajukan' => 50000000.00,
                'anggaran_disetujui' => 0.00,
                'file_proposal' => 'proposals/2026/PRP-2026-T2-003_proposal.pdf',
                'status' => 'disetujui_kaprodi',
            ]
        );

        // Simpan nilai rubrik Kaprodi (Tahap 1) dengan Skor > 80 (Lolos Tahap 1)
        foreach ($rubrikKaprodi as $rk) {
            PenilaianRubrikDetail::updateOrCreate(
                [
                    'proposal_id' => $p3->id,
                    'rubrik_id' => $rk->id,
                    'tipe_reviewer' => 'kaprodi',
                ],
                [
                    'reviewer_pegawai_id' => $dosenSi->id,
                    'skor' => 90.00,
                    'catatan' => 'Topik riset sangat relevan dengan keahlian dosen di prodi.',
                ]
            );
        }

        // ------------------------------------------------------------
        // PROPOSAL 4: Tahap 3 - Final Approved (Lolos Kaprodi & Admin)
        // ------------------------------------------------------------
        $p4 = ProposalKegiatan::updateOrCreate(
            ['kode_proposal' => 'PRP-2026-T3-004'],
            [
                'periode_id' => $periode->id,
                'skema_id' => $skemaPenelitian->id,
                'ketua_pegawai_id' => $dosenTi->id,
                'judul' => 'Rancang Bangun Platform IoT Smart Energy Monitoring untuk Gedung Kampus',
                'abstrak' => 'Pengembangan arsitektur sensor pintar terdistribusi untuk mengukur dan mengoptimalkan efisiensi energi listrik gedung secara otomatis.',
                'rumpun_ilmu' => $dosenTi->unitKerja?->nama ?? 'S1 Teknik Informatika',
                'target_tkt' => 6,
                'anggaran_diajukan' => 40000000.00,
                'anggaran_disetujui' => 38000000.00,
                'file_proposal' => 'proposals/2026/PRP-2026-T3-004_proposal.pdf',
                'status' => 'disetujui_admin',
            ]
        );

        // Simpan Nilai Tahap 1 (Kaprodi Skor 92) & Tahap 2 (Admin Skor 88)
        foreach ($rubrikKaprodi as $rk) {
            PenilaianRubrikDetail::updateOrCreate(
                ['proposal_id' => $p4->id, 'rubrik_id' => $rk->id, 'tipe_reviewer' => 'kaprodi'],
                ['skor' => 92.00, 'catatan' => 'Sesuai dengan visi keilmuan prodi. Topik berbobot tinggi.']
            );
        }
        foreach ($rubrikAdmin as $ra) {
            PenilaianRubrikDetail::updateOrCreate(
                ['proposal_id' => $p4->id, 'rubrik_id' => $ra->id, 'tipe_reviewer' => 'admin'],
                ['skor' => 88.00, 'catatan' => 'Dokumen administrasi & RAB valid sesuai panduan hibah.']
            );
        }
    }
}

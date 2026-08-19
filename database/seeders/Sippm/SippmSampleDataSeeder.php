<?php

namespace Database\Seeders\Sippm;

use Illuminate\Database\Seeder;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\UnitKerja;
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
        $skemas = SkemaKegiatan::all();
        $periode = PeriodeHibah::first();

        if ($skemas->isEmpty() || !$periode) {
            return;
        }

        $skemaDikti = SkemaKegiatan::where('tipe', 'penelitian')->first() ?? $skemas->first();
        $skemaInternal = SkemaKegiatan::where('tipe', 'pengabdian')->first() ?? $skemas->last();

        $prodis = UnitKerja::where('tipe', 'prodi')->get();

        $propCounter = 1;
        foreach ($prodis as $prodi) {
            $dosenInProdi = Pegawai::where('unit_kerja_id', $prodi->id)->where('jenis_pegawai', 'dosen')->get();

            if ($dosenInProdi->isEmpty()) continue;

            foreach ($dosenInProdi as $idx => $dosen) {
                $propCode = 'PRP-2026-' . sprintf('%03d', $propCounter++);
                $isDikti = ($idx % 2 === 0);
                $skemaUsed = $isDikti ? $skemaDikti : $skemaInternal;
                $anggaranNominal = $isDikti ? 45000000.00 : 20000000.00;

                // 1. Create Proposal Kegiatan
                $proposal = ProposalKegiatan::updateOrCreate(
                    ['kode_proposal' => $propCode],
                    [
                        'periode_id' => $periode->id,
                        'skema_id' => $skemaUsed->id,
                        'ketua_pegawai_id' => $dosen->id,
                        'judul' => 'Riset Terapan & Inovasi ' . $prodi->nama . ': ' . ($dosen->riwayatPendidikan->first()?->bidang_ilmu ?? 'Teknologi Informasi'),
                        'abstrak' => 'Penelitian ini berfokus pada implementasi teknologi terapan dan pengembangan solusi inovatif untuk meningkatkan efisiensi dan daya saing di bidang ' . $prodi->nama,
                        'rumpun_ilmu' => $prodi->nama,
                        'target_tkt' => 6,
                        'anggaran_diajukan' => $anggaranNominal,
                        'anggaran_disetujui' => $anggaranNominal * 0.9,
                        'file_proposal' => 'proposals/2026/' . $propCode . '_proposal.pdf',
                        'status' => 'berjalan',
                    ]
                );

                // 2. Add Anggota Kegiatan (Ketua Dosen, Anggota Tendik, Mahasiswa, & Dosen Eksternal)
                AnggotaKegiatan::updateOrCreate(
                    ['proposal_id' => $proposal->id, 'peran_dalam_tim' => 'Ketua Pengusul'],
                    [
                        'jenis_tim' => 'dosen',
                        'pegawai_id' => $dosen->id,
                        'peran_dalam_tim' => 'Ketua Pengusul',
                        'tugas_kegiatan' => 'Penanggung jawab utama pelaksanaan riset dan perancangan metodologi.',
                    ]
                );

                // Add Dosen Eksternal Anggota
                AnggotaKegiatan::updateOrCreate(
                    ['proposal_id' => $proposal->id, 'nama_eksternal' => 'Dr. Ir. Budi Santoso, M.T.'],
                    [
                        'jenis_tim' => 'dosen_eksternal',
                        'instansi_eksternal' => 'Universitas Gadjah Mada',
                        'nidn_eksternal' => '0012057801',
                        'peran_dalam_tim' => 'Anggota Peneliti Eksternal',
                        'tugas_kegiatan' => 'Pengujian skala laboratorium dan validasi data instrumen.',
                    ]
                );

                // Add Mahasiswa Anggota with SIAKAD Grade Conversion Course
                AnggotaKegiatan::updateOrCreate(
                    ['proposal_id' => $proposal->id, 'mahasiswa_id' => 1001],
                    [
                        'jenis_tim' => 'mahasiswa',
                        'mahasiswa_id' => 1001,
                        'mata_kuliah_id' => 101, // SIAKAD Course Integration for grade conversion
                        'peran_dalam_tim' => 'Anggota Mahasiswa (MBKM)',
                        'tugas_kegiatan' => 'Pengumpulan sampel data lapangan dan pembantu pengolahan kuisioner.',
                    ]
                );

                // 3. Add Reviewer & Penilaian
                $reviewerPegawai = $dosenInProdi->where('id', '!=', $dosen->id)->first() ?? $dosen;
                $reviewer = ReviewerKegiatan::updateOrCreate(
                    ['proposal_id' => $proposal->id, 'reviewer_pegawai_id' => $reviewerPegawai->id],
                    [
                        'tgl_penugasan' => '2026-02-10',
                        'status_review' => 'selesai',
                    ]
                );

                PenilaianProposal::updateOrCreate(
                    ['reviewer_kegiatan_id' => $reviewer->id],
                    [
                        'skor_rekam_jejak' => 85.00 + ($idx * 2),
                        'skor_substansi' => 90.00,
                        'skor_rencana_anggaran' => 88.00,
                        'skor_total' => 87.50 + $idx,
                        'rekomendasi' => 'diterima',
                        'catatan_revisi' => 'Proposal sangat baik dan layak didanai LPPM.',
                        'file_penilaian' => 'rubrik/2026/REV-' . sprintf('%03d', $propCounter) . '_review.pdf',
                        'submitted_at' => now(),
                    ]
                );

                // 4. Create Kontrak Perjanjian
                $kontrak = KontrakKegiatan::updateOrCreate(
                    ['proposal_id' => $proposal->id],
                    [
                        'nomor_kontrak' => 'SPK/LPPM/2026/' . sprintf('%03d', $proposal->id),
                        'dana_disetujui' => $anggaranNominal * 0.9,
                        'tgl_mulai' => '2026-03-01',
                        'tgl_selesai' => '2026-11-30',
                        'file_kontrak' => 'kontrak/2026/SPK-' . sprintf('%03d', $proposal->id) . '_signed.pdf',
                        'status' => 'aktif',
                    ]
                );

                // 5. Create Pencairan Dana Termin 1
                PencairanDanaHibah::updateOrCreate(
                    ['kontrak_id' => $kontrak->id, 'termin_ke' => 1],
                    [
                        'persen_pencairan' => 70.00,
                        'nominal' => ($anggaranNominal * 0.9) * 0.7,
                        'status' => 'cair',
                        'tgl_cair' => '2026-03-15',
                        'bukti_transfer' => 'transfer/2026/TRF-' . sprintf('%03d', $propCounter) . '.pdf',
                    ]
                );

                // 6. Create Laporan Kemajuan
                LaporanKegiatan::updateOrCreate(
                    ['kontrak_id' => $kontrak->id, 'jenis_laporan' => 'kemajuan'],
                    [
                        'file_laporan' => 'laporan/2026/Laporan_Kemajuan_' . $propCode . '.pdf',
                        'file_logbook' => 'laporan/2026/Logbook_' . $propCode . '.pdf',
                        'file_penggunaan_anggaran' => 'laporan/2026/SPJ_' . $propCode . '.pdf',
                        'persentase_capaian' => 80,
                        'status_verifikasi' => 'disetujui',
                        'catatan_lppm' => 'Laporan kemajuan disetujui tim LPPM.',
                        'submitted_at' => now(),
                    ]
                );

                // 7. Create Publikasi Ilmiah (Scopus / SINTA)
                $isScopus = ($idx % 2 === 0);
                PublikasiIlmiah::updateOrCreate(
                    ['judul_artikel' => 'Publikasi Riset ' . $dosen->nama_lengkap . ': ' . ($dosen->riwayatPendidikan->first()?->bidang_ilmu ?? 'Sistem Cerdas')],
                    [
                        'proposal_id' => $proposal->id,
                        'pegawai_id' => $dosen->id,
                        'jenis_publikasi' => $isScopus ? 'jurnal_internasional_bereputasi' : 'jurnal_nasional_terakreditasi',
                        'nama_jurnal_prosiding' => $isScopus ? 'IEEE Access / Elsevier Journal (Scopus Q' . (($idx % 3) + 1) . ')' : 'Jurnal Nasional Terakreditasi (SINTA ' . (($idx % 3) + 1) . ')',
                        'indexing' => $isScopus ? 'scopus_q' . (($idx % 3) + 1) : 'sinta_' . (($idx % 3) + 1),
                        'volume_issue_tahun' => 'Vol. ' . (10 + $idx) . ' No. 1 (2026)',
                        'doi' => '10.1109/RAGI.2026.' . sprintf('%06d', $propCounter * 123),
                        'url_artikel' => 'https://doi.org/10.1109/RAGI.2026.' . sprintf('%06d', $propCounter * 123),
                        'file_artikel' => 'publikasi/2026/Article_' . $propCode . '.pdf',
                        'is_verified_lppm' => true,
                    ]
                );

                // 8. Create HKI & Paten
                HkiDanBuku::updateOrCreate(
                    ['nomor_pencatatan_isbn' => 'EC002026' . sprintf('%05d', $propCounter * 456)],
                    [
                        'proposal_id' => $proposal->id,
                        'pegawai_id' => $dosen->id,
                        'jenis_luaran' => ($idx % 2 === 0) ? 'hak_cipta' : 'paten',
                        'judul' => 'Ciptaan / Paten Inovasi: ' . ($dosen->riwayatPendidikan->first()?->bidang_ilmu ?? 'Sistem Terintegrasi'),
                        'penerbit_lembaga' => 'DJKI Kemenkumham RI',
                        'tgl_terbit_catat' => '2026-04-' . (10 + $idx),
                        'file_sertifikat_buku' => 'hki/2026/Sertifikat_' . $propCode . '.pdf',
                        'is_verified_lppm' => true,
                    ]
                );
            }
        }
    }
}

<?php

namespace Database\Seeders\Simpeg;

use App\Models\Simpeg\DokumenPegawai;
use App\Models\Simpeg\GajiPegawai;
use App\Models\Simpeg\JabatanFungsionalAkademik;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PengajuanCuti;
use App\Models\Simpeg\PenilaianKinerja;
use App\Models\Simpeg\PresensiPegawai;
use App\Models\Simpeg\UsulanJafung;
use Illuminate\Database\Seeder;

class EnterpriseSimpegSeeder extends Seeder
{
    public function run(): void
    {
        $pegawaiList = Pegawai::all();
        if ($pegawaiList->isEmpty()) return;

        foreach ($pegawaiList as $pegawai) {
            // 1. Dokumen Pegawai
            DokumenPegawai::firstOrCreate([
                'pegawai_id' => $pegawai->id,
                'nama_dokumen' => 'KTP - ' . $pegawai->nama_lengkap,
            ], [
                'jenis_dokumen' => 'ktp',
                'file_path' => 'dokumen/ktp_' . $pegawai->id . '.pdf',
                'file_size' => '1.2 MB',
                'status_verifikasi' => 'terverifikasi',
            ]);

            DokumenPegawai::firstOrCreate([
                'pegawai_id' => $pegawai->id,
                'nama_dokumen' => 'Ijazah Pendidikan Terakhir',
            ], [
                'jenis_dokumen' => 'ijazah',
                'file_path' => 'dokumen/ijazah_' . $pegawai->id . '.pdf',
                'file_size' => '2.5 MB',
                'status_verifikasi' => 'terverifikasi',
            ]);

            DokumenPegawai::firstOrCreate([
                'pegawai_id' => $pegawai->id,
                'nama_dokumen' => 'SK Pengangkatan Kepegawaian',
            ], [
                'jenis_dokumen' => 'sk',
                'file_path' => 'dokumen/sk_' . $pegawai->id . '.pdf',
                'file_size' => '1.8 MB',
                'status_verifikasi' => 'terverifikasi',
            ]);

            // 2. Pengajuan Cuti
            PengajuanCuti::firstOrCreate([
                'pegawai_id' => $pegawai->id,
                'tanggal_mulai' => '2026-06-10',
            ], [
                'jenis_cuti' => 'tahunan',
                'tanggal_selesai' => '2026-06-14',
                'jumlah_hari' => 5,
                'alasan' => 'Cuti Tahunan & Keperluan Keluarga',
                'status_approval' => 'approved',
                'approved_by' => $pegawai->user_id,
                'catatan_approval' => 'Disetujui oleh Bagian Kepegawaian',
            ]);

            PengajuanCuti::firstOrCreate([
                'pegawai_id' => $pegawai->id,
                'tanggal_mulai' => '2026-08-15',
            ], [
                'jenis_cuti' => 'alasan_penting',
                'tanggal_selesai' => '2026-08-16',
                'jumlah_hari' => 2,
                'alasan' => 'Menghadiri Seminar Internasional & Konferensi Nasional',
                'status_approval' => 'pending',
                'approved_by' => null,
                'catatan_approval' => null,
            ]);

            // 3. Presensi Pegawai (3 Hari Terakhir)
            $dates = ['2026-07-28', '2026-07-29', '2026-07-30'];
            foreach ($dates as $tgl) {
                PresensiPegawai::firstOrCreate([
                    'pegawai_id' => $pegawai->id,
                    'tanggal' => $tgl,
                ], [
                    'jam_masuk' => '07:45:00',
                    'jam_keluar' => '16:15:00',
                    'status_kehadiran' => 'hadir',
                    'lat_long' => '-6.8915,107.6107',
                    'catatan' => 'Presensi Fingerprint & GPS Verified',
                ]);
            }

            // 4. Gaji & Slip Gaji Pegawai (Juli & Juni 2026)
            GajiPegawai::firstOrCreate([
                'pegawai_id' => $pegawai->id,
                'periode_bulan_tahun' => '2026-07',
            ], [
                'gaji_pokok' => 7500000,
                'total_tunjangan' => 3500000,
                'total_potongan' => 500000,
                'gaji_bersih' => 10500000,
                'status_transfer' => 'paid',
                'tanggal_transfer' => '2026-07-25',
                'nomor_rekening' => '1310012345678',
                'bank_nama' => 'Bank Mandiri',
            ]);

            GajiPegawai::firstOrCreate([
                'pegawai_id' => $pegawai->id,
                'periode_bulan_tahun' => '2026-06',
            ], [
                'gaji_pokok' => 7500000,
                'total_tunjangan' => 3500000,
                'total_potongan' => 500000,
                'gaji_bersih' => 10500000,
                'status_transfer' => 'paid',
                'tanggal_transfer' => '2026-06-25',
                'nomor_rekening' => '1310012345678',
                'bank_nama' => 'Bank Mandiri',
            ]);

            // 5. Usulan Jafung
            $jafungLektor = JabatanFungsionalAkademik::where('golongan', 'lektor')->first();
            $jafungKepala = JabatanFungsionalAkademik::where('golongan', 'lektor_kepala')->first();

            if ($jafungLektor && $jafungKepala) {
                UsulanJafung::firstOrCreate([
                    'pegawai_id' => $pegawai->id,
                    'jafung_tujuan_id' => $jafungKepala->id,
                ], [
                    'jafung_asal_id' => $jafungLektor->id,
                    'angka_kredit_usulan' => 450,
                    'status_usulan' => 'submitted',
                    'catatan_reviewer' => 'Syarat kelayakan jurnal bereputasi Scopus Q2 terpenuhi.',
                ]);
            }

            // 6. Penilaian Kinerja BKD
            PenilaianKinerja::firstOrCreate([
                'pegawai_id' => $pegawai->id,
                'tahun' => 2026,
                'semester' => 'tahunan',
            ], [
                'nilai_skp' => 94.50,
                'nilai_bkd' => 96.00,
                'predikat' => 'sangat_baik',
                'catatan_evaluator' => 'Kinerja pengajaran, penelitian, dan pengabdian masyarakat sangat memuaskan.',
                'evaluator_id' => $pegawai->user_id,
            ]);
        }
    }
}

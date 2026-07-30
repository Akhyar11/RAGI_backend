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
        $pegawai = Pegawai::first();
        if (!$pegawai) return;

        // 1. Dokumen Pegawai
        DokumenPegawai::firstOrCreate([
            'pegawai_id' => $pegawai->id,
            'nama_dokumen' => 'KTP Wasis Utama',
        ], [
            'jenis_dokumen' => 'ktp',
            'file_path' => 'dokumen/ktp_wasis.pdf',
            'file_size' => '1.2 MB',
            'status_verifikasi' => 'terverifikasi',
        ]);

        DokumenPegawai::firstOrCreate([
            'pegawai_id' => $pegawai->id,
            'nama_dokumen' => 'Ijazah S3 ITB Informatika',
        ], [
            'jenis_dokumen' => 'ijazah',
            'file_path' => 'dokumen/ijazah_s3.pdf',
            'file_size' => '2.5 MB',
            'status_verifikasi' => 'terverifikasi',
        ]);

        // 2. Pengajuan Cuti
        PengajuanCuti::firstOrCreate([
            'pegawai_id' => $pegawai->id,
            'tanggal_mulai' => '2026-08-10',
        ], [
            'jenis_cuti' => 'tahunan',
            'tanggal_selesai' => '2026-08-12',
            'jumlah_hari' => 3,
            'alasan' => 'Keperluan keluarga dan riset konferensi',
            'status_approval' => 'approved',
            'approved_by' => $pegawai->user_id,
            'catatan_approval' => 'Disetujui oleh SDM',
        ]);

        // 3. Presensi Pegawai
        PresensiPegawai::firstOrCreate([
            'pegawai_id' => $pegawai->id,
            'tanggal' => now()->toDateString(),
        ], [
            'jam_masuk' => '07:55:00',
            'jam_keluar' => '16:30:00',
            'status_kehadiran' => 'hadir',
            'lat_long' => '-6.8915,107.6107',
            'catatan' => 'Tepat waktu',
        ]);

        // 4. Gaji Pegawai
        GajiPegawai::firstOrCreate([
            'pegawai_id' => $pegawai->id,
            'periode_bulan_tahun' => '2026-07',
        ], [
            'gaji_pokok' => 7500000,
            'total_tunjangan' => 3500000,
            'total_potongan' => 500000,
            'gaji_bersih' => 10500000,
            'status_transfer' => 'paid',
            'tanggal_transfer' => now(),
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
                'catatan_reviewer' => 'Syarat jurnal bereputasi Scopus Q2 terpenuhi.',
            ]);
        }

        // 6. Penilaian Kinerja BKD
        PenilaianKinerja::firstOrCreate([
            'pegawai_id' => $pegawai->id,
            'tahun' => 2026,
            'semester' => 'tahunan',
        ], [
            'nilai_skp' => 92.50,
            'nilai_bkd' => 95.00,
            'predikat' => 'sangat_baik',
            'catatan_evaluator' => 'Kinerja pengajaran dan publikasi sangat memuaskan.',
            'evaluator_id' => $pegawai->user_id,
        ]);
    }
}

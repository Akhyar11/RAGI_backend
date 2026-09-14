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
            // 1. Dokumen Pegawai (Cleared by user demand)
            // DokumenPegawai records cleared

            // 2. Pengajuan Cuti (Cleared by user demand)
            // PengajuanCuti records cleared

            // 3. Presensi Pegawai (Cleared by user demand)
            // PresensiPegawai records cleared

            // 4. Gaji & Slip Gaji Pegawai (Cleared by user demand)
            // GajiPegawai records cleared

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

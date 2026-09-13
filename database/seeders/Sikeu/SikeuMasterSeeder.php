<?php

namespace Database\Seeders\Sikeu;

use Illuminate\Database\Seeder;
use App\Models\Sikeu\MasterBiaya;
use App\Models\Sikeu\MasterBiayaModule;
use App\Models\Sikeu\UnitKas;
use App\Models\Sikeu\PeriodeAkuntansi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SikeuMasterSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('core_master_biaya_modules')->truncate();
        DB::table('sikeu_master_biaya')->truncate();
        DB::table('sikeu_unit_kas')->truncate();
        DB::table('sikeu_periode_akuntansi')->truncate();
        Schema::enableForeignKeyConstraints();

        // 1. Seed Master Biaya & Module Delegations
        $jbUkt = MasterBiaya::firstOrCreate(
            ['kode' => 'UKT_REG'],
            [
                'nama' => 'Uang Kuliah Tunggal (UKT) Reguler',
                'tipe' => 'spp',
                'nominal_standar' => 3500000.00,
                'deskripsi' => 'Biaya pendidikan semesteran reguler mahasiswa',
                'is_recurring' => true,
                'is_active' => true,
            ]
        );
        MasterBiayaModule::firstOrCreate(['master_biaya_id' => $jbUkt->id, 'module_code' => 'siakad']);
        MasterBiayaModule::firstOrCreate(['master_biaya_id' => $jbUkt->id, 'module_code' => 'sikeu']);

        $jbSpmb = MasterBiaya::firstOrCreate(
            ['kode' => 'SPMB_ADM'],
            [
                'nama' => 'Biaya Pendaftaran SPMB',
                'tipe' => 'spmb_adm',
                'nominal_standar' => 250000.00,
                'deskripsi' => 'Biaya formulir & ujian seleksi penerimaan mahasiswa baru',
                'is_recurring' => false,
                'is_active' => true,
            ]
        );
        MasterBiayaModule::firstOrCreate(['master_biaya_id' => $jbSpmb->id, 'module_code' => 'spmb']);
        MasterBiayaModule::firstOrCreate(['master_biaya_id' => $jbSpmb->id, 'module_code' => 'sikeu']);

        $jbWisuda = MasterBiaya::firstOrCreate(
            ['kode' => 'WISUDA_FEE'],
            [
                'nama' => 'Biaya Kelulusan & Wisuda',
                'tipe' => 'wisuda',
                'nominal_standar' => 1500000.00,
                'deskripsi' => 'Biaya ijazah, toga, & upacara wisuda',
                'is_recurring' => false,
                'is_active' => true,
            ]
        );
        MasterBiayaModule::firstOrCreate(['master_biaya_id' => $jbWisuda->id, 'module_code' => 'siakad']);
        MasterBiayaModule::firstOrCreate(['master_biaya_id' => $jbWisuda->id, 'module_code' => 'sikeu']);



        // 4. Seed Unit Kas
        UnitKas::create([
            'unit_kerja_id' => 1,
            'nama_kas' => 'Kas Utama Rektorat / Bank Kampus',
            'saldo_awal' => 500000000.00,
            'saldo_saat_ini' => 500000000.00,
            'penanggung_jawab_id' => 1,
            'deskripsi' => 'Rekening utama penampungan seluruh penerimaan & pengeluaran universitas',
            'status' => true,
        ]);
        UnitKas::create([
            'unit_kerja_id' => 2,
            'nama_kas' => 'Petty Cash Fakultas Teknik & Ilmu Komputer',
            'saldo_awal' => 10000000.00,
            'saldo_saat_ini' => 10000000.00,
            'penanggung_jawab_id' => 2,
            'deskripsi' => 'Kas kecil operasional harian fakultas TIK',
            'status' => true,
        ]);

        // 5. Seed Periode Akuntansi
        PeriodeAkuntansi::firstOrCreate(
            ['tahun' => 2026, 'bulan' => 8],
            [
                'nama_periode' => 'Periode Agustus 2026',
                'tanggal_mulai' => '2026-08-01',
                'tanggal_selesai' => '2026-08-31',
                'status' => 'terbuka',
            ]
        );

        // 6. Seed Jalur Kelas
        $jalurs = [
            ['kode' => 'REGULER', 'nama_jalur' => 'Reguler', 'deskripsi' => 'Perkuliahan reguler pagi-siang', 'is_active' => true],
            ['kode' => 'KARYAWAN', 'nama_jalur' => 'Karyawan / Eksekutif', 'deskripsi' => 'Perkuliahan malam / akhir pekan', 'is_active' => true],
            ['kode' => 'INTERNASIONAL', 'nama_jalur' => 'Internasional', 'deskripsi' => 'Kelas bilingual pengantar bahasa Inggris', 'is_active' => true],
        ];
        foreach ($jalurs as $j) {
            \App\Models\Sikeu\JalurKelas::firstOrCreate(['kode' => $j['kode']], $j);
        }

        // 7. Seed Tarif UKT Standard
        $tarifs = [
            ['tahun_angkatan' => 2025, 'jalur_kelas' => 'Reguler', 'kelompok_ukt' => 1, 'nama_kelompok' => 'Kelompok 1 (KIP / Prasejahtera)', 'nominal' => 500000.00],
            ['tahun_angkatan' => 2025, 'jalur_kelas' => 'Reguler', 'kelompok_ukt' => 2, 'nama_kelompok' => 'Kelompok 2 (Subsidi Khusus)', 'nominal' => 1000000.00],
            ['tahun_angkatan' => 2025, 'jalur_kelas' => 'Reguler', 'kelompok_ukt' => 3, 'nama_kelompok' => 'Kelompok 3 (Standar Menengah Bawah)', 'nominal' => 3500000.00],
            ['tahun_angkatan' => 2025, 'jalur_kelas' => 'Reguler', 'kelompok_ukt' => 4, 'nama_kelompok' => 'Kelompok 4 (Standar Penuh)', 'nominal' => 5500000.00],
            ['tahun_angkatan' => 2025, 'jalur_kelas' => 'Reguler', 'kelompok_ukt' => 5, 'nama_kelompok' => 'Kelompok 5 (Mandiri / Eksekutif)', 'nominal' => 7500000.00],
        ];
        foreach ($tarifs as $t) {
            \App\Models\Sikeu\TarifUkt::firstOrCreate(
                ['tahun_angkatan' => $t['tahun_angkatan'], 'jalur_kelas' => $t['jalur_kelas'], 'kelompok_ukt' => $t['kelompok_ukt']],
                $t
            );
        }

        // 8. Seed Program Beasiswa
        $beasiswas = [
            [
                'kode' => 'BEA-KIPK',
                'nama' => 'Beasiswa KIP-Kuliah (Kemendikbud)',
                'sumber' => 'Pemerintah',
                'tipe_potongan' => 'persen',
                'nilai_potongan' => 100.00,
                'berlaku_angkatan_mulai' => 2023,
                'berlaku_angkatan_sampai' => 2026,
                'deskripsi' => 'Pembebasan UKT 100% dari Kemendikbudristek',
                'is_active' => true,
            ],
            [
                'kode' => 'BEA-PRESTASI',
                'nama' => 'Beasiswa Prestasi Akademik Kampus',
                'sumber' => 'Yayasan / Internal',
                'tipe_potongan' => 'persen',
                'nilai_potongan' => 50.00,
                'berlaku_angkatan_mulai' => 2024,
                'berlaku_angkatan_sampai' => 2026,
                'deskripsi' => 'Potongan 50% UKT semester untuk mahasiswa berprestasi',
                'is_active' => true,
            ],
            [
                'kode' => 'BEA-HAFIDZ',
                'nama' => 'Beasiswa Tahfidz Al-Quran',
                'sumber' => 'Yayasan / Internal',
                'tipe_potongan' => 'nominal',
                'nilai_potongan' => 2500000.00,
                'berlaku_angkatan_mulai' => 2024,
                'berlaku_angkatan_sampai' => 2026,
                'deskripsi' => 'Potongan langsung Rp 2.500.000 per semester',
                'is_active' => true,
            ],
        ];
        foreach ($beasiswas as $b) {
            \App\Models\Sikeu\Beasiswa::firstOrCreate(['kode' => $b['kode']], $b);
        }

        // 9. Seed Setting Tarif (Matriks Tarif per Angkatan, Jalur, & Prodi)
        $prodiTI = \App\Models\Spmb\MasterProgramStudi::where('kode_prodi', '55201')
            ->orWhere('nama', 'like', '%Teknik Informatika%')
            ->first();
        $prodiTIId = $prodiTI ? $prodiTI->id : null;

        $jbPraktikum = MasterBiaya::firstOrCreate(
            ['kode' => 'PRAKTIKUM'],
            [
                'nama' => 'Biaya Laboratorium & Praktikum',
                'tipe' => 'praktikum',
                'nominal_standar' => 750000.00,
                'deskripsi' => 'Biaya laboratorium dan sertifikasi kompetensi praktikum',
                'is_recurring' => true,
                'is_active' => true,
            ]
        );

        $settingTarifs = [
            // Angkatan 2023 - TI - Reguler (Semester null = berlaku semua semester)
            [
                'master_biaya_id' => $jbUkt->id,
                'tahun_angkatan' => 2023,
                'program_studi_id' => $prodiTIId,
                'semester' => null,
                'jalur_kelas' => 'Reguler',
                'nominal' => 3500000.00,
                'is_active' => true,
                'keterangan' => 'UKT Reguler Angkatan 2023 Prodi Teknik Informatika',
            ],
            [
                'master_biaya_id' => $jbPraktikum->id,
                'tahun_angkatan' => 2023,
                'program_studi_id' => $prodiTIId,
                'semester' => null,
                'jalur_kelas' => 'Reguler',
                'nominal' => 750000.00,
                'is_active' => true,
                'keterangan' => 'Praktikum & Lab Komputer Prodi Teknik Informatika',
            ],
            // Angkatan 2023 - TI - Karyawan
            [
                'master_biaya_id' => $jbUkt->id,
                'tahun_angkatan' => 2023,
                'program_studi_id' => $prodiTIId,
                'semester' => null,
                'jalur_kelas' => 'Karyawan',
                'nominal' => 4500000.00,
                'is_active' => true,
                'keterangan' => 'UKT Kelas Karyawan Angkatan 2023 Teknik Informatika',
            ],
            // Angkatan 2024 - Global Kampus - Reguler
            [
                'master_biaya_id' => $jbUkt->id,
                'tahun_angkatan' => 2024,
                'program_studi_id' => null,
                'semester' => null,
                'jalur_kelas' => 'Reguler',
                'nominal' => 3750000.00,
                'is_active' => true,
                'keterangan' => 'UKT Standar Angkatan 2024 (Semua Prodi)',
            ],
            // Angkatan 2025 - Global Kampus - Reguler
            [
                'master_biaya_id' => $jbUkt->id,
                'tahun_angkatan' => 2025,
                'program_studi_id' => null,
                'semester' => null,
                'jalur_kelas' => 'Reguler',
                'nominal' => 4000000.00,
                'is_active' => true,
                'keterangan' => 'UKT Standar Angkatan 2025 (Semua Prodi)',
            ],
        ];

        foreach ($settingTarifs as $st) {
            \App\Models\Sikeu\SettingTarif::updateOrCreate(
                [
                    'master_biaya_id' => $st['master_biaya_id'],
                    'tahun_angkatan' => $st['tahun_angkatan'],
                    'program_studi_id' => $st['program_studi_id'],
                    'semester' => $st['semester'],
                    'jalur_kelas' => $st['jalur_kelas'],
                ],
                $st
            );
        }
    }
}

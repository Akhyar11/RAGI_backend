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
        DB::table('sikeu_master_biaya')->truncate();
        DB::table('sikeu_unit_kas')->truncate();
        DB::table('sikeu_periode_akuntansi')->truncate();
        Schema::enableForeignKeyConstraints();

        // 1. Seed Master Biaya & Module Delegations
        $jbUkt = MasterBiaya::create([
            'kode' => 'UKT_REG',
            'nama' => 'Uang Kuliah Tunggal (UKT) Reguler',
            'tipe' => 'spp',
            'nominal_standar' => 3500000.00,
            'deskripsi' => 'Biaya pendidikan semesteran reguler mahasiswa',
            'is_recurring' => true,
            'is_active' => true,
        ]);
        MasterBiayaModule::create(['master_biaya_id' => $jbUkt->id, 'module_code' => 'siakad']);
        MasterBiayaModule::create(['master_biaya_id' => $jbUkt->id, 'module_code' => 'sikeu']);

        $jbSpmb = MasterBiaya::create([
            'kode' => 'SPMB_ADM',
            'nama' => 'Biaya Pendaftaran SPMB',
            'tipe' => 'spmb_adm',
            'nominal_standar' => 250000.00,
            'deskripsi' => 'Biaya formulir & ujian seleksi penerimaan mahasiswa baru',
            'is_recurring' => false,
            'is_active' => true,
        ]);
        MasterBiayaModule::create(['master_biaya_id' => $jbSpmb->id, 'module_code' => 'spmb']);
        MasterBiayaModule::create(['master_biaya_id' => $jbSpmb->id, 'module_code' => 'sikeu']);

        $jbWisuda = MasterBiaya::create([
            'kode' => 'WISUDA_FEE',
            'nama' => 'Biaya Kelulusan & Wisuda',
            'tipe' => 'wisuda',
            'nominal_standar' => 1500000.00,
            'deskripsi' => 'Biaya ijazah, toga, & upacara wisuda',
            'is_recurring' => false,
            'is_active' => true,
        ]);
        MasterBiayaModule::create(['master_biaya_id' => $jbWisuda->id, 'module_code' => 'siakad']);
        MasterBiayaModule::create(['master_biaya_id' => $jbWisuda->id, 'module_code' => 'sikeu']);



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
        PeriodeAkuntansi::create([
            'nama_periode' => 'Periode Agustus 2026',
            'tahun' => 2026,
            'bulan' => 8,
            'tanggal_mulai' => '2026-08-01',
            'tanggal_selesai' => '2026-08-31',
            'status' => 'terbuka',
        ]);
    }
}

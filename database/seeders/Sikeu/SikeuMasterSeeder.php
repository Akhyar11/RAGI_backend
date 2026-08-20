<?php

namespace Database\Seeders\Sikeu;

use Illuminate\Database\Seeder;
use App\Models\Sikeu\JenisBiaya;
use App\Models\Sikeu\TarifUkt;
use App\Models\Sikeu\Beasiswa;
use App\Models\Sikeu\UnitKas;
use App\Models\Sikeu\PeriodeAkuntansi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SikeuMasterSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('jenis_biaya')->truncate();
        DB::table('tarif_ukt')->truncate();
        DB::table('beasiswa')->truncate();
        DB::table('unit_kas')->truncate();
        DB::table('periode_akuntansi')->truncate();
        Schema::enableForeignKeyConstraints();

        // 1. Seed Jenis Biaya & Module Delegations
        $jbUkt = JenisBiaya::create([
            'kode' => 'UKT_REG',
            'nama' => 'Uang Kuliah Tunggal (UKT) Reguler',
            'tipe' => 'ukt',
            'nominal_standar' => 3500000.00,
            'deskripsi' => 'Biaya pendidikan semesteran reguler mahasiswa',
            'is_recurring' => true,
            'is_active' => true,
        ]);
        \App\Models\Sikeu\JenisBiayaModule::create(['jenis_biaya_id' => $jbUkt->id, 'module_code' => 'siakad']);
        \App\Models\Sikeu\JenisBiayaModule::create(['jenis_biaya_id' => $jbUkt->id, 'module_code' => 'sikeu']);

        $jbSpmb = JenisBiaya::create([
            'kode' => 'SPMB_ADM',
            'nama' => 'Biaya Pendaftaran SPMB',
            'tipe' => 'spmb_adm',
            'nominal_standar' => 250000.00,
            'deskripsi' => 'Biaya formulir & ujian seleksi penerimaan mahasiswa baru',
            'is_recurring' => false,
            'is_active' => true,
        ]);
        \App\Models\Sikeu\JenisBiayaModule::create(['jenis_biaya_id' => $jbSpmb->id, 'module_code' => 'spmb']);
        \App\Models\Sikeu\JenisBiayaModule::create(['jenis_biaya_id' => $jbSpmb->id, 'module_code' => 'sikeu']);

        $jbWisuda = JenisBiaya::create([
            'kode' => 'WISUDA_FEE',
            'nama' => 'Biaya Kelulusan & Wisuda',
            'tipe' => 'wisuda',
            'nominal_standar' => 1500000.00,
            'deskripsi' => 'Biaya ijazah, toga, & upacara wisuda',
            'is_recurring' => false,
            'is_active' => true,
        ]);
        \App\Models\Sikeu\JenisBiayaModule::create(['jenis_biaya_id' => $jbWisuda->id, 'module_code' => 'siakad']);
        \App\Models\Sikeu\JenisBiayaModule::create(['jenis_biaya_id' => $jbWisuda->id, 'module_code' => 'sikeu']);

        // 2. Seed Tarif UKT
        TarifUkt::create([
            'program_studi_id' => 1,
            'jenis_biaya_id' => $jbUkt->id,
            'tahun_akademik_id' => 1,
            'kelompok_ukt' => 1,
            'nominal' => 500000.00,
            'is_active' => true,
        ]);
        TarifUkt::create([
            'program_studi_id' => 1,
            'jenis_biaya_id' => $jbUkt->id,
            'tahun_akademik_id' => 1,
            'kelompok_ukt' => 2,
            'nominal' => 2500000.00,
            'is_active' => true,
        ]);
        TarifUkt::create([
            'program_studi_id' => 1,
            'jenis_biaya_id' => $jbUkt->id,
            'tahun_akademik_id' => 1,
            'kelompok_ukt' => 3,
            'nominal' => 5000000.00,
            'is_active' => true,
        ]);

        // 3. Seed Beasiswa
        Beasiswa::create([
            'kode' => 'KIP_KULIAH',
            'nama' => 'Beasiswa KIP Kuliah Pemerintah',
            'sumber' => 'pemerintah',
            'tipe_potongan' => 'persen',
            'nilai_potongan' => 100.00,
            'deskripsi' => 'Potongan 100% biaya UKT untuk mahasiswa penerima KIP-K',
            'is_active' => true,
        ]);
        Beasiswa::create([
            'kode' => 'PRESTASI_AKADEMIK',
            'nama' => 'Beasiswa Prestasi Akademik Kampus',
            'sumber' => 'internal',
            'tipe_potongan' => 'nominal',
            'nilai_potongan' => 1500000.00,
            'deskripsi' => 'Potongan nominal UKT untuk mahasiswa berprestasi',
            'is_active' => true,
        ]);

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

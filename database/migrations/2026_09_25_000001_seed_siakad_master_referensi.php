<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seed tipe + item master referensi modul SIAKAD.
     *
     * Sumber opsi dropdown master akademik (jenjang prodi, akreditasi,
     * tipe MK, tipe prasyarat, mode penilaian) agar frontend mengambil
     * langsung dari database, bukan literal di kode.
     * Kode item diselaraskan dengan enum validasi backend
     * (PDDIKTI / Neo Feeder) sehingga kontrak API tidak berubah.
     */
    public function up(): void
    {
        $now = now();

        $types = [
            ['kode' => 'jenjang_prodi', 'nama' => 'Jenjang Program Studi', 'modul' => 'siakad', 'deskripsi' => 'Strata pendidikan program studi sesuai PDDIKTI (D3 s.d. Profesi).', 'urutan' => 14],
            ['kode' => 'akreditasi_prodi', 'nama' => 'Peringkat Akreditasi Prodi', 'modul' => 'siakad', 'deskripsi' => 'Peringkat akreditasi BAN-PT / LAM program studi.', 'urutan' => 15],
            ['kode' => 'tipe_mk', 'nama' => 'Tipe Mata Kuliah', 'modul' => 'siakad', 'deskripsi' => 'Klasifikasi mata kuliah dalam kurikulum (wajib / pilihan).', 'urutan' => 16],
            ['kode' => 'tipe_prasyarat_mk', 'nama' => 'Kriteria Prasyarat MK', 'modul' => 'siakad', 'deskripsi' => 'Syarat menempuh mata kuliah (wajib lulus / pernah diambil).', 'urutan' => 17],
            ['kode' => 'mode_penilaian', 'nama' => 'Mode Penilaian Periode', 'modul' => 'siakad', 'deskripsi' => 'Metode evaluasi hasil belajar per periode (OBE / konvensional).', 'urutan' => 18],
        ];

        if (Schema::hasTable('core_tipe_referensi')) {
            foreach ($types as $t) {
                DB::table('core_tipe_referensi')->updateOrInsert(
                    ['kode' => $t['kode']],
                    array_merge($t, ['is_active' => true, 'created_at' => $now, 'updated_at' => $now])
                );
            }
        }

        $items = [
            'jenjang_prodi' => [
                ['kode' => 'D3', 'nama' => 'Diploma 3 (D3)'],
                ['kode' => 'D4', 'nama' => 'Sarjana Terapan (D4)'],
                ['kode' => 'S1', 'nama' => 'Sarjana (S1)'],
                ['kode' => 'S2', 'nama' => 'Magister (S2)'],
                ['kode' => 'S3', 'nama' => 'Doktor (S3)'],
                ['kode' => 'Profesi', 'nama' => 'Profesi'],
            ],
            'akreditasi_prodi' => [
                ['kode' => 'Unggul', 'nama' => 'Unggul (A)'],
                ['kode' => 'Baik Sekali', 'nama' => 'Baik Sekali (B)'],
                ['kode' => 'Baik', 'nama' => 'Baik (C)'],
                ['kode' => 'Terakreditasi Sementara', 'nama' => 'Terakreditasi Sementara'],
            ],
            'tipe_mk' => [
                ['kode' => 'wajib', 'nama' => 'Wajib Program Studi'],
                ['kode' => 'wajib_prodi', 'nama' => 'Wajib Prodi (Kekhasan)'],
                ['kode' => 'pilihan', 'nama' => 'Pilihan Bebas'],
            ],
            'tipe_prasyarat_mk' => [
                ['kode' => 'lulus', 'nama' => 'Wajib Lulus (Nilai Minimal)'],
                ['kode' => 'pernah_ambil', 'nama' => 'Pernah Diambil (Cukup Terdaftar)'],
            ],
            'mode_penilaian' => [
                ['kode' => 'full_obe', 'nama' => 'Pure OBE (CPMK)'],
                ['kode' => 'semi_obe', 'nama' => 'Hybrid OBE (UTS/UAS)'],
                ['kode' => 'konvensional', 'nama' => 'Konvensional'],
            ],
        ];

        if (Schema::hasTable('spmb_master_referensi')) {
            foreach ($items as $tipe => $rows) {
                foreach (array_values($rows) as $i => $row) {
                    DB::table('spmb_master_referensi')->updateOrInsert(
                        ['modul' => 'siakad', 'tipe' => $tipe, 'kode' => $row['kode']],
                        [
                            'nama' => $row['nama'],
                            'urutan' => $i + 1,
                            'is_active' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tipes = ['jenjang_prodi', 'akreditasi_prodi', 'tipe_mk', 'tipe_prasyarat_mk', 'mode_penilaian'];

        if (Schema::hasTable('spmb_master_referensi')) {
            DB::table('spmb_master_referensi')
                ->where('modul', 'siakad')
                ->whereIn('tipe', $tipes)
                ->delete();
        }

        if (Schema::hasTable('core_tipe_referensi')) {
            DB::table('core_tipe_referensi')->whereIn('kode', $tipes)->delete();
        }
    }
};

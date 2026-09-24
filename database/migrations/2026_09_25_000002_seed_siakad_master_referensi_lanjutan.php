<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * - Lebarkan kolom akreditasi prodi (20 -> 50) agar menampung
     *   peringkat "Terakreditasi Sementara" dari master referensi.
     * - Seed tipe + item master referensi SIAKAD lanjutan
     *   (kategori CPL, teknik penilaian, hari kuliah,
     *   status absensi, predikat kelulusan).
     */
    public function up(): void
    {
        if (Schema::hasTable('spmb_master_program_studi') && Schema::hasColumn('spmb_master_program_studi', 'akreditasi')) {
            Schema::table('spmb_master_program_studi', function (Blueprint $table) {
                $table->string('akreditasi', 50)->nullable()->change();
            });
        }

        $now = now();

        $types = [
            ['kode' => 'kategori_cpl', 'nama' => 'Kategori CPL', 'modul' => 'siakad', 'deskripsi' => 'Rumpun capaian pembelajaran lulusan (Sikap, Pengetahuan, Keterampilan).', 'urutan' => 19],
            ['kode' => 'teknik_penilaian', 'nama' => 'Teknik Penilaian', 'modul' => 'siakad', 'deskripsi' => 'Metode asesmen komponen penilaian kelas.', 'urutan' => 20],
            ['kode' => 'hari_kuliah', 'nama' => 'Hari Kuliah', 'modul' => 'siakad', 'deskripsi' => 'Hari penyelenggaraan tatap muka perkuliahan.', 'urutan' => 21],
            ['kode' => 'status_absensi', 'nama' => 'Status Absensi', 'modul' => 'siakad', 'deskripsi' => 'Status kehadiran mahasiswa per pertemuan.', 'urutan' => 22],
            ['kode' => 'predikat_kelulusan', 'nama' => 'Predikat Kelulusan', 'modul' => 'siakad', 'deskripsi' => 'Predikat yudisium kelulusan mahasiswa.', 'urutan' => 23],
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
            'kategori_cpl' => [
                ['kode' => 'sikap', 'nama' => 'Sikap & Tata Nilai (S)'],
                ['kode' => 'pengetahuan', 'nama' => 'Penguasaan Pengetahuan (P)'],
                ['kode' => 'keterampilan_umum', 'nama' => 'Keterampilan Umum (KU)'],
                ['kode' => 'keterampilan_khusus', 'nama' => 'Keterampilan Khusus (KK)'],
            ],
            'teknik_penilaian' => [
                ['kode' => 'tes_tulis', 'nama' => 'Tes Tulis (UTS/UAS)'],
                ['kode' => 'tes_lisan', 'nama' => 'Tes Lisan'],
                ['kode' => 'proyek', 'nama' => 'Proyek (PBL)'],
                ['kode' => 'praktikum', 'nama' => 'Praktikum Laboratorium'],
                ['kode' => 'unjuk_kerja', 'nama' => 'Unjuk Kerja'],
                ['kode' => 'portofolio', 'nama' => 'Portofolio'],
                ['kode' => 'partisipatif', 'nama' => 'Partisipatif'],
                ['kode' => 'tugas', 'nama' => 'Tugas Terstruktur'],
                ['kode' => 'kuis', 'nama' => 'Kuis Formatif'],
                ['kode' => 'lainnya', 'nama' => 'Lainnya'],
            ],
            'hari_kuliah' => [
                ['kode' => 'senin', 'nama' => 'Senin'],
                ['kode' => 'selasa', 'nama' => 'Selasa'],
                ['kode' => 'rabu', 'nama' => 'Rabu'],
                ['kode' => 'kamis', 'nama' => 'Kamis'],
                ['kode' => 'jumat', 'nama' => 'Jumat'],
                ['kode' => 'sabtu', 'nama' => 'Sabtu'],
                ['kode' => 'minggu', 'nama' => 'Minggu'],
            ],
            'status_absensi' => [
                ['kode' => 'hadir', 'nama' => 'Hadir'],
                ['kode' => 'sakit', 'nama' => 'Sakit'],
                ['kode' => 'izin', 'nama' => 'Izin'],
                ['kode' => 'alfa', 'nama' => 'Alfa (Tanpa Keterangan)'],
            ],
            'predikat_kelulusan' => [
                ['kode' => 'memuaskan', 'nama' => 'Memuaskan'],
                ['kode' => 'sangat_memuaskan', 'nama' => 'Sangat Memuaskan'],
                ['kode' => 'dengan_pujian', 'nama' => 'Dengan Pujian'],
                ['kode' => 'cum_laude', 'nama' => 'Cum Laude'],
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
        $tipes = ['kategori_cpl', 'teknik_penilaian', 'hari_kuliah', 'status_absensi', 'predikat_kelulusan'];

        if (Schema::hasTable('spmb_master_referensi')) {
            DB::table('spmb_master_referensi')
                ->where('modul', 'siakad')
                ->whereIn('tipe', $tipes)
                ->delete();
        }

        if (Schema::hasTable('core_tipe_referensi')) {
            DB::table('core_tipe_referensi')->whereIn('kode', $tipes)->delete();
        }

        if (Schema::hasTable('spmb_master_program_studi') && Schema::hasColumn('spmb_master_program_studi', 'akreditasi')) {
            Schema::table('spmb_master_program_studi', function (Blueprint $table) {
                $table->string('akreditasi', 20)->nullable()->change();
            });
        }
    }
};

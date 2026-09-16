<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SimpegKompetensiMasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Jenis Sertifikasi
        $jenisSertifikasi = [
            [
                'nama' => 'Sertifikasi Pendidik (Serdos)',
                'kode' => 'SERDOS',
                'deskripsi' => 'Sertifikasi pendidik resmi untuk dosen dari Kemendikbudristek',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Sertifikasi Profesi',
                'kode' => 'PROFESI',
                'deskripsi' => 'Sertifikasi profesi resmi (Insinyur, Akuntan, Advokat, Dokter, dll.)',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Sertifikasi BNSP / LSP',
                'kode' => 'BNSP',
                'deskripsi' => 'Sertifikasi kompetensi kerja dari Badan Nasional Sertifikasi Profesi',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Sertifikasi Kompetensi Industri',
                'kode' => 'INDUSTRI',
                'deskripsi' => 'Sertifikasi vendor internasional/industri (Cisco, Microsoft, Oracle, AWS, Google, dll.)',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($jenisSertifikasi as $item) {
            DB::table('simpeg_master_jenis_sertifikasi')->updateOrInsert(
                ['kode' => $item['kode']],
                $item
            );
        }

        // 2. Jenis Tes
        $jenisTes = [
            [
                'nama' => 'TOEFL ITP',
                'kode' => 'TOEFL_ITP',
                'kategori' => 'bahasa',
                'skor_min' => 310,
                'skor_max' => 677,
                'deskripsi' => 'Institutional Testing Program oleh ETS',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'TOEFL iBT',
                'kode' => 'TOEFL_IBT',
                'kategori' => 'bahasa',
                'skor_min' => 0,
                'skor_max' => 120,
                'deskripsi' => 'Internet-based Test TOEFL',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'IELTS',
                'kode' => 'IELTS',
                'kategori' => 'bahasa',
                'skor_min' => 0,
                'skor_max' => 9.0,
                'deskripsi' => 'International English Language Testing System',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'TKDA (Tes Kemampuan Dasar Akademik)',
                'kode' => 'TKDA',
                'kategori' => 'potensi_akademik',
                'skor_min' => 200,
                'skor_max' => 800,
                'deskripsi' => 'Tes potensi akademik resmi PLTI / Serdos',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'TKBI (Tes Kemampuan Bahasa Inggris)',
                'kode' => 'TKBI',
                'kategori' => 'bahasa',
                'skor_min' => 0,
                'skor_max' => 100,
                'deskripsi' => 'Tes bahasa inggris resmi PLTI / Serdos',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'TPA Bappenas',
                'kode' => 'TPA_BAPPENAS',
                'kategori' => 'potensi_akademik',
                'skor_min' => 200,
                'skor_max' => 800,
                'deskripsi' => 'Tes Potensi Akademik resmi OTO Bappenas',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($jenisTes as $item) {
            DB::table('simpeg_master_jenis_tes')->updateOrInsert(
                ['kode' => $item['kode']],
                $item
            );
        }

        // 3. Jenis Pelatihan
        $jenisPelatihan = [
            'Pelatihan Pekerti',
            'Applied Approach (AA)',
            'Workshop Kurikulum Berbasis OBE',
            'Diklat Kepemimpinan & Tata Kelola',
            'Seminar / Konferensi Ilmiah',
            'Pelatihan Teknis & Vokasi Industri',
            'Workshop Penulisan Jurnal Bereputasi',
            'Pelatihan Metodologi Penelitian & Pengabdian',
        ];

        foreach ($jenisPelatihan as $nama) {
            DB::table('simpeg_master_jenis_pelatihan')->updateOrInsert(
                ['nama' => $nama],
                [
                    'deskripsi' => 'Kategori pelatihan pengembangan dosen dan tenaga kependidikan',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 4. Peran Pelatihan
        $peranPelatihan = [
            'Peserta',
            'Narasumber / Pemateri',
            'Moderator',
            'Instruktur / Fasilitator',
            'Panitia Pelaksana',
        ];

        foreach ($peranPelatihan as $nama) {
            DB::table('simpeg_master_peran_pelatihan')->updateOrInsert(
                ['nama' => $nama],
                [
                    'deskripsi' => 'Peran dalam kegiatan pelatihan/seminar',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 5. Tingkat Kegiatan
        $tingkatKegiatan = [
            'Institusi / Internal Kampus',
            'Lokal / Kabupaten / Kota',
            'Regional / Wilayah',
            'Nasional',
            'Internasional',
        ];

        foreach ($tingkatKegiatan as $nama) {
            DB::table('simpeg_master_tingkat_kegiatan')->updateOrInsert(
                ['nama' => $nama],
                [
                    'deskripsi' => 'Cakupan level skala kegiatan',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}

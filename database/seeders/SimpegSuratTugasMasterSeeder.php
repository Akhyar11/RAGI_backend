<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SimpegSuratTugasMasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Jenis Transportasi
        $transportasi = [
            [
                'nama' => 'Mobil Dinas / Operasional Kampus',
                'kode' => 'MOBIL_DINAS',
                'deskripsi' => 'Kendaraan operasional inventaris kampus dengan atau tanpa penugasan sopir',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Mobil Pribadi',
                'kode' => 'MOBIL_PRIBADI',
                'deskripsi' => 'Kendaraan roda empat milik pribadi dengan penggantian BBM/tol',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Sepeda Motor Dinas / Pribadi',
                'kode' => 'MOTOR',
                'deskripsi' => 'Kendaraan roda dua untuk penugasan jarak dekat/menengah',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Pesawat Terbang Komersial',
                'kode' => 'PESAWAT',
                'deskripsi' => 'Transportasi udara untuk penugasan antarpulau atau internasional',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Kereta Api',
                'kode' => 'KERETA',
                'deskripsi' => 'Transportasi kereta api antarkota (Eksekutif/Bisnis)',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Kendaraan Sewa / Rental / Travel',
                'kode' => 'SEWA',
                'deskripsi' => 'Kendaraan sewa untuk rombongan tugas dinas luar kota',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Bus / Angkutan Umum',
                'kode' => 'BUS',
                'deskripsi' => 'Angkutan umum massal',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($transportasi as $item) {
            DB::table('simpeg_master_jenis_transportasi')->updateOrInsert(
                ['kode' => $item['kode']],
                $item
            );
        }

        // 2. Kategori Kegiatan Penugasan
        $kategori = [
            'Tugas Kedinasan & Kelembagaan Resmi',
            'Kunjungan Institusi & Studi Banding',
            'Pelatihan, Diklat & Workshop Eksternal',
            'Konferensi & Seminar Ilmiah Nasional/Internasional',
            'Monitoring, Evaluasi & Asesmen Akreditasi',
            'Pengabdian Masyarakat, KKN & Pendampingan',
            'Kerjasama, Kemitraan & Penjajakan Industri',
            'Uji Kompetensi & Asesmen Luar Kampus',
        ];

        foreach ($kategori as $nama) {
            DB::table('simpeg_master_kategori_kegiatan_tugas')->updateOrInsert(
                ['nama' => $nama],
                [
                    'deskripsi' => 'Klasifikasi kegiatan penugasan dinas dosen dan pegawai',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}

<?php

namespace Database\Seeders\Sippm;

use Illuminate\Database\Seeder;
use App\Models\Sippm\SkemaKegiatan;

class SippmSkemaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $skemaList = [
            [
                'kode' => 'SKM-PEN-PDM',
                'nama' => 'Penelitian Dosen Muda (PDP)',
                'tipe' => 'penelitian',
                'sumber_dana' => 'internal',
                'maksimal_anggaran' => 15000000.00,
                'deskripsi' => 'Skema hibah pembinaan peneliti muda perguruan tinggi untuk menginisiasi rekam jejak riset.',
                'is_active' => true,
            ],
            [
                'kode' => 'SKM-PEN-PD',
                'nama' => 'Penelitian Dasar (PD)',
                'tipe' => 'penelitian',
                'sumber_dana' => 'internal',
                'maksimal_anggaran' => 35000000.00,
                'deskripsi' => 'Riset berfokus pada pengembangan IPTEKS, teori baru, dan publikasi bereputasi.',
                'is_active' => true,
            ],
            [
                'kode' => 'SKM-PEN-PT',
                'nama' => 'Penelitian Terapan (PT / BIMA)',
                'tipe' => 'penelitian',
                'sumber_dana' => 'dikti_bima',
                'maksimal_anggaran' => 75000000.00,
                'deskripsi' => 'Riset terapan berorientasi produk/teknologi tepat guna nasional terdaftar di portal BIMA.',
                'is_active' => true,
            ],
            [
                'kode' => 'SKM-PEN-PP',
                'nama' => 'Penelitian Pengembangan (PP)',
                'tipe' => 'penelitian',
                'sumber_dana' => 'mitra_industri',
                'maksimal_anggaran' => 100000000.00,
                'deskripsi' => 'Riset komersialisasi dan penyempurnaan prototipe bekerja sama dengan DUDI.',
                'is_active' => true,
            ],
            [
                'kode' => 'SKM-PKM-PPM',
                'nama' => 'Pemberdayaan Kemitraan Masyarakat (PKM)',
                'tipe' => 'pengabdian',
                'sumber_dana' => 'internal',
                'maksimal_anggaran' => 20000000.00,
                'deskripsi' => 'Pemberdayaan masyarakat berbasis penerapan IPTEKS dan teknologi tepat guna.',
                'is_active' => true,
            ],
            [
                'kode' => 'SKM-PKM-PDM',
                'nama' => 'Pemberdayaan Desa Mitra (PDM)',
                'tipe' => 'pengabdian',
                'sumber_dana' => 'internal',
                'maksimal_anggaran' => 30000000.00,
                'deskripsi' => 'Program pengabdian wilayah secara berkelanjutan di kawasan desa binaan kampus.',
                'is_active' => true,
            ],
        ];

        foreach ($skemaList as $skema) {
            SkemaKegiatan::updateOrCreate(
                ['kode' => $skema['kode']],
                $skema
            );
        }
    }
}

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
                'kode' => 'SKM-PEN-PDP',
                'nama' => 'Penelitian Dosen Pemula (PDP)',
                'tipe' => 'penelitian',
                'sumber_dana' => 'internal',
                'maksimal_anggaran' => 15000000.00,
                'deskripsi' => 'Skema hibah pembinaan peneliti muda perguruan tinggi untuk menginisiasi rekam jejak riset.',
                'is_active' => true,
            ],
            [
                'kode' => 'SKM-PEN-PDU',
                'nama' => 'Penelitian Dasar Unggulan Kampus (PDUK)',
                'tipe' => 'penelitian',
                'sumber_dana' => 'internal',
                'maksimal_anggaran' => 35000000.00,
                'deskripsi' => 'Riset berfokus pada pengembangan IPTEKS dan publikasi internasional bereputasi.',
                'is_active' => true,
            ],
            [
                'kode' => 'SKM-PEN-BIMA',
                'nama' => 'Hibah Riset Terapan BIMA Kemendikbudristek',
                'tipe' => 'penelitian',
                'sumber_dana' => 'dikti_bima',
                'maksimal_anggaran' => 75000000.00,
                'deskripsi' => 'Hibah penelitian berskala nasional yang didanai melalui portal BIMA Kemendikbudristek.',
                'is_active' => true,
            ],
            [
                'kode' => 'SKM-PKM-PPDM',
                'nama' => 'Pengabdian Masyarakat Desa Mitra (PPDM)',
                'tipe' => 'pengabdian',
                'sumber_dana' => 'internal',
                'maksimal_anggaran' => 20000000.00,
                'deskripsi' => 'Pemberdayaan masyarakat berbasis teknologi tepat guna di kawasan desa binaan kampus.',
                'is_active' => true,
            ],
            [
                'kode' => 'SKM-PKM-INDUSTRI',
                'nama' => 'Pengabdian Kemitraan DUDI / Industri',
                'tipe' => 'pengabdian',
                'sumber_dana' => 'mitra_industri',
                'maksimal_anggaran' => 50000000.00,
                'deskripsi' => 'Program pengabdian masyarakat berkolaborasi dengan mitra dunia usaha dan industri.',
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

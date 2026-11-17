<?php

namespace Database\Seeders\Simpeg;

use App\Models\Simpeg\MasterKategoriKebijakan;
use Illuminate\Database\Seeder;

class SimpegMasterKategoriKebijakanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'kode' => 'PERATURAN',
                'nama' => 'Peraturan',
                'deskripsi' => 'Peraturan Yayasan, Rektor, atau Kebijakan Institusi Tingkat Tertinggi',
                'urutan' => 1,
                'is_active' => true,
            ],
            [
                'kode' => 'SK',
                'nama' => 'Surat Keputusan (SK)',
                'deskripsi' => 'Surat Keputusan Pimpinan Terkait Kebijakan & Ketentuan Kepegawaian',
                'urutan' => 2,
                'is_active' => true,
            ],
            [
                'kode' => 'PEDOMAN',
                'nama' => 'Pedoman & Panduan',
                'deskripsi' => 'Buku Pedoman, Panduan Teknis, atau Tata Tertib Kepegawaian',
                'urutan' => 3,
                'is_active' => true,
            ],
            [
                'kode' => 'EDARAN',
                'nama' => 'Surat Edaran (SE)',
                'deskripsi' => 'Surat Edaran Resmi Terkait Pemberitahuan atau Kebijakan Berkala',
                'urutan' => 4,
                'is_active' => true,
            ],
            [
                'kode' => 'SOP',
                'nama' => 'Standar Operasional Prosedur (SOP)',
                'deskripsi' => 'SOP Alur Layanan dan Operasional SDM / Kepegawaian',
                'urutan' => 5,
                'is_active' => true,
            ],
            [
                'kode' => 'INSTRUKSI',
                'nama' => 'Instruksi Kerja / Petunjuk Teknis',
                'deskripsi' => 'Instruksi Kerja, Format Baku, dan Juknis Pelaksanaan Tugas Pegawai',
                'urutan' => 6,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $cat) {
            MasterKategoriKebijakan::updateOrCreate(
                ['kode' => $cat['kode']],
                $cat
            );
        }
    }
}

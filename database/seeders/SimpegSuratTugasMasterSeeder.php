<?php

namespace Database\Seeders;

use App\Models\Simpeg\MasterJenisTransportasi;
use App\Models\Simpeg\MasterKategoriKegiatanTugas;
use Illuminate\Database\Seeder;

class SimpegSuratTugasMasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kategoriList = [
            [
                'nama' => 'Konsorsium / Pertemuan Ilmiah',
                'deskripsi' => 'Seminar, lokakarya, atau konferensi ilmiah',
                'urutan' => 1,
                'is_active' => true,
            ],
            [
                'nama' => 'Monitoring dan Evaluasi',
                'deskripsi' => 'Kunjungan kerja pengawasan, akreditasi, atau audit',
                'urutan' => 2,
                'is_active' => true,
            ],
            [
                'nama' => 'Studi Banding & Kerjasama',
                'deskripsi' => 'Kunjungan benchmarking dan penandatanganan MoU',
                'urutan' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($kategoriList as $kategori) {
            MasterKategoriKegiatanTugas::updateOrCreate(
                ['nama' => $kategori['nama']],
                $kategori
            );
        }

        $transportasiList = [
            [
                'kode' => 'MOBIL_DINAS',
                'nama' => 'Mobil Dinas Kampus',
                'deskripsi' => 'Armada mobil operasional kampus',
                'urutan' => 1,
                'is_active' => true,
            ],
            [
                'kode' => 'PESAWAT',
                'nama' => 'Pesawat Terbang',
                'deskripsi' => 'Penerbangan komersil',
                'urutan' => 2,
                'is_active' => true,
            ],
            [
                'kode' => 'KERETA',
                'nama' => 'Kereta Api',
                'deskripsi' => 'Transportasi kereta api antar kota',
                'urutan' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($transportasiList as $transport) {
            MasterJenisTransportasi::updateOrCreate(
                ['kode' => $transport['kode']],
                $transport
            );
        }
    }
}

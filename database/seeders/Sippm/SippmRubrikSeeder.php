<?php

namespace Database\Seeders\Sippm;

use Illuminate\Database\Seeder;
use App\Models\Sippm\RubrikIndikator;

class SippmRubrikSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Truncate existing kaprodi rubriks to ensure clean 7 criteria list
        RubrikIndikator::where('tipe_reviewer', 'kaprodi')->delete();

        $rubriks = [
            // Tahap 1: Kaprodi (Integrasi Penelitian dan Pengabdian Kepada Masyarakat - 7 Kriteria)
            [
                'tipe_reviewer' => 'kaprodi',
                'nama_indikator' => 'Dosen memiliki roadmap PPM',
                'deskripsi' => 'Ketersediaan roadmap PPM dosen pengusul (Pilihan: Sudah Memiliki / Belum Memiliki).',
                'bobot' => 14.00,
                'skor_minimal_default' => 80.00,
                'is_active' => true,
            ],
            [
                'tipe_reviewer' => 'kaprodi',
                'nama_indikator' => 'Kesesuaian PPM dengan roadmap',
                'deskripsi' => 'Kesesuaian topik usulan PPM dengan roadmap (Pilihan: Sudah Sesuai / Belum Sesuai).',
                'bobot' => 14.00,
                'skor_minimal_default' => 80.00,
                'is_active' => true,
            ],
            [
                'tipe_reviewer' => 'kaprodi',
                'nama_indikator' => 'Judul',
                'deskripsi' => 'Judul usulan proposal (Terisi otomatis dari sistem).',
                'bobot' => 14.00,
                'skor_minimal_default' => 80.00,
                'is_active' => true,
            ],
            [
                'tipe_reviewer' => 'kaprodi',
                'nama_indikator' => 'Bentuk integrasi hasil PPM dengan mata kuliah',
                'deskripsi' => 'Bentuk/modalitas integrasi hasil PPM ke dalam kurikulum & bahan ajar mata kuliah.',
                'bobot' => 14.00,
                'skor_minimal_default' => 80.00,
                'is_active' => true,
            ],
            [
                'tipe_reviewer' => 'kaprodi',
                'nama_indikator' => 'Luaran',
                'deskripsi' => 'Rencana/target luaran hasil PPM (Pilihan: Publikasi, Buku, HKI, dll).',
                'bobot' => 14.00,
                'skor_minimal_default' => 80.00,
                'is_active' => true,
            ],
            [
                'tipe_reviewer' => 'kaprodi',
                'nama_indikator' => 'Mata kuliah yang diintegrasikan',
                'deskripsi' => 'Mata kuliah wadah integrasi (Pilihan dari daftar mata kuliah prodi).',
                'bobot' => 15.00,
                'skor_minimal_default' => 80.00,
                'is_active' => true,
            ],
            [
                'tipe_reviewer' => 'kaprodi',
                'nama_indikator' => 'Bukti integrasi PPM dalam pembelajaran (RPS, PPT/ Buku Ajar/ Video, dll) *) berupa link drive',
                'deskripsi' => 'Kelengkapan dan keabsahan tautan drive bukti fisik RPS/modul/video pembelajaran.',
                'bobot' => 15.00,
                'skor_minimal_default' => 80.00,
                'is_active' => true,
            ],

            // Tahap 2: Admin SIPPM (Administrasi & Kelayakan Kelompok)
            [
                'tipe_reviewer' => 'admin',
                'nama_indikator' => 'Kesesuaian Format & Kelengkapan Administrasi Dokumen',
                'deskripsi' => 'Kelengkapan berkas pendukung, lembar pengesahan, serta kepatuhan terhadap panduan penulisan hibah.',
                'bobot' => 25.00,
                'skor_minimal_default' => 80.00,
                'is_active' => true,
            ],
            [
                'tipe_reviewer' => 'admin',
                'nama_indikator' => 'Kesesuaian Justifikasi Rancangan Anggaran Biaya (RAB)',
                'deskripsi' => 'Rasionalitas kelayakan honorarium, operasional, serta kesesuaian plafon anggaran skema hibah.',
                'bobot' => 25.00,
                'skor_minimal_default' => 80.00,
                'is_active' => true,
            ],
            [
                'tipe_reviewer' => 'admin',
                'nama_indikator' => 'Ketercapaian Target TKT & Rencana Luaran Hibah',
                'deskripsi' => 'Kejelasan target luaran wajib (jurnal, HKI, buku) dan kesesuaian tingkat kesiapan teknologi (TKT).',
                'bobot' => 25.00,
                'skor_minimal_default' => 80.00,
                'is_active' => true,
            ],
            [
                'tipe_reviewer' => 'admin',
                'nama_indikator' => 'Integrasi Mata Kuliah SIAKAD & Keterlibatan Anggota',
                'deskripsi' => 'Keterlibatan Mahasiswa/Tendik/Dosen Eksternal serta kejelasan konversi mata kuliah SIAKAD.',
                'bobot' => 25.00,
                'skor_minimal_default' => 80.00,
                'is_active' => true,
            ],
        ];

        foreach ($rubriks as $r) {
            RubrikIndikator::updateOrCreate(
                [
                    'tipe_reviewer' => $r['tipe_reviewer'],
                    'nama_indikator' => $r['nama_indikator'],
                ],
                $r
            );
        }
    }
}

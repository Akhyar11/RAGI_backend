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
        $rubriks = [
            // Tahap 1: Kaprodi (Keilmuan & Linieritas)
            [
                'tipe_reviewer' => 'kaprodi',
                'nama_indikator' => 'Linieritas Topik Riset / Pengabdian dengan Program Studi',
                'deskripsi' => 'Kesesuaian fokus riset/pengabdian yang diajukan dengan roadmap penelitian dan bidang keahlian di Program Studi.',
                'bobot' => 35.00,
                'skor_minimal_default' => 80.00,
                'is_active' => true,
            ],
            [
                'tipe_reviewer' => 'kaprodi',
                'nama_indikator' => 'Urgensi Kebijakan & Isu Strategis Keilmuan Prodi',
                'deskripsi' => 'Tingkat urgensi, kebaruan (novelty), serta kontribusi solusi yang ditawarkan terhadap bidang keilmuan prodi.',
                'bobot' => 35.00,
                'skor_minimal_default' => 80.00,
                'is_active' => true,
            ],
            [
                'tipe_reviewer' => 'kaprodi',
                'nama_indikator' => 'Kesesuaian Rekam Jejak / Kepakaran Dosen Pengusul',
                'deskripsi' => 'Kesesuaian kepakaran dan publikasi terdahulu Ketua Pengusul dengan topik kegiatan.',
                'bobot' => 30.00,
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

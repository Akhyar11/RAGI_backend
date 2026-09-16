<?php

namespace Database\Seeders;

use App\Models\Simpeg\MasterJenisIzinJamKerja;
use App\Models\Simpeg\MasterKategoriSk;
use Illuminate\Database\Seeder;

class SimpegIzinDanSkMasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Master Jenis Izin Jam Kerja (Parsial)
        $jenisIzin = [
            [
                'nama' => 'Izin Keluar Kampus Sementara (Kedinasan)',
                'kode' => 'KELUAR_DINAS',
                'tipe_potongan' => 'tidak_potong',
                'deskripsi' => 'Meninggalkan kampus sementara waktu pada jam kerja untuk urusan dinas/kampus (bank, koordinasi mitra, dll).',
                'urutan' => 1,
                'is_active' => true,
            ],
            [
                'nama' => 'Izin Keluar Kampus Sementara (Pribadi Mendesak)',
                'kode' => 'KELUAR_PRIBADI',
                'tipe_potongan' => 'tidak_potong',
                'deskripsi' => 'Meninggalkan kampus sementara waktu pada jam kerja untuk urusan pribadi yang sangat mendesak/darurat.',
                'urutan' => 2,
                'is_active' => true,
            ],
            [
                'nama' => 'Izin Datang Terlambat',
                'kode' => 'TERLAMBAT',
                'tipe_potongan' => 'tidak_potong',
                'deskripsi' => 'Izin dispensasi keterlambatan hadir karena kendala lalu lintas, tugas pagi di luar, atau alasan terduga.',
                'urutan' => 3,
                'is_active' => true,
            ],
            [
                'nama' => 'Izin Pulang Lebih Awal',
                'kode' => 'PULANG_AWAL',
                'tipe_potongan' => 'tidak_potong',
                'deskripsi' => 'Izin dispensasi meninggalkan kampus sebelum jam kepulangan resmi karena kondisi darurat/kesehatan.',
                'urutan' => 4,
                'is_active' => true,
            ],
        ];

        foreach ($jenisIzin as $item) {
            MasterJenisIzinJamKerja::updateOrCreate(['kode' => $item['kode']], $item);
        }

        // 2. Master Kategori SK Pegawai
        $kategoriSk = [
            [
                'nama' => 'SK Pengangkatan Pegawai',
                'kode' => 'PENGANGKATAN',
                'deskripsi' => 'Surat keputusan pengangkatan calon pegawai, pegawai tetap, atau pegawai kontrak.',
                'urutan' => 1,
                'is_active' => true,
            ],
            [
                'nama' => 'SK Jabatan Struktural',
                'kode' => 'JABATAN_STRUKTURAL',
                'deskripsi' => 'Surat keputusan pengangkatan dalam jabatan manajerial/pimpinan kampus (Dekan, Kaprodi, dll).',
                'urutan' => 2,
                'is_active' => true,
            ],
            [
                'nama' => 'SK Jabatan Fungsional (Jafung)',
                'kode' => 'JAFUNG',
                'deskripsi' => 'Surat keputusan penetapan jenjang akademik dosen (Asisten Ahli, Lektor, Lektor Kepala, Guru Besar).',
                'urutan' => 3,
                'is_active' => true,
            ],
            [
                'nama' => 'SK Penugasan Mengajar',
                'kode' => 'MENGAJAR',
                'deskripsi' => 'Surat keputusan beban penugasan mengajar per semester dari pimpinan fakultas/institut.',
                'urutan' => 4,
                'is_active' => true,
            ],
            [
                'nama' => 'SK Pembimbingan & Pengujian Akademik',
                'kode' => 'BIMBINGAN_UJI',
                'deskripsi' => 'Surat keputusan penugasan sebagai dosen pembimbing atau penguji skripsi, tesis, dan disertasi.',
                'urutan' => 5,
                'is_active' => true,
            ],
            [
                'nama' => 'SK Kepanitiaan & Tim Kerja (Ad-Hoc)',
                'kode' => 'KEPANITIAAN',
                'deskripsi' => 'Surat keputusan penugasan kepanitiaan wisuda, akreditasi, SPMB, dan task force lainnya.',
                'urutan' => 6,
                'is_active' => true,
            ],
            [
                'nama' => 'SK Kenaikan Gaji Berkala (KGB) / Golongan',
                'kode' => 'KGB_GOLONGAN',
                'deskripsi' => 'Surat keputusan kenaikan gaji berkala atau kenaikan pangkat golongan kepegawaian.',
                'urutan' => 7,
                'is_active' => true,
            ],
        ];

        foreach ($kategoriSk as $item) {
            MasterKategoriSk::updateOrCreate(['kode' => $item['kode']], $item);
        }
    }
}

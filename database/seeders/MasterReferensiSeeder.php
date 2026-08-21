<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\System\MasterReferensi;

class MasterReferensiSeeder extends Seeder
{
    public function run(): void
    {
        $referensi = [
            // Status Sipil
            ['tipe' => 'status_sipil', 'kode' => 'Belum Kawin', 'nama' => 'Belum Kawin'],
            ['tipe' => 'status_sipil', 'kode' => 'Kawin', 'nama' => 'Kawin'],
            ['tipe' => 'status_sipil', 'kode' => 'Janda', 'nama' => 'Janda'],
            ['tipe' => 'status_sipil', 'kode' => 'Duda', 'nama' => 'Duda'],

            // Agama
            ['tipe' => 'agama', 'kode' => 'Islam', 'nama' => 'Islam'],
            ['tipe' => 'agama', 'kode' => 'Kristen', 'nama' => 'Kristen'],
            ['tipe' => 'agama', 'kode' => 'Katolik', 'nama' => 'Katolik'],
            ['tipe' => 'agama', 'kode' => 'Hindu', 'nama' => 'Hindu'],
            ['tipe' => 'agama', 'kode' => 'Buddha', 'nama' => 'Buddha'],
            ['tipe' => 'agama', 'kode' => 'Konghucu', 'nama' => 'Konghucu'],

            // Asal Lulusan
            ['tipe' => 'asal_lulusan', 'kode' => 'sekolah', 'nama' => 'SMA/SMK/MA/Sederajat'],
            ['tipe' => 'asal_lulusan', 'kode' => 'pt', 'nama' => 'Perguruan Tinggi (Transfer)'],

            // Jenis PT
            ['tipe' => 'jenis_pt', 'kode' => 'komputer', 'nama' => 'Komputer'],
            ['tipe' => 'jenis_pt', 'kode' => 'non-komputer', 'nama' => 'Non Komputer'],

            // Jenjang PT
            ['tipe' => 'jenjang_pt', 'kode' => 'D1', 'nama' => 'D1'],
            ['tipe' => 'jenjang_pt', 'kode' => 'D2', 'nama' => 'D2'],
            ['tipe' => 'jenjang_pt', 'kode' => 'D3', 'nama' => 'D3'],
            ['tipe' => 'jenjang_pt', 'kode' => 'D4', 'nama' => 'D4'],
            ['tipe' => 'jenjang_pt', 'kode' => 'S1', 'nama' => 'S1'],
            ['tipe' => 'jenjang_pt', 'kode' => 'S2', 'nama' => 'S2'],

            // Info Daftar
            ['tipe' => 'info_daftar', 'kode' => 'Media Sosial / Website', 'nama' => 'Media Sosial / Website'],
            ['tipe' => 'info_daftar', 'kode' => 'Teman / Keluarga', 'nama' => 'Teman / Keluarga'],
            ['tipe' => 'info_daftar', 'kode' => 'Brosur / Spanduk', 'nama' => 'Brosur / Spanduk'],
            ['tipe' => 'info_daftar', 'kode' => 'Pameran / Guru BK', 'nama' => 'Pameran / Guru BK'],
            ['tipe' => 'info_daftar', 'kode' => 'Lainnya', 'nama' => 'Lainnya'],

            // Penghasilan
            ['tipe' => 'penghasilan_ortu', 'kode' => '< 1 Juta', 'nama' => '< 1 Juta'],
            ['tipe' => 'penghasilan_ortu', 'kode' => '1 - 3 Juta', 'nama' => '1 - 3 Juta'],
            ['tipe' => 'penghasilan_ortu', 'kode' => '3 - 5 Juta', 'nama' => '3 - 5 Juta'],
            ['tipe' => 'penghasilan_ortu', 'kode' => '5 - 10 Juta', 'nama' => '5 - 10 Juta'],
            ['tipe' => 'penghasilan_ortu', 'kode' => '> 10 Juta', 'nama' => '> 10 Juta'],
        ];

        foreach ($referensi as $index => $ref) {
            MasterReferensi::updateOrCreate(
                ['tipe' => $ref['tipe'], 'kode' => $ref['kode']],
                ['nama' => $ref['nama'], 'urutan' => $index + 1]
            );
        }
    }
}

<?php

namespace Database\Seeders\Simpeg;

use App\Models\Simpeg\JabatanFungsionalAkademik;
use Illuminate\Database\Seeder;

class JabatanFungsionalSeeder extends Seeder
{
    public function run(): void
    {
        $jafung = [
            [
                'nama' => 'Asisten Ahli',
                'angka_kredit_min' => 100,
                'angka_kredit_max' => 150,
                'golongan' => 'asisten_ahli',
            ],
            [
                'nama' => 'Lektor (200)',
                'angka_kredit_min' => 200,
                'angka_kredit_max' => 300,
                'golongan' => 'lektor',
            ],
            [
                'nama' => 'Lektor Kepala (400)',
                'angka_kredit_min' => 400,
                'angka_kredit_max' => 700,
                'golongan' => 'lektor_kepala',
            ],
            [
                'nama' => 'Guru Besar / Profesor',
                'angka_kredit_min' => 850,
                'angka_kredit_max' => 1050,
                'golongan' => 'guru_besar',
            ],
        ];

        foreach ($jafung as $data) {
            JabatanFungsionalAkademik::firstOrCreate(['nama' => $data['nama']], $data);
        }
    }
}

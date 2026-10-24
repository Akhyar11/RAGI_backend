<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Siakad\AturanSks;

class SiakadAturanSksSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultRules = [
            [
                'program_studi_id' => null,
                'ipk_min' => 3.00,
                'ipk_max' => 4.00,
                'max_sks' => 24,
                'keterangan' => 'Sangat Baik (Beban Maksimal)',
                'is_active' => true,
            ],
            [
                'program_studi_id' => null,
                'ipk_min' => 2.50,
                'ipk_max' => 2.99,
                'max_sks' => 22,
                'keterangan' => 'Baik',
                'is_active' => true,
            ],
            [
                'program_studi_id' => null,
                'ipk_min' => 2.00,
                'ipk_max' => 2.49,
                'max_sks' => 20,
                'keterangan' => 'Cukup',
                'is_active' => true,
            ],
            [
                'program_studi_id' => null,
                'ipk_min' => 0.00,
                'ipk_max' => 1.99,
                'max_sks' => 18,
                'keterangan' => 'Kurang (Beban Minimal)',
                'is_active' => true,
            ],
        ];

        foreach ($defaultRules as $rule) {
            AturanSks::updateOrCreate(
                [
                    'program_studi_id' => $rule['program_studi_id'],
                    'ipk_min' => $rule['ipk_min'],
                    'ipk_max' => $rule['ipk_max'],
                ],
                $rule
            );
        }
    }
}

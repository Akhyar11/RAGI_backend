<?php

namespace Database\Seeders\IAM;

use App\Models\IamApiIntegration;
use Illuminate\Database\Seeder;

class IamApiIntegrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $integrations = [
            [
                'key' => 'spmb_pendaftaran',
                'name' => 'SPMB Pendaftaran',
                'module' => 'spmb',
                'http_method' => 'GET',
                'description' => 'Endpoint API migrasi data calon mahasiswa baru / pendaftaran dari sistem lama',
                'expected_schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'nomor_pendaftaran' => ['type' => 'string', 'description' => 'Nomor pendaftaran sistem lama'],
                            'nama_lengkap' => ['type' => 'string', 'description' => 'Nama lengkap pendaftar'],
                            'nik' => ['type' => 'string', 'description' => 'Nomor Induk Kependudukan'],
                            'email' => ['type' => 'string', 'description' => 'Email pendaftar'],
                            'nomor_hp' => ['type' => 'string', 'description' => 'Nomor WhatsApp / HP'],
                            'jalur_pendaftaran' => ['type' => 'string', 'description' => 'Kode / nama jalur pendaftaran'],
                            'pilihan_prodi_1' => ['type' => 'string', 'description' => 'Kode / nama prodi pilihan 1'],
                            'pilihan_prodi_2' => ['type' => 'string', 'description' => 'Kode / nama prodi pilihan 2 (opsional)'],
                        ],
                        'required' => ['nama_lengkap', 'nik', 'email', 'nomor_hp'],
                    ],
                ],
                'auth_type' => 'bearer',
                'is_active' => true,
            ]
        ];

        foreach ($integrations as $item) {
            IamApiIntegration::updateOrCreate(
                ['key' => $item['key']],
                [
                    'name' => $item['name'],
                    'module' => $item['module'],
                    'http_method' => $item['http_method'],
                    'description' => $item['description'],
                    'expected_schema' => $item['expected_schema'],
                    'auth_type' => $item['auth_type'],
                    'is_active' => $item['is_active'],
                ]
            );
        }
    }
}

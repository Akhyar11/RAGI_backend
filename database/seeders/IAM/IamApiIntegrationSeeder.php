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
                'key' => 'siakad_program_studi',
                'name' => 'Migrasi Program Studi SIAKAD',
                'module' => 'siakad',
                'http_method' => 'GET',
                'description' => 'Sumber data read-only untuk migrasi program studi ke master SIAKAD. Fakultas tidak disediakan oleh sumber lama dan harus dipetakan terpisah di sistem tujuan.',
                'expected_schema' => [
                    'type' => 'array',
                    'description' => 'Daftar program studi dari sistem sumber. ID sumber hanya untuk rekonsiliasi dan tidak digunakan sebagai ID siakad_program_studi.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'source_id' => ['type' => ['string', 'integer'], 'description' => 'ID stabil program studi pada sistem sumber, disimpan sebagai referensi rekonsiliasi eksternal.'],
                            'kode_prodi' => ['type' => 'string', 'description' => 'Kode program studi lokal; dipetakan ke siakad_program_studi.kode_prodi dan harus unik.'],
                            'kode_prodi_dikti' => ['type' => ['string', 'null'], 'description' => 'Kode program studi PDDIKTI bila tersedia.'],
                            'nama' => ['type' => 'string', 'description' => 'Nama resmi program studi.'],
                            'jenjang' => ['type' => 'string', 'description' => 'Kode jenjang yang harus cocok dengan master referensi SIAKAD jenjang_prodi.'],
                            'akreditasi' => ['type' => ['string', 'null'], 'description' => 'Kode akreditasi yang harus cocok dengan master referensi SIAKAD akreditasi_prodi.'],
                            'is_active' => ['type' => 'boolean', 'description' => 'Status aktif program studi pada sistem sumber.'],
                            'fakultas' => ['type' => ['string', 'null'], 'description' => 'Nama/kode fakultas sumber bila kelak tersedia; sumber lama saat ini tidak memiliki data fakultas. Jangan menebak fakultas.'],
                        ],
                        'required' => ['source_id', 'kode_prodi', 'nama', 'jenjang', 'is_active'],
                    ],
                ],
                'auth_type' => 'bearer',
                'is_active' => true,
            ],
        ];

        IamApiIntegration::withTrashed()
            ->where('key', 'spmb_pendaftaran')
            ->forceDelete();

        foreach ($integrations as $item) {
            $existing = IamApiIntegration::where('key', $item['key'])->first();
            $connectionSettings = $existing
                ? [
                    'url_api' => $existing->url_api,
                    'token' => $existing->token,
                    'auth_type' => $existing->auth_type,
                    'is_active' => $existing->is_active,
                ]
                : [
                    'url_api' => null,
                    'token' => null,
                    'auth_type' => $item['auth_type'],
                    'is_active' => $item['is_active'],
                ];

            IamApiIntegration::updateOrCreate(
                ['key' => $item['key']],
                array_merge([
                    'name' => $item['name'],
                    'module' => $item['module'],
                    'http_method' => $item['http_method'],
                    'description' => $item['description'],
                    'expected_schema' => $item['expected_schema'],
                ], $connectionSettings)
            );
        }
    }
}

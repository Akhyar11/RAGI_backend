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
            [
                'key' => 'sikeu_transaksi_kas',
                'name' => 'Migrasi Transaksi Kas & Jurnal SIKEU',
                'module' => 'sikeu',
                'http_method' => 'GET',
                'description' => 'Sumber data transaksi kas masuk/keluar dari sistem keuangan legacy untuk dikonversi menjadi Jurnal Akuntansi berimbang (Double-Entry) dan dipetakan ke COA aktif RAG.',
                'expected_schema' => [
                    'type' => 'array',
                    'description' => 'Daftar transaksi kas dari sistem keuangan legacy. Kode perkiraan wajib terpetakan pada sikeu_mig_akun_mapping.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'no_tran' => ['type' => ['string', 'integer'], 'description' => 'Nomor transaksi kas unik dari sistem legacy.'],
                            'tgl_tran' => ['type' => 'string', 'description' => 'Tanggal transaksi kas (YYYY-MM-DD).'],
                            'kd_perkiraan' => ['type' => 'string', 'description' => 'Kode perkiraan akun lawan legacy yang dipetakan ke COA RAG.'],
                            'jenis' => ['type' => 'integer', 'description' => 'Arah kas: 1 untuk Kas Masuk (Debet Kas), 2 untuk Kas Keluar (Kredit Kas).'],
                            'jumlah' => ['type' => 'number', 'description' => 'Nominal transaksi kas (harus > 0).'],
                            'keterangan' => ['type' => ['string', 'null'], 'description' => 'Uraian atau deskripsi transaksi kas.'],
                        ],
                        'required' => ['no_tran', 'tgl_tran', 'kd_perkiraan', 'jumlah'],
                    ],
                ],
                'auth_type' => 'bearer',
                'is_active' => true,
            ],
            [
                'key' => 'sikeu_transaksi_pembayaran',
                'name' => 'Migrasi Pembayaran Mahasiswa SIKEU',
                'module' => 'sikeu',
                'http_method' => 'GET',
                'description' => 'Sumber data transaksi pembayaran kuliah mahasiswa dari sistem legacy untuk dicatat sebagai tagihan lunas dan riwayat pembayaran resmi mahasiswa di SIKEU RAG.',
                'expected_schema' => [
                    'type' => 'array',
                    'description' => 'Daftar transaksi pembayaran mahasiswa dari sistem legacy. NIM harus terdaftar pada siakad_mahasiswa.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'no_tran' => ['type' => ['string', 'integer'], 'description' => 'Nomor transaksi pembayaran unik dari sistem legacy.'],
                            'nim' => ['type' => 'string', 'description' => 'Nomor Induk Mahasiswa (NIM) pembayar.'],
                            'tgl_tran' => ['type' => 'string', 'description' => 'Tanggal pembayaran (YYYY-MM-DD).'],
                            'jumlah' => ['type' => 'number', 'description' => 'Nominal pembayaran dalam Rupiah (harus > 0).'],
                            'metode_bayar' => ['type' => ['string', 'null'], 'description' => 'Kanal / metode pembayaran (misal: TRANSFER_BANK, TUNAI, MIDTRANS).'],
                            'keterangan' => ['type' => ['string', 'null'], 'description' => 'Catatan transaksi pembayaran.'],
                        ],
                        'required' => ['no_tran', 'nim', 'tgl_tran', 'jumlah'],
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

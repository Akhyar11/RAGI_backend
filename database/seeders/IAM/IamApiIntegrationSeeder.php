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
                'description' => 'Migrasi program studi dari PDDIKTI Neo Feeder atau Custom API ke master SIAKAD.',
                'source_type' => 'pddikti',
                'supported_sources' => ['pddikti', 'custom_api'],
                'source_schemas' => [
                    'pddikti' => [
                        'source' => 'Neo Feeder PDDikti',
                        'action' => 'GetProdi',
                        'connection_managed' => true,
                        'target_entity' => 'program_studi',
                        'fields' => [
                            'id_prodi' => 'UUID program studi PDDIKTI; disimpan pada source_id dan id_feeder.',
                            'kode_program_studi' => 'Kode program studi PDDIKTI.',
                            'nama_program_studi' => 'Nama program studi.',
                            'nama_jenjang_pendidikan' => 'Kode jenjang; harus tersedia pada master referensi SIAKAD.',
                            'status' => 'Status program studi; A berarti aktif.',
                        ],
                    ],
                ],
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
            [
                'key' => 'siakad_mahasiswa',
                'name' => 'Sinkronisasi Data Mahasiswa SIAKAD',
                'module' => 'siakad',
                'http_method' => 'GET',
                'description' => 'Sinkronisasi mahasiswa dari PDDIKTI Neo Feeder atau API SIAKAD legacy ke siakad_mahasiswa. Data akademik PDDIKTI mempertahankan NIM sumber; akun/password tetap dikelola IAM lokal.',
                'source_type' => 'pddikti',
                'supported_sources' => ['pddikti', 'custom_api'],
                'source_schemas' => [
                    'pddikti' => [
                        'source' => 'Neo Feeder PDDikti',
                        'count_action' => 'GetCountMahasiswa',
                        'action' => 'GetListMahasiswa',
                        'biodata_count_action' => 'GetCountBiodataMahasiswa',
                        'detail_action' => 'GetBiodataMahasiswa',
                        'connection_managed' => true,
                        'target_entity' => 'mahasiswa',
                        'fields' => [
                            'id_mahasiswa' => 'UUID mahasiswa Feeder; disimpan pada id_feeder_biodata dan dipakai sebagai identitas sumber.',
                            'id_registrasi_mahasiswa' => 'UUID riwayat registrasi; disimpan pada id_feeder_riwayat.',
                            'nim' => 'NIM sumber; dipertahankan persis dan tidak dibuat ulang dengan prefix_nim.',
                            'id_prodi' => 'UUID prodi PDDIKTI; wajib cocok dengan siakad_program_studi.id_feeder.',
                            'id_periode' => 'Periode masuk Neo Feeder; hanya periode YYYY-S yang dapat dikonversi ke tahun angkatan.',
                            'id_status_mahasiswa' => 'Kode status Feeder; nilai tanpa mapping lokal akan dilewati.',
                            'nama_status_mahasiswa' => 'Status Feeder; hanya AKTIF dan Lulus dipetakan otomatis pada rilis awal.',
                        ],
                    ],
                    'custom_api' => [
                        'source' => 'SIAKAD legacy',
                        'target_entity' => 'mahasiswa',
                        'connection_managed' => false,
                        'preserve_connection_settings' => true,
                    ],
                ],
                'expected_schema' => [
                    'type' => 'object',
                    'description' => 'Sumber legacy menggunakan respons paginasi; PDDIKTI menghitung total dengan GetCountMahasiswa dan GetCountBiodataMahasiswa, lalu membaca GetListMahasiswa dan GetBiodataMahasiswa per batch 500.',
                    'properties' => [
                        'status' => ['type' => 'boolean'],
                        'message' => ['type' => 'string'],
                        'pagination' => [
                            'type' => 'object',
                            'properties' => [
                                'total_records' => ['type' => 'integer'],
                                'total_pages' => ['type' => 'integer'],
                                'current_page' => ['type' => 'integer'],
                                'limit' => ['type' => 'integer'],
                                'has_next' => ['type' => 'boolean'],
                                'has_prev' => ['type' => 'boolean'],
                            ],
                        ],
                        'data' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'identitas' => ['type' => 'object', 'description' => 'NIM, nama, NIK, NISN, TTL, jenis kelamin, agama.'],
                                    'akademik' => ['type' => 'object', 'description' => 'Angkatan, prodi_id (xid_sms sumber), program_studi, jenjang, kelas, status_mahasiswa, tanggal_masuk, jenis pendaftaran.'],
                                    'kontak' => ['type' => 'object'],
                                    'alamat' => ['type' => 'object'],
                                    'orang_tua_wali' => ['type' => 'object'],
                                ],
                            ],
                        ],
                    ],
                ],
                'auth_type' => 'bearer',
                'is_active' => true,
            ],
            [
                'key' => 'siakad_tahun_akademik',
                'name' => 'Sinkronisasi Semester Neo Feeder SIAKAD',
                'module' => 'siakad',
                'http_method' => 'GET',
                'description' => 'Menarik semester dari Neo Feeder dan menyimpan ID Feeder pada Tahun Akademik lokal dengan kode periode yang sama persis.',
                'source_type' => 'pddikti',
                'supported_sources' => ['pddikti'],
                'source_schemas' => [
                    'pddikti' => [
                        'source' => 'Neo Feeder PDDikti',
                        'action' => 'GetSemester',
                        'connection_managed' => true,
                        'target_entity' => 'tahun_akademik',
                        'fields' => [
                            'id_semester' => 'ID semester Neo Feeder; hanya dipetakan ke siakad_tahun_akademik.id_feeder jika sama persis dengan kode lokal.',
                            'id_tahun_ajaran' => 'Tahun ajaran pada data semester Feeder; tidak mengubah rentang tahun lokal.',
                            'nama_semester' => 'Nama semester Feeder untuk keperluan informasi/log.',
                            'semester' => 'Nomor semester Feeder untuk informasi/log.',
                            'a_periode_aktif' => 'Status periode aktif Feeder; tidak menimpa status aktif lokal.',
                            'tanggal_mulai' => 'Tanggal mulai Feeder; tidak menimpa kalender lokal.',
                            'tanggal_selesai' => 'Tanggal selesai Feeder; tidak menimpa kalender lokal.',
                        ],
                    ],
                ],
                'expected_schema' => [
                    'type' => 'object',
                    'description' => 'Daftar semester Neo Feeder. Kode id_semester harus cocok persis dengan kode Tahun Akademik lokal.',
                    'properties' => [
                        'data' => ['type' => 'array', 'items' => ['type' => 'object']],
                        'error_code' => ['type' => 'integer'],
                    ],
                ],
                'auth_type' => 'bearer',
                'is_active' => true,
            ],
            [
                'key' => 'siakad_kurikulum',
                'name' => 'Migrasi Kurikulum SIAKAD',
                'module' => 'siakad',
                'http_method' => 'GET',
                'description' => 'Mengambil master kurikulum PDDIKTI melalui GetListKurikulum. Program studi tujuan harus sudah dipetakan ke UUID Feeder.',
                'source_type' => 'pddikti',
                'supported_sources' => ['pddikti'],
                'source_schemas' => [
                    'pddikti' => [
                        'source' => 'Neo Feeder PDDikti',
                        'action' => 'GetListKurikulum',
                        'connection_managed' => true,
                        'target_entity' => 'kurikulum',
                        'fields' => [
                            'id_kurikulum' => 'UUID kurikulum PDDIKTI',
                            'nama_kurikulum' => 'Nama kurikulum',
                            'id_prodi' => 'UUID program studi PDDIKTI',
                            'nama_program_studi' => 'Nama program studi sumber',
                            'id_semester' => 'Kode semester mulai',
                            'semester_mulai_berlaku' => 'Semester mulai berlaku',
                            'jumlah_sks_lulus' => 'Total SKS lulus',
                            'jumlah_sks_wajib' => 'Total SKS wajib',
                        ],
                    ],
                ],
                'expected_schema' => [
                    'type' => 'object',
                    'description' => 'Respons Neo Feeder dari GetListKurikulum.',
                    'properties' => [
                        'data' => ['type' => 'array', 'items' => ['type' => 'object']],
                        'error_code' => ['type' => 'integer'],
                    ],
                ],
                'auth_type' => 'bearer',
                'is_active' => true,
            ],
            [
                'key' => 'siakad_mata_kuliah',
                'name' => 'Migrasi Mata Kuliah SIAKAD',
                'module' => 'siakad',
                'http_method' => 'GET',
                'description' => 'Sumber data read-only mata kuliah dari SIAKAD legacy untuk disinkronkan ke master mata kuliah RAG. Setiap baris harus merujuk kode kurikulum yang telah tersedia di sistem tujuan.',
                'source_type' => 'pddikti',
                'supported_sources' => ['pddikti', 'custom_api'],
                'source_schemas' => [
                    'pddikti' => [
                        'source' => 'Neo Feeder PDDikti',
                        'action' => 'GetMatkulKurikulum',
                        'connection_managed' => true,
                        'fields' => [
                            'id_matkul' => 'UUID mata kuliah PDDIKTI',
                            'id_kurikulum' => 'UUID kurikulum PDDIKTI',
                            'id_prodi' => 'UUID program studi PDDIKTI',
                            'kode_mata_kuliah' => 'Kode mata kuliah',
                            'nama_mata_kuliah' => 'Nama mata kuliah',
                            'semester' => 'Semester anjuran',
                            'sks_mata_kuliah' => 'Total SKS',
                            'sks_tatap_muka' => 'SKS teori',
                            'sks_praktek' => 'SKS praktik',
                            'apakah_wajib' => '1 wajib, selainnya pilihan',
                            'nama_kurikulum' => 'Nama kurikulum untuk mapping prodi',
                        ],
                    ],
                ],
                'expected_schema' => [
                    'type' => 'object',
                    'description' => 'Respons array atau paginasi dari endpoint mata kuliah SIAKAD legacy.',
                    'properties' => [
                        'data' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'source_id' => ['type' => ['string', 'integer'], 'description' => 'ID stabil mata kuliah pada sistem sumber; disimpan untuk rekonsiliasi dan idempotensi.'],
                                    'kurikulum_kode' => ['type' => 'string', 'description' => 'Kode kurikulum yang harus sudah ada dan cocok persis di SIAKAD tujuan.'],
                                    'kode_mk' => ['type' => 'string', 'description' => 'Kode mata kuliah unik pada SIAKAD tujuan.'],
                                    'nama' => ['type' => 'string'],
                                    'sks_teori' => ['type' => 'integer', 'minimum' => 0],
                                    'sks_praktik' => ['type' => 'integer', 'minimum' => 0],
                                    'total_sks' => ['type' => 'integer', 'minimum' => 0],
                                    'semester_anjuran' => ['type' => 'integer', 'minimum' => 1],
                                    'tipe' => ['type' => 'string', 'enum' => ['wajib', 'pilihan', 'wajib_prodi']],
                                    'kategori' => ['type' => ['string', 'null']],
                                    'jumlah_pertemuan' => ['type' => 'integer', 'minimum' => 1],
                                    'is_active' => ['type' => 'boolean'],
                                ],
                                'required' => ['source_id', 'kurikulum_kode', 'kode_mk', 'nama', 'sks_teori', 'sks_praktik', 'total_sks', 'semester_anjuran', 'tipe', 'is_active'],
                            ],
                        ],
                        'meta' => [
                            'type' => 'object',
                            'properties' => [
                                'current_page' => ['type' => 'integer'],
                                'last_page' => ['type' => 'integer'],
                            ],
                        ],
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
                    'source_type' => $existing->source_type ?? $item['source_type'] ?? 'custom_api',
                    'supported_sources' => $item['supported_sources'] ?? ['custom_api'],
                    'source_schemas' => $item['source_schemas'] ?? [],
                ]
                : [
                    'url_api' => null,
                    'token' => null,
                    'auth_type' => $item['auth_type'],
                    'is_active' => $item['is_active'],
                    'source_type' => $item['source_type'] ?? 'custom_api',
                    'supported_sources' => $item['supported_sources'] ?? ['custom_api'],
                    'source_schemas' => $item['source_schemas'] ?? [],
                ];

            IamApiIntegration::updateOrCreate(
                ['key' => $item['key']],
                array_merge([
                    'name' => $item['name'],
                    'module' => $item['module'],
                    'http_method' => $item['http_method'],
                    'description' => $item['description'],
                    'expected_schema' => $item['expected_schema'],
                    'source_type' => $connectionSettings['source_type'],
                    'supported_sources' => $connectionSettings['supported_sources'],
                    'source_schemas' => $connectionSettings['source_schemas'],
                ], $connectionSettings)
            );
        }
    }
}

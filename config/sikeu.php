<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tabel staging migrasi legacy SIKEU
    |--------------------------------------------------------------------------
    |
    | Nama tabel staging historis dipusatkan di sini dengan prefix modul resmi.
    |
    */
    'migration' => [
        'akun_mapping_table' => env('SIKEU_MIGRATION_AKUN_MAPPING_TABLE', 'sikeu_mig_akun_mapping'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Migrator Data Keuangan (SIKEU)
    |--------------------------------------------------------------------------
    |
    | Mendukung mode direct_db (membaca langsung dari MySQL legacy) atau
    | mode api (menarik stream transaksi dari API endpoint eksternal).
    |
    */
    'migrator' => [
        'mode' => env('SIKEU_MIGRATOR_MODE', 'direct_db'), // 'direct_db' | 'api'
        'auth_scheme' => env('SIKEU_MIGRATOR_AUTH_SCHEME', 'bearer'), // 'bearer' | 'header_key'
        'api_url' => env('SIKEU_MIGRATOR_API_URL', ''),
        'api_token' => env('SIKEU_MIGRATOR_API_TOKEN', ''),
        'batch_size' => (int) env('SIKEU_MIGRATOR_BATCH_SIZE', 500),
        'max_pages' => (int) env('SIKEU_MIGRATOR_MAX_PAGES', 1000),
        'module_key' => 'SIKEU',
        'sources' => ['all', 'indonusa', 'sikeudb'],
        'types' => ['all', 'jurnal', 'pembayaran'],
        'modes' => ['direct_db', 'api'],
        'auth_schemes' => ['bearer', 'header_key'],
        'source_connections' => [
            'indonusa' => 'mysql_indonusa',
            'sikeudb'  => 'mysql_sikeudb',
        ],
        'source_tables' => [
            'jurnal' => [
                'mysql_indonusa' => 'trkas',
                'mysql_sikeudb'  => 'transaksi_kas',
            ],
            'pembayaran' => [
                'mysql_indonusa' => 'trbyr',
                'mysql_sikeudb'  => 'transaksi_bayar',
            ],
        ],
        'legacy_sources' => [
            'indonusa' => 'LEGACY_INDONUSA_STMIK',
            'sikeudb'  => 'SEQUELIZE_SIKEUDB',
        ],
        'unresolved_source_tables' => [
            'jurnal' => ['trkas', 'transaksi_kas'],
            'pembayaran' => ['trbyr', 'transaksi_bayar', 'rkpbyr'],
        ],
    ],
];

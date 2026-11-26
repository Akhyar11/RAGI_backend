<?php

return [

    'timezone' => env('SPMB_TIMEZONE', 'Asia/Jakarta'),

    'public_url' => env('SPMB_PUBLIC_URL', env('FRONTEND_URL')),

    /*
    |--------------------------------------------------------------------------
    | SPMB — Pengaturan Referral
    |--------------------------------------------------------------------------
    */

    // Label jalur bila pendaftar belum memiliki jalur (mis. data import legacy).
    'label_jalur_default' => env('SPMB_LABEL_JALUR_DEFAULT', 'Reguler'),

    'import' => [
        // Password awal akun calon mahasiswa hasil import Excel legacy.
        'default_password' => env('SPMB_IMPORT_DEFAULT_PASSWORD') ?: 'indonusa',
        'max_file_kb' => 10240,
    ],

    'referral' => [
        // Minimal pembayaran biaya daftar ulang (Rupiah) agar referral dianggap
        // layak dicairkan.
        'min_daftar_ulang_payment' => (float) env('SPMB_REFERRAL_MIN_DAFTAR_ULANG', 100000),

        // Status pendaftaran yang dianggap "selesai".
        'completed_statuses' => ['lulus_administrasi', 'mahasiswa_baru'],
    ],

];

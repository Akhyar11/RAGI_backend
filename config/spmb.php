<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SPMB — Pengaturan Referral
    |--------------------------------------------------------------------------
    */

    'referral' => [
        // Minimal pembayaran biaya daftar ulang (Rupiah) agar referral dianggap
        // layak dicairkan.
        'min_daftar_ulang_payment' => (float) env('SPMB_REFERRAL_MIN_DAFTAR_ULANG', 100000),

        // Status pendaftaran yang dianggap "selesai".
        'completed_statuses' => ['lulus_administrasi', 'mahasiswa_baru'],
    ],

];

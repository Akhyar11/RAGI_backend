<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Akun SSO otomatis hasil sinkronisasi
    |--------------------------------------------------------------------------
    | Kata sandi bawaan TIDAK ditulis di kode; isi lewat .env. Bila kosong,
    | sinkronisasi mahasiswa tetap berjalan tetapi pembuatan akun SSO dilewati.
    */
    'sso' => [
        'mahasiswa_default_password' => env('SSO_DEFAULT_MAHASISWA_PASSWORD'),
        // Domain email cadangan bila email PDDIKTI kosong/tidak valid/sudah dipakai
        // (selaras dengan akun dosen hasil sync: username@campus.ac.id).
        'placeholder_email_domain' => env('SSO_PLACEHOLDER_EMAIL_DOMAIN', 'campus.ac.id'),
    ],
];

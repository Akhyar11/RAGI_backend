<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tabel staging migrasi legacy SIKEU
    |--------------------------------------------------------------------------
    |
    | Nama tabel staging historis dipertahankan agar kompatibel dengan data
    | migrasi yang sudah berjalan. Nilai dipusatkan di sini (bukan literal
    | berulang di command) sehingga mudah diubah saat staging dirapikan.
    |
    */
    'migration' => [
        'akun_mapping_table' => '_mig_akun_mapping',
    ],
];

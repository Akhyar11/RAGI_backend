<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Integrasi Modul LMS ke Sistem Eksternal (PresensiV2)
    |--------------------------------------------------------------------------
    | Modul LMS menyimpan data presensi sendiri (siakad_pertemuan +
    | siakad_absensi_mahasiswa) dan BERKEMBUNG dengan aplikasi PresensiV2:
    | jurnal perkuliahan & rekap kehadiran dikirim ke PresensiV2, dan riwayat
    | dari PresensiV2 dapat ditarik kembali ke LMS.
    |
    | Dua jalur komunikasi, dipilih lewat `presensi.mode`:
    |   - `api` : REST ke aplikasi PresensiV2 (disarankan, via domain).
    |   - `db`  : tulis langsung ke database MySQL PresensiV2 (koneksi
    |             `presensiv2` pada config/database.php).
    |   - `auto`: coba `api` dulu bila PRESENSI_API_URL terisi, jika gagal
    |             fallback ke `db`.
    |
    | Bila `PRESENSI_API_URL` kosong DAN koneksi `presensiv2` tidak
    | dikonfigurasi, seluruh sinkronisasi otomatis berstatus "skipped" —
    | fitur LMS tetap berjalan normal tanpa integrasi eksternal.
    |
    | Semua URL memakai domain (tanpa port) agar tidak perlu ganti-ganti
    | port. Contoh production:
    |   PRESENSI_API_URL=https://presensiv2.poltekindonusa.ac.id
    */

    'presensi' => [
        // URL dasar aplikasi PresensiV2. Kosongkan bila hanya memakai jalur DB.
        'api_url' => rtrim((string) env('PRESENSI_API_URL', ''), '/'),

        // API key untuk header `X-Api-Key`.
        'api_key' => (string) env('PRESENSI_API_KEY', ''),

        // auto | api | db
        'mode' => (string) env('PRESENSI_MODE', 'auto'),

        // Timeout HTTP (detik) untuk panggilan API PresensiV2.
        'timeout' => (int) env('PRESENSI_API_TIMEOUT', 10),

        // Nama koneksi database untuk jalur direct-DB.
        'db_connection' => (string) env('PRESENSI_DB_CONNECTION', 'presensiv2'),

        // Apakah jalur direct-DB benar-benar dikonfigurasi. Bila host & basis
        // data PresensiV2 belum diisi di environment, integrasi otomatis
        // berstatus "skipped" dan fitur LMS tetap berjalan normal.
        'db_configured' => (bool) (env('PRESENSI_DB_HOST') && env('PRESENSI_DB_DATABASE')),

        // Nama tabel jurnal & rekap pada database PresensiV2.
        'jurnal_table' => (string) env('PRESENSI_JURNAL_TABLE', 'presensi_jurnal_perkuliahan'),
        'rekap_table' => (string) env('PRESENSI_REKAP_TABLE', 'presensi_rekap'),

        // Kolom sumber identitas dosen pada RAG yang dikirim sebagai `id_ptk`
        // ke PresensiV2: `nidn` (default) atau `nip`.
        'id_ptk_source' => (string) env('PRESENSI_ID_PTK_SOURCE', 'nidn'),

        // Label ruang yang dikirim ke PresensiV2 bila kelas tidak punya ruang.
        'ruang_default' => (string) env('PRESENSI_RUANG_DEFAULT', 'LMS Online'),
    ],

];

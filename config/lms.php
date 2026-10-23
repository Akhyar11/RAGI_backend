<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modul LMS (standalone)
    |--------------------------------------------------------------------------
    | Konfigurasi khusus modul LMS. Integrasi ke sistem eksternal (PresensiV2)
    | disimpan terpisah pada `config/lms_integration.php`.
    */

    'notifikasi' => [
        /*
        | In-app inbox (tabel `lms_notifikasi`) SELALU aktif. Kanal outbound
        | tambahan (email / WhatsApp) bersifat opsional dan memakai kanal global
        | `App\Services\Notification\CampusNotificationService` — tidak ada
        | kanal baru yang dibuat untuk LMS.
        |
        | Aktifkan lewat env:
        |   LMS_NOTIFIKASI_KIRIM_EMAIL=true
        |   LMS_NOTIFIKASI_KIRIM_WHATSAPP=true
        */
        'kirim_email' => (bool) env('LMS_NOTIFIKASI_KIRIM_EMAIL', false),

        'kirim_whatsapp' => (bool) env('LMS_NOTIFIKASI_KIRIM_WHATSAPP', false),
    ],

];

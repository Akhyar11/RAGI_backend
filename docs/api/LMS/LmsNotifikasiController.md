# LmsNotifikasiController

> **Modul**: LMS (standalone, pisah dari SIAKAD)  
> **Base URL**: `/api/v1/lms`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-10-22  
> **Diperbarui**: 2026-10-22

Controller notifikasi in-app modul LMS (LMS-04 / Fase 3A).

Berbeda dengan notifikasi donor (`Project/lms`) yang dihitung *on-the-fly* pada
tiap request, notifikasi RAG **disimpan** pada tabel `lms_notifikasi` sehingga:

- status baca/unread konsisten antar perangkat,
- pengingat deadline tidak terkirim berulang (idempoten lewat `dedup_key`),
- notifikasi dapat difilter per jenis & ditampilkan pada lonceng navbar.

## Model Otorisasi

Notifikasi bersifat **personal** (disimpan per `user_id`), sehingga endpoint ini
**tidak memakai permission modul terpisah**. Setiap pengguna hanya dapat:

- membaca notifikasi miliknya sendiri,
- menandai notifikasi miliknya sendiri sebagai sudah dibaca.

Notifikasi milik pengguna lain tidak pernah terlihat dan menghasilkan `404`
saat tries ditandai dibaca.

## Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ untuk POST |

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/lms/notifikasi` | Daftar notifikasi milik pengguna (filter + pagination) | ✅ Token |
| GET | `/api/v1/lms/notifikasi/unread-count` | Jumlah notifikasi yang belum dibaca | ✅ Token |
| POST | `/api/v1/lms/notifikasi/{id}/read` | Tandai satu notifikasi sudah dibaca | ✅ Token |
| POST | `/api/v1/lms/notifikasi/read-all` | Tandai seluruh notifikasi sudah dibaca | ✅ Token |

## Query Parameters

### `GET /notifikasi`

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Pencarian pada `judul` atau `pesan` |
| `jenis` | string | ❌ | — | Filter jenis notifikasi (lihat tabel Jenis Notifikasi) |
| `is_read` | boolean | ❌ | — | `true` = hanya yang sudah dibaca, `false` = hanya yang belum dibaca |
| `sort_by` | string | ❌ | `created_at` | `created_at`, `read_at`, `prioritas`, `jenis` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Nomor halaman |

### Jenis Notifikasi

Nilai bersifat *closed-set domain* (bukan entitas master), dideklarasikan
terpusat pada model `App\Models\Lms\LmsNotifikasi`:

| Nilai | Makna |
|---|---|
| `deadline_tugas` | Pengingat batas pengumpulan tugas |
| `deadline_quiz` | Pengingat penutupan kuis/tryout |
| `tugas_baru` | Tugas baru dipublikasikan |
| `quiz_baru` | Kuis/tryout baru dipublikasikan |
| `forum` | Aktivitas baru di forum diskusi |
| `info` | Informasi umum |

### Prioritas

| Nilai | Makna |
|---|---|
| `rendah` | Tidak mendesak |
| `normal` | Default |
| `tinggi` | Mendesak (contoh: pengingat deadline) |

## Response Sukses

### `GET /notifikasi`

**200 OK**

```json
{
    "status": "success",
    "message": "Daftar notifikasi berhasil diambil.",
    "data": [
        {
            "id": 12,
            "user_id": 5,
            "jenis": "deadline_tugas",
            "judul": "Tugas segera jatuh tempo: Laporan Praktikum",
            "pesan": "Batas pengumpulan tugas ini pada 23 Okt 2026 23:59.",
            "rute": "/lms/tugas/9",
            "kelas_id": 3,
            "entitas_type": "App\\Models\\Lms\\Tugas",
            "entitas_id": 9,
            "prioritas": "tinggi",
            "is_read": false,
            "read_at": null,
            "created_at": "2026-10-22T07:00:00.000000Z",
            "updated_at": "2026-10-22T07:00:00.000000Z",
            "kelas": {
                "id": 3,
                "kode_kelas": "IF401-A",
                "nama_kelas": "IF401-A"
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1,
        "from": 1,
        "to": 1
    },
    "filters": {
        "search": null,
        "jenis": null,
        "is_read": null,
        "sort_by": "created_at",
        "sort_order": "desc"
    }
}
```

### `GET /notifikasi/unread-count`

**200 OK**

```json
{
    "status": "success",
    "message": "Jumlah notifikasi belum dibaca berhasil diambil.",
    "data": {
        "unread_count": 3
    }
}
```

### `POST /notifikasi/{id}/read`

Tidak ada request body.

**200 OK**

```json
{
    "status": "success",
    "message": "Notifikasi ditandai sudah dibaca.",
    "data": {
        "id": 12,
        "user_id": 5,
        "jenis": "deadline_tugas",
        "judul": "Tugas segera jatuh tempo: Laporan Praktikum",
        "pesan": "Batas pengumpulan tugas ini pada 23 Okt 2026 23:59.",
        "rute": "/lms/tugas/9",
        "kelas_id": 3,
        "entitas_type": "App\\Models\\Lms\\Tugas",
        "entitas_id": 9,
        "prioritas": "tinggi",
        "is_read": true,
        "read_at": "2026-10-22T09:12:00.000000Z",
        "created_at": "2026-10-22T07:00:00.000000Z",
        "updated_at": "2026-10-22T09:12:00.000000Z"
    }
}
```

### `POST /notifikasi/read-all`

Tidak ada request body.

**200 OK**

```json
{
    "status": "success",
    "message": "5 notifikasi ditandai sudah dibaca.",
    "data": {
        "updated": 5
    }
}
```

Bila tidak ada notifikasi yang belum dibaca, `message` menjadi
`"Tidak ada notifikasi yang belum dibaca."` dan `data.updated` bernilai `0`.

## Response Error

**401 Unauthorized**

```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**404 Not Found**

Notifikasi tidak ada **atau** bukan milik pengguna yang sedang login.

```json
{
    "status": "error",
    "message": "LmsNotifikasi not found."
}
```

## Command Terjadwal

### `php artisan lms:notifikasi-deadline`

Mengirim pengingat deadline tugas & kuis ke mahasiswa yang relevan.

| Option | Default | Deskripsi |
|---|---|---|
| `--hours` | `24` | Rentang waktu pengingat ke depan (jam) |
| `--dry-run` | — | Hanya menampilkan ringkasan, tanpa membuat notifikasi |

Contoh output:

```
Notifikasi deadline: 2 tugas (18 notifikasi), 1 kuis (12 notifikasi).
```

Perilaku penting:

- Hanya tugas/kuis yang **sudah dipublikasikan** (`is_published = true`) yang
  dip remind.
- Mahasiswa yang **sudah mengumpulkan tugas** atau **sudah menyelesaikan
  kuis** tidak dikirimi pengingat.
- Pengingat bersifat **idempoten**: `dedup_key` =
  `deadline_tugas:{id_tugas}:{YYYYMMDD}` / `deadline_quiz:{id_quiz}:{YYYYMMDD}`
  sehingga menjalankan command berkali-kali pada hari yang sama tidak membuat
  notifikasi ganda.

Command dijadwalkan harian pada pukul 07:00 (lihat `routes/console.php`):

```php
Schedule::command('lms:notifikasi-deadline')->dailyAt('07:00')->withoutOverlapping();
```

### Kanal Outbound (opsional)

Selain inbox in-app, pengingat dapat dikirim ke kanal outbound **global** yang
sudah ada (`App\Services\Notification\CampusNotificationService`) — tidak ada
kanal baru yang dibuat khusus LMS. Kanal ini **nonaktif secara default** dan
diaktifkan lewat env:

| Env | Efek |
|---|---|
| `LMS_NOTIFIKASI_KIRIM_EMAIL=true` | Kirim email pengingat ke email mahasiswa |
| `LMS_NOTIFIKASI_KIRIM_WHATSAPP=true` | Kirim WhatsApp pengingat ke nomor mahasiswa |

Nilai ini dibaca dari `config/lms.php` (`lms.notifikasi.kirim_email` &
`lms.notifikasi.kirim_whatsapp`).

## Catatan Tambahan

> - Tabel `lms_notifikasi` dibuat oleh migrasi
>   `2026_10_22_000003_create_lms_notifikasi_table.php`.
> - Kolom `dedup_key` bersifat internal (tidak diekspos pada response API).
> - Endpoint ini dipakai lonceng notifikasi pada navbar, sehingga tersedia
>   untuk seluruh pengguna, bukan hanya peserta kelas LMS.

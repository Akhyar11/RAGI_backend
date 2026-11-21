# CampusAnnouncementController

> **Modul**: SIMPEG (Kepegawaian & SDM)  
> **Base URL**: `/api/simpeg` & `/api/v1/mobile/staff`  
> **Autentikasi**: Bearer Token (`auth:api`)  
> **Dibuat**: 2026-11-21  
> **Diperbarui**: 2026-11-21  

## Deskripsi Singkat

`CampusAnnouncementController` menangani penyiaran (broadcast) pengumuman kampus, agenda rapat dan kegiatan kedinasan, serta informasi ketentuan seragam kerja harian bagi staf dan dosen. Pengumuman baru yang disiarkan otomatis mendistribusikan notifikasi in-app ke tabel `core_notifications` untuk seluruh pegawai sesuai sasaran peran (semua pegawai, khusus dosen, atau khusus tendik).

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Autentikasi / Akses |
|---|---|---|---|
| GET | `/api/simpeg/announcements` | Riwayat broadcast pengumuman & agenda kampus | ✅ Bearer Token (`auth:api` / `simpeg.pegawai.read`) |
| POST | `/api/simpeg/announcements/broadcast` | Siarkan pengumuman baru & sebar notifikasi pegawai | ✅ Bearer Token (`auth:api` / `simpeg.pegawai.create`) |
| GET | `/api/v1/mobile/staff/agendas` | Agenda & info seragam kerja hari ini untuk Mobile Staff | ✅ Bearer Token (`auth:api` / `simpeg.pegawai.read`) |

---

## 1. GET /api/simpeg/announcements

Mengambil riwayat broadcast pengumuman dengan filter kategori, target role, dan pagination standar.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Kata kunci pencarian judul atau isi pengumuman |
| `category` | string | ❌ | — | Filter kategori: `seragam`, `rapat`, `deadline`, `umum` |
| `target_role` | string | ❌ | — | Filter target peran: `all_staff`, `dosen`, `tendik` |
| `sort_by` | string | ❌ | `id` | Kolom pengurutan (`id`, `created_at`, `event_date`, `priority`) |
| `sort_order` | string | ❌ | `desc` | Arah urutan (`asc` atau `desc`) |
| `per_page` | integer | ❌ | `15` | Jumlah item per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Nomor halaman |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar riwayat broadcast pengumuman berhasil diambil.",
    "data": [
        {
            "id": 5,
            "sender_id": 1,
            "title": "Ketentuan Seragam Kerja Hari Senin",
            "category": "seragam",
            "target_role": "all_staff",
            "content": "Seluruh pegawai diwajibkan mengenakan pakaian dinas harian warna putih.",
            "is_agenda": false,
            "event_date": "2026-11-23",
            "event_time": null,
            "event_location": null,
            "event_url": null,
            "priority": "normal",
            "recipients_count": 85,
            "created_at": "2026-11-21T06:00:00.000000Z",
            "updated_at": "2026-11-21T06:00:00.000000Z",
            "sender": {
                "id": 1,
                "name": "Super Admin",
                "username": "superadmin"
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1,
        "from": 1,
        "to": 1,
        "filters": {
            "search": null,
            "category": null,
            "target_role": null,
            "sort_by": "id",
            "sort_order": "desc"
        }
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

---

## 2. POST /api/simpeg/announcements/broadcast

Menyiarkan pengumuman baru, menyimpannya ke tabel `simpeg_campus_announcements`, dan mengirimkan entri notifikasi sistem ke `core_notifications` untuk setiap pegawai sasaran.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "title": "Rapat Koordinasi Akademik Semester Ganjil 2026/2027",
    "category": "rapat",
    "target_role": "dosen",
    "content": "Rapat evaluasi perkuliahan semester ganjil bertempat di Ruang Senat.",
    "is_agenda": true,
    "event_date": "2026-11-25",
    "event_time": "09:00 WIB",
    "event_location": "Ruang Senat Gedung Rektorat Lt 3",
    "event_url": "https://meet.google.com/abc-defg-hij",
    "priority": "high"
}
```

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Pengumuman berhasil disiarkan ke 42 pegawai (Dosen Saja).",
    "data": {
        "id": 6,
        "sender_id": 1,
        "title": "Rapat Koordinasi Akademik Semester Ganjil 2026/2027",
        "category": "rapat",
        "target_role": "dosen",
        "content": "Rapat evaluasi perkuliahan semester ganjil bertempat di Ruang Senat.",
        "is_agenda": true,
        "event_date": "2026-11-25",
        "event_time": "09:00 WIB",
        "event_location": "Ruang Senat Gedung Rektorat Lt 3",
        "event_url": "https://meet.google.com/abc-defg-hij",
        "priority": "high",
        "recipients_count": 42,
        "created_at": "2026-11-21T07:00:00.000000Z",
        "updated_at": "2026-11-21T07:00:00.000000Z"
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "title": ["Judul pengumuman wajib diisi."],
        "category": ["Kategori pengumuman wajib dipilih."],
        "target_role": ["Target penerima pengumuman wajib dipilih."]
    }
}
```

---

## 3. GET /api/v1/mobile/staff/agendas

Mengambil agenda rapat dan informasi seragam kerja hari ini sesuai role pegawai aktif.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Agenda & pengumuman berhasil diambil.",
    "data": {
        "user_role": "dosen",
        "seragam_hari_ini": {
            "id": 5,
            "title": "Ketentuan Seragam Kerja Hari Senin",
            "category": "seragam",
            "content": "Pakaian dinas harian (PDH) putih.",
            "event_date": "2026-11-23"
        },
        "upcoming_agendas": [
            {
                "id": 6,
                "title": "Rapat Koordinasi Akademik",
                "category": "rapat",
                "content": "Rapat evaluasi perkuliahan semester ganjil.",
                "is_agenda": true,
                "event_date": "2026-11-25",
                "event_time": "09:00 WIB",
                "event_location": "Ruang Senat"
            }
        ]
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

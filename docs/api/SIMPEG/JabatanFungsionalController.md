# JabatanFungsionalController

> **Modul**: SIMPEG (Sistem Informasi Manajemen Kepegawaian)  
> **Base URL**: `/api/simpeg/jabatan-fungsional`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-09-24  
> **Diperbarui**: 2026-09-24  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/simpeg/jabatan-fungsional` | Daftar seluruh jenjang jabatan fungsional dosen dengan pagination & filter | ✅ Staff / Admin SIMPEG |
| POST | `/api/simpeg/jabatan-fungsional` | Menambahkan master jabatan fungsional dosen baru | ✅ Admin SIMPEG |
| GET | `/api/simpeg/jabatan-fungsional/master/golongan` | Daftar distinct golongan jabatan fungsional akademik | ✅ Staff / Admin SIMPEG |

---

## GET /api/simpeg/jabatan-fungsional

> Mengambil daftar master jenjang jabatan fungsional akademik (Jafung) dosen.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari nama jafung atau nama golongan |
| `golongan` | string | ❌ | — | Filter golongan (`asisten_ahli`, `lektor`, `lektor_kepala`, `guru_besar`) |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan (`nama`, `golongan`, `angka_kredit_min`, `created_at`) |
| `sort_order` | string | ❌ | `desc` | Arah urutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Limit per halaman (default 15, maks 100) |
| `page` | integer | ❌ | `1` | Nomor halaman (default 1) |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "data": [
        {
            "id": 1,
            "nama": "Asisten Ahli (100)",
            "golongan": "asisten_ahli",
            "angka_kredit_min": 100,
            "angka_kredit_max": 150,
            "created_at": "2026-09-24T10:00:00.000000Z",
            "updated_at": "2026-09-24T10:00:00.000000Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1,
        "from": 1,
        "to": 1
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

---

## POST /api/simpeg/jabatan-fungsional

> Menambahkan data master jabatan fungsional dosen baru.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "nama": "Tenaga Pengajar",
    "angka_kredit_min": 0,
    "angka_kredit_max": 100,
    "golongan": "asisten_ahli"
}
```

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Jabatan Fungsional berhasil dibuat.",
    "data": {
        "id": 2,
        "nama": "Tenaga Pengajar",
        "angka_kredit_min": 0,
        "angka_kredit_max": 100,
        "golongan": "asisten_ahli",
        "created_at": "2026-09-24T10:05:00.000000Z",
        "updated_at": "2026-09-24T10:05:00.000000Z"
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki hak akses (permission) untuk menambah Jabatan Fungsional."
}
```

**422 Unprocessable Entity**
```json
{
    "message": "The nama has already been taken.",
    "errors": {
        "nama": [
            "The nama has already been taken."
        ]
    }
}
```

---

## GET /api/simpeg/jabatan-fungsional/master/golongan

> Mengambil daftar unik opsi golongan jabatan fungsional akademik untuk dropdown form.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "data": [
        {
            "value": "asisten_ahli",
            "label": "Asisten Ahli"
        },
        {
            "value": "lektor",
            "label": "Lektor"
        },
        {
            "value": "lektor_kepala",
            "label": "Lektor Kepala"
        },
        {
            "value": "guru_besar",
            "label": "Guru Besar"
        }
    ]
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

> **Catatan**: Master data jabatan fungsional mengacu ke tabel `simpeg_jabatan_fungsional_akademik`. Tidak ada fitur hard-delete sembarangan karena jenjang jafung terikat dengan riwayat kepangkatan dan usulan kenaikan jafung dosen. Password dan data sensitif tidak pernah disertakan.

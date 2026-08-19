# SpmbSekolahMitraController

> **Modul**: SPMB  
> **Base URL**: `/api/spmb`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-08-19  
> **Diperbarui**: 2026-08-19

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/spmb/sekolah-mitra` | Daftar semua Sekolah Mitra | ✅ Admin |
| POST | `/api/spmb/sekolah-mitra` | Buat data Sekolah Mitra baru | ✅ Admin |

---

## [GET] /api/spmb/sekolah-mitra

> Mendapatkan daftar Sekolah Mitra dengan dukungan pencarian, pagination, dan filter.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari berdasarkan `nama_sekolah` atau `npsn` |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan (`created_at`, `updated_at`, `nama_sekolah`, `npsn`) |
| `sort_order` | string | ❌ | `desc` | Arah urutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data retrieved successfully",
    "data": [
        {
            "id": 1,
            "npsn": "12345678",
            "nama_sekolah": "SMAN 1 Contoh",
            "alamat": "Jl. Pendidikan No. 1",
            "akreditasi": "A",
            "telepon": "021-1234567",
            "email": "info@sman1.sch.id",
            "is_active": 1,
            "created_at": "2026-08-19 22:19:00",
            "updated_at": "2026-08-19 22:19:00"
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
        "sort_by": "created_at",
        "sort_order": "desc"
    }
}
```

---

## [POST] /api/spmb/sekolah-mitra

> Membuat data Sekolah Mitra baru di tabel `spmb_sekolah_mitra`.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "npsn": "string, nullable, unique, max:20",
    "nama_sekolah": "string, required, max:255",
    "alamat": "string, nullable",
    "akreditasi": "string, nullable, max:10",
    "telepon": "string, nullable, max:20",
    "email": "string, nullable, email, max:255",
    "is_active": "boolean, nullable"
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Sekolah mitra berhasil ditambahkan",
    "data": {
        "id": 2,
        "npsn": "87654321",
        "nama_sekolah": "SMK 2 Maju",
        "alamat": "Jl. Teknologi No. 42",
        "akreditasi": "B",
        "telepon": "021-7654321",
        "email": "contact@smk2.sch.id",
        "is_active": 1,
        "created_at": "2026-08-19 22:20:00",
        "updated_at": "2026-08-19 22:20:00"
    }
}
```

### Response Error

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "nama_sekolah": [
            "The nama sekolah field is required."
        ],
        "npsn": [
            "The npsn has already been taken."
        ]
    }
}
```

# UnitKerjaController

> **Modul**: SIMPEG (Sistem Informasi Manajemen Kepegawaian)  
> **Base URL**: `/api/simpeg/unit-kerja`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-09-24  
> **Diperbarui**: 2026-09-24  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/simpeg/unit-kerja` | Daftar seluruh unit kerja (list flat atau hirarki tree) | ✅ Staff / Admin SIMPEG |
| POST | `/api/simpeg/unit-kerja` | Menambahkan unit kerja baru | ✅ Admin SIMPEG (`simpeg.unit_kerja.create` / `manage`) |
| GET | `/api/simpeg/unit-kerja/{id}` | Detail rincian unit kerja beserta relasi | ✅ Staff / Admin SIMPEG |
| PUT | `/api/simpeg/unit-kerja/{id}` | Memperbarui data unit kerja | ✅ Admin SIMPEG (`simpeg.unit_kerja.update` / `manage`) |
| DELETE | `/api/simpeg/unit-kerja/{id}` | Menghapus data unit kerja | ✅ Admin SIMPEG (`simpeg.unit_kerja.delete` / `manage`) |

---

## GET /api/simpeg/unit-kerja

> Mengambil daftar master unit kerja & SOTK kampus dengan pagination atau tree view.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tree` | boolean | ❌ | — | Jika ada parameter ini (`?tree=1`), mengembalikan struktur hirarki pohon induk-anak |
| `search` | string | ❌ | — | Cari nama unit kerja atau kode |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan |
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
            "induk_id": null,
            "kode": "REK-01",
            "nama": "Rektorat",
            "tipe": "rektorat",
            "is_active": true,
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

## POST /api/simpeg/unit-kerja

> Menambahkan data unit kerja baru.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "induk_id": 1,
    "kode": "FT-01",
    "nama": "Fakultas Teknik",
    "tipe": "fakultas",
    "is_active": true
}
```

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Unit Kerja berhasil dibuat.",
    "data": {
        "id": 2,
        "induk_id": 1,
        "kode": "FT-01",
        "nama": "Fakultas Teknik",
        "tipe": "fakultas",
        "is_active": true,
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
    "message": "Anda tidak memiliki hak akses (permission) untuk menambah Unit Kerja."
}
```

**422 Unprocessable Entity**
```json
{
    "message": "The kode has already been taken.",
    "errors": {
        "kode": [
            "The kode has already been taken."
        ]
    }
}
```

---

## GET /api/simpeg/unit-kerja/{id}

> Mengambil detail spesifik unit kerja beserta parent, children, jabatan, dan daftar pegawai.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "data": {
        "id": 2,
        "induk_id": 1,
        "kode": "FT-01",
        "nama": "Fakultas Teknik",
        "tipe": "fakultas",
        "is_active": true,
        "parent": {
            "id": 1,
            "nama": "Rektorat"
        },
        "children": [],
        "jabatan": [],
        "pegawai": []
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

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data Unit Kerja tidak ditemukan."
}
```

---

## PUT /api/simpeg/unit-kerja/{id}

> Memperbarui informasi unit kerja.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "induk_id": 1,
    "kode": "FT-01",
    "nama": "Fakultas Teknik dan Ilmu Komputer",
    "tipe": "fakultas",
    "is_active": true
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Unit Kerja berhasil diperbarui.",
    "data": {
        "id": 2,
        "induk_id": 1,
        "kode": "FT-01",
        "nama": "Fakultas Teknik dan Ilmu Komputer",
        "tipe": "fakultas",
        "is_active": true
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
    "message": "Anda tidak memiliki hak akses (permission) untuk mengubah Unit Kerja."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data Unit Kerja tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "message": "The kode has already been taken.",
    "errors": {
        "kode": [
            "The kode has already been taken."
        ]
    }
}
```

---

## DELETE /api/simpeg/unit-kerja/{id}

> Menghapus data unit kerja secara aman.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Unit Kerja berhasil dihapus."
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
    "message": "Anda tidak memiliki hak akses (permission) untuk menghapus Unit Kerja."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data Unit Kerja tidak ditemukan."
}
```

> **Catatan**: Operasi DELETE melakukan hard-delete atau pembersihan data unit kerja. Jika unit kerja memiliki relasi anak (`children`) atau riwayat jabatan/pegawai yang terikat, sistem akan memvalidasi integritas relasi untuk mencegah *orphaned records*. Tidak ada data sensitif atau password yang dikembalikan dalam response ini.

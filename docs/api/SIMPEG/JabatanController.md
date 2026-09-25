# JabatanController

> **Modul**: SIMPEG (Sistem Informasi Manajemen Kepegawaian)  
> **Base URL**: `/api/simpeg/jabatan`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-09-24  
> **Diperbarui**: 2026-09-24  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/simpeg/jabatan` | Daftar jabatan dengan filter unit kerja dan tipe | ✅ Staff / Admin SIMPEG |
| POST | `/api/simpeg/jabatan` | Menambahkan formasi jabatan baru | ✅ Admin SIMPEG (`simpeg.jabatan.create` / `manage`) |
| GET | `/api/simpeg/jabatan/{id}` | Detail formasi jabatan | ✅ Staff / Admin SIMPEG |
| PUT | `/api/simpeg/jabatan/{id}` | Memperbarui data formasi jabatan | ✅ Admin SIMPEG (`simpeg.jabatan.update` / `manage`) |
| DELETE | `/api/simpeg/jabatan/{id}` | Menghapus data formasi jabatan | ✅ Admin SIMPEG (`simpeg.jabatan.delete` / `manage`) |

---

## GET /api/simpeg/jabatan

> Mengambil daftar master formasi jabatan pegawai.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `unit_kerja_id` | integer | ❌ | — | Filter ID Unit Kerja |
| `tipe` | string | ❌ | — | Filter tipe jabatan: `struktural`, `fungsional`, `teknis` |
| `search` | string | ❌ | — | Filter kata kunci nama jabatan |
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
            "unit_kerja_id": 1,
            "nama": "Kepala Biro Kepegawaian",
            "tipe": "struktural",
            "level_jabatan": 2,
            "is_active": true,
            "created_at": "2026-09-24T10:00:00.000000Z",
            "updated_at": "2026-09-24T10:00:00.000000Z",
            "unit_kerja": {
                "id": 1,
                "nama": "Biro Kepegawaian",
                "kode": "BKP"
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

## POST /api/simpeg/jabatan

> Menambahkan data formasi jabatan baru.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "unit_kerja_id": 1,
    "nama": "Kepala Biro Kepegawaian",
    "tipe": "struktural",
    "level_jabatan": 2,
    "is_active": true
}
```

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Jabatan berhasil dibuat.",
    "data": {
        "id": 1,
        "unit_kerja_id": 1,
        "nama": "Kepala Biro Kepegawaian",
        "tipe": "struktural",
        "level_jabatan": 2,
        "is_active": true,
        "created_at": "2026-09-24T10:00:00.000000Z",
        "updated_at": "2026-09-24T10:00:00.000000Z"
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
    "message": "Anda tidak memiliki hak akses (permission) untuk menambah data Jabatan."
}
```

**422 Unprocessable Entity**
```json
{
    "message": "The selected unit kerja id is invalid.",
    "errors": {
        "unit_kerja_id": [
            "The selected unit kerja id is invalid."
        ]
    }
}
```

---

## GET /api/simpeg/jabatan/{id}

> Mengambil detail spesifik formasi jabatan beserta relasi unit kerja dan riwayat jabatan pegawai.

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
        "id": 1,
        "unit_kerja_id": 1,
        "nama": "Kepala Biro Kepegawaian",
        "tipe": "struktural",
        "level_jabatan": 2,
        "is_active": true,
        "unit_kerja": {
            "id": 1,
            "nama": "Biro Kepegawaian",
            "kode": "BKP"
        },
        "riwayat_jabatan": []
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
    "message": "Data Jabatan tidak ditemukan."
}
```

---

## PUT /api/simpeg/jabatan/{id}

> Memperbarui informasi formasi jabatan.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "unit_kerja_id": 1,
    "nama": "Kepala Biro Sumber Daya Manusia",
    "tipe": "struktural",
    "level_jabatan": 2,
    "is_active": true
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Jabatan berhasil diperbarui.",
    "data": {
        "id": 1,
        "unit_kerja_id": 1,
        "nama": "Kepala Biro Sumber Daya Manusia",
        "tipe": "struktural",
        "level_jabatan": 2,
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
    "message": "Anda tidak memiliki hak akses (permission) untuk mengubah data Jabatan."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data Jabatan tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "message": "The selected unit kerja id is invalid.",
    "errors": {
        "unit_kerja_id": [
            "The selected unit kerja id is invalid."
        ]
    }
}
```

---

## DELETE /api/simpeg/jabatan/{id}

> Menghapus data formasi jabatan.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Jabatan berhasil dihapus."
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
    "message": "Anda tidak memiliki hak akses (permission) untuk menghapus data Jabatan."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data Jabatan tidak ditemukan."
}
```

> **Catatan**: Operasi DELETE menghapus formasi jabatan (hard-delete / cascade). Apabila jabatan masih terikat dengan pegawai aktif atau riwayat penugasan SK pegawai, sistem memblokir penghapusan untuk menjaga konsistensi data riwayat karir pegawai. Tidak ada data rahasia/password dalam payload response ini.

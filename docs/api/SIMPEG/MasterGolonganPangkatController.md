# MasterGolonganPangkatController

> **Modul**: SIMPEG (Sistem Informasi Manajemen Kepegawaian)  
> **Base URL**: `/api/simpeg/master-golongan-pangkat`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-09-26  
> **Diperbarui**: 2026-09-26  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/simpeg/master-golongan-pangkat` | Daftar master jenjang golongan & pangkat dengan pagination & filter | ✅ Staff / Admin SIMPEG |
| POST | `/api/simpeg/master-golongan-pangkat` | Menambahkan data jenjang golongan baru | ✅ Admin SIMPEG |
| GET | `/api/simpeg/master-golongan-pangkat/{id}` | Detail data master jenjang golongan | ✅ Staff / Admin SIMPEG |
| PUT | `/api/simpeg/master-golongan-pangkat/{id}` | Memperbarui data jenjang golongan | ✅ Admin SIMPEG |
| DELETE | `/api/simpeg/master-golongan-pangkat/{id}` | Menghapus data jenjang golongan (soft delete) | ✅ Admin SIMPEG |

---

## GET /api/simpeg/master-golongan-pangkat

> Mengambil daftar master jenjang golongan dan pangkat pegawai.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Kata kunci pencarian kode, nama, pangkat, atau ruang |
| `is_active` | boolean | ❌ | — | Filter status keaktifan (`true` / `false`) |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan (`created_at`, `updated_at`, `nama`, `kode`, `urutan`, `pangkat`, `ruang`, `is_active`) |
| `sort_order` | string | ❌ | `desc` | Arah pengurutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Data master jenjang golongan & pangkat berhasil dimuat.",
    "data": [
        {
            "id": 1,
            "kode": "III/a",
            "nama": "Penata Muda (III/a)",
            "pangkat": "Penata Muda",
            "ruang": "a",
            "urutan": 9,
            "is_active": true,
            "created_at": "2026-09-26T10:00:00.000000Z",
            "updated_at": "2026-09-26T10:00:00.000000Z"
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
        "search": "",
        "sort_by": "created_at",
        "sort_order": "desc"
    }
}
```

### Response Error (401 Unauthorized)

```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

---

## POST /api/simpeg/master-golongan-pangkat

> Menambahkan data master jenjang golongan dan pangkat baru.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "kode": "III/a",
    "nama": "Penata Muda (III/a)",
    "pangkat": "Penata Muda",
    "ruang": "a",
    "urutan": 9,
    "is_active": true
}
```

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Data master jenjang golongan berhasil dibuat.",
    "data": {
        "id": 1,
        "kode": "III/a",
        "nama": "Penata Muda (III/a)",
        "pangkat": "Penata Muda",
        "ruang": "a",
        "urutan": 9,
        "is_active": true,
        "created_at": "2026-09-26T10:00:00.000000Z",
        "updated_at": "2026-09-26T10:00:00.000000Z"
    }
}
```

### Response Error (422 Unprocessable Content)

```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "kode": [
            "Kode golongan (misal: III/a) wajib diisi."
        ],
        "nama": [
            "Nama jenjang pangkat/golongan wajib diisi."
        ]
    }
}
```

### Response Error (403 Forbidden)

```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

---

## GET /api/simpeg/master-golongan-pangkat/{id}

> Mengambil detail data master jenjang golongan berdasarkan ID.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Detail master jenjang golongan berhasil dimuat.",
    "data": {
        "id": 1,
        "kode": "III/a",
        "nama": "Penata Muda (III/a)",
        "pangkat": "Penata Muda",
        "ruang": "a",
        "urutan": 9,
        "is_active": true,
        "created_at": "2026-09-26T10:00:00.000000Z",
        "updated_at": "2026-09-26T10:00:00.000000Z"
    }
}
```

### Response Error (404 Not Found)

```json
{
    "status": "error",
    "message": "Data tidak ditemukan."
}
```

---

## PUT /api/simpeg/master-golongan-pangkat/{id}

> Memperbarui data master jenjang golongan berdasarkan ID.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "kode": "III/a",
    "nama": "Penata Muda (III/a)",
    "pangkat": "Penata Muda",
    "ruang": "a",
    "urutan": 9,
    "is_active": true
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Data master jenjang golongan berhasil diperbarui.",
    "data": {
        "id": 1,
        "kode": "III/a",
        "nama": "Penata Muda (III/a)",
        "pangkat": "Penata Muda",
        "ruang": "a",
        "urutan": 9,
        "is_active": true,
        "created_at": "2026-09-26T10:00:00.000000Z",
        "updated_at": "2026-09-26T10:00:00.000000Z"
    }
}
```

### Response Error (422 Unprocessable Content)

```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "kode": [
            "Kode golongan wajib diisi."
        ]
    }
}
```

### Response Error (404 Not Found)

```json
{
    "status": "error",
    "message": "Data tidak ditemukan."
}
```

---

## DELETE /api/simpeg/master-golongan-pangkat/{id}

> Menghapus data master jenjang golongan.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Data master jenjang golongan berhasil dihapus."
}
```

### Response Error (403 Forbidden)

```json
{
    "status": "error",
    "message": "Anda tidak memiliki hak untuk menghapus data master golongan."
}
```

### Response Error (404 Not Found)

```json
{
    "status": "error",
    "message": "Data tidak ditemukan."
}
```

### Catatan Penting
- Endpoint `DELETE` menerapkan **soft-delete** (data tidak dihapus permanen dari basis data, melainkan mengisi stempel waktu pada kolom `deleted_at`).

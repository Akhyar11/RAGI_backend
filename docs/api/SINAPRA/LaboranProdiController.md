# LaboranProdiController

> **Modul**: SINAPRA (Sarana, Prasarana, & Aset)  
> **Base URL**: `/api/sinapra/laboran-prodi`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-09-30  
> **Diperbarui**: 2026-09-30  

Dokumentasi endpoint penugasan Laboran ke Program Studi (SIAKAD) untuk isolasi akses data aset dan ruangan per Program Studi.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth / Permission |
|---|---|---|---|
| GET | `/api/sinapra/laboran-prodi` | Daftar penugasan laboran per Program Studi | ✅ `sinapra.ruangan.read` |
| POST | `/api/sinapra/laboran-prodi` | Tugaskan laboran ke Program Studi | ✅ `sinapra.laboran.manage` |
| DELETE | `/api/sinapra/laboran-prodi/{laboranProdi}` | Hapus penugasan laboran dari Program Studi | ✅ `sinapra.laboran.manage` |

---

## GET /api/sinapra/laboran-prodi

Deskripsi: Mengambil daftar penugasan laboran per program studi dengan dukungan pencarian dan filter.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Pencarian nama/email laboran atau nama/kode prodi |
| `program_studi_id` | integer | ❌ | — | Filter ID program studi SIAKAD |
| `user_id` | integer | ❌ | — | Filter ID user laboran |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan (`created_at`, `id`) |
| `sort_order` | string | ❌ | `desc` | Arah urutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Daftar penugasan laboran program studi berhasil diambil",
    "data": [
        {
            "id": 1,
            "user_id": 8,
            "program_studi_id": 1,
            "is_primary": true,
            "created_at": "2026-09-30T10:00:00.000000Z",
            "updated_at": "2026-09-30T10:00:00.000000Z",
            "user": {
                "id": 8,
                "name": "Budi Laboran TI",
                "email": "laboran.ti@kampus.ac.id",
                "pegawai": {
                    "id": 3,
                    "nama_lengkap": "Budi Santoso, S.Kom.",
                    "nip": "199001012015011002"
                }
            },
            "program_studi": {
                "id": 1,
                "kode_prodi": "TI-S1",
                "nama": "S1 Teknik Informatika",
                "jenjang": "S1",
                "fakultas": {
                    "id": 1,
                    "nama": "Fakultas Teknik dan Ilmu Komputer"
                }
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
        "program_studi_id": null,
        "user_id": null,
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

### Response Error (403 Forbidden)
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

---

## POST /api/sinapra/laboran-prodi

Deskripsi: Menugaskan user laboran ke sebuah Program Studi SIAKAD.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body
```json
{
    "user_id": 8,
    "program_studi_id": 1,
    "is_primary": true
}
```

### Response Sukses (201 Created)
```json
{
    "status": "success",
    "message": "Laboran berhasil ditugaskan ke program studi",
    "data": {
        "id": 1,
        "user_id": 8,
        "program_studi_id": 1,
        "is_primary": true,
        "created_at": "2026-09-30T10:00:00.000000Z",
        "updated_at": "2026-09-30T10:00:00.000000Z",
        "user": {
            "id": 8,
            "name": "Budi Laboran TI",
            "email": "laboran.ti@kampus.ac.id"
        },
        "program_studi": {
            "id": 1,
            "kode_prodi": "TI-S1",
            "nama": "S1 Teknik Informatika"
        }
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

### Response Error (403 Forbidden)
```json
{
    "status": "error",
    "message": "Anda tidak memiliki hak akses untuk menugaskan laboran program studi."
}
```

### Response Error (422 Validation Error)
```json
{
    "status": "error",
    "message": "Validasi gagal.",
    "errors": {
        "user_id": [
            "User yang dipilih tidak valid atau bukan laboran."
        ],
        "program_studi_id": [
            "Program studi tidak ditemukan di database SIAKAD."
        ]
    }
}
```

---

## DELETE /api/sinapra/laboran-prodi/{laboranProdi}

Deskripsi: Menghapus penugasan laboran dari Program Studi.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Penugasan laboran program studi berhasil dihapus",
    "data": null
}
```

### Response Error (401 Unauthorized)
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

### Response Error (403 Forbidden)
```json
{
    "status": "error",
    "message": "Anda tidak memiliki hak akses untuk menghapus penugasan laboran program studi."
}
```

### Response Error (404 Not Found)
```json
{
    "status": "error",
    "message": "Penugasan laboran program studi tidak ditemukan."
}
```

---

> **Catatan:**
> - Aksi `DELETE` melakukan penghapusan record relasi secara fisik (hard delete) dari tabel `sinapra_laboran_prodi` tanpa menghapus akun user maupun entitas Program Studi SIAKAD.
> - Field sensitif kredensial seperti `password` dan `remember_token` tidak pernah dikembalikan dalam response objek `user`.

# MasterReferensiController

> **Modul**: System  
> **Base URL**: `/api/master-referensi` & `/api/referensi`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat/Diperbarui**: 2026-09-18

Controller ini menangani manajemen data item referensi kampus (Agama, Status Sipil, Kewarganegaraan, Hubungan Keluarga, dll) lintas modul universitas (SSO, SPMB, SIAKAD, SIMPEG, SIKEU).

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/master-referensi` | Daftar item referensi + filter & pagination | ✅ Admin |
| GET | `/api/master-referensi/categories` | Ringkasan kategori tipe & modul | ✅ Admin |
| POST | `/api/master-referensi` | Tambah item referensi baru | ✅ Admin |
| GET | `/api/master-referensi/{id}` | Detail satu item referensi | ✅ Admin |
| PUT | `/api/master-referensi/{id}` | Update item referensi | ✅ Admin |
| PATCH | `/api/master-referensi/{id}/toggle` | Toggle status aktif item referensi | ✅ Admin |
| DELETE | `/api/master-referensi/{id}` | Hapus item referensi | ✅ Admin |
| GET | `/api/referensi/{tipe}` | Lookup dropdown data referensi publik | ❌ Publik |

---

## Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ (Kecuali endpoint publik `/api/referensi/{tipe}`) |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ (POST / PUT / PATCH) |

---

## GET /api/master-referensi

> Mengambil daftar seluruh item referensi dengan filter modul, tipe, status aktif, pencarian, dan paginasi.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Pencarian nama, kode, atau tipe |
| `modul` | string | ❌ | `all` | Filter modul (`all`, `global`, `spmb`, `siakad`, `simpeg`, `sikeu`) |
| `tipe` | string | ❌ | `all` | Filter kode kategori tipe referensi |
| `is_active` | boolean | ❌ | — | Filter status aktif (`true` / `false` atau `1` / `0`) |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan (`tipe`, `kode`, `nama`, `modul`, `urutan`, `created_at`, `id`) |
| `sort_order` | string | ❌ | `desc` | Arah urutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `20` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses (200 OK - dengan Pagination)

```json
{
  "status": "success",
  "message": "Daftar master referensi berhasil diambil.",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "tipe": "agama",
        "modul": "global",
        "kode": "ISLAM",
        "nama": "Islam",
        "urutan": 1,
        "is_active": true,
        "created_at": "2026-09-17T04:00:00.000000Z",
        "updated_at": "2026-09-17T04:00:00.000000Z"
      }
    ],
    "first_page_url": "http://localhost:8000/api/master-referensi?page=1",
    "from": 1,
    "last_page": 1,
    "last_page_url": "http://localhost:8000/api/master-referensi?page=1",
    "next_page_url": null,
    "path": "http://localhost:8000/api/master-referensi",
    "per_page": 20,
    "prev_page_url": null,
    "to": 1,
    "total": 1
  }
}
```

---

## GET /api/master-referensi/categories

> Mengambil ringkasan kategori tipe referensi aktif dan jumlah item per modul untuk tab navigasi filter.

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "data": {
    "categories": [
      {
        "tipe": "agama",
        "nama": "Agama",
        "modul": "global",
        "deskripsi": "Daftar agama resmi di Indonesia",
        "total_items": 6
      }
    ],
    "modules": [
      {
        "modul": "global",
        "total_items": 10
      },
      {
        "modul": "spmb",
        "total_items": 20
      }
    ]
  }
}
```

---

## POST /api/master-referensi

> Menambahkan data item referensi baru ke dalam sistem.

### Request Body

```json
{
  "tipe": "agama",
  "modul": "global",
  "kode": "KATOLIK",
  "nama": "Katolik",
  "urutan": 2,
  "is_active": true
}
```

### Response Sukses (201 Created)

```json
{
  "status": "success",
  "message": "Data referensi berhasil ditambahkan.",
  "data": {
    "id": 2,
    "tipe": "agama",
    "modul": "global",
    "kode": "KATOLIK",
    "nama": "Katolik",
    "urutan": 2,
    "is_active": true,
    "created_at": "2026-09-18T08:00:00.000000Z",
    "updated_at": "2026-09-18T08:00:00.000000Z"
  }
}
```

---

## GET /api/master-referensi/{id}

> Mengambil rincian satu data item referensi berdasarkan ID.

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "data": {
    "id": 1,
    "tipe": "agama",
    "modul": "global",
    "kode": "ISLAM",
    "nama": "Islam",
    "urutan": 1,
    "is_active": true,
    "created_at": "2026-09-17T04:00:00.000000Z",
    "updated_at": "2026-09-17T04:00:00.000000Z"
  }
}
```

---

## PUT /api/master-referensi/{id}

> Mengubah data item referensi yang sudah ada.

### Request Body

```json
{
  "tipe": "agama",
  "modul": "global",
  "kode": "ISLAM",
  "nama": "Agama Islam",
  "urutan": 1,
  "is_active": true
}
```

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Data referensi berhasil diperbarui.",
  "data": {
    "id": 1,
    "tipe": "agama",
    "modul": "global",
    "kode": "ISLAM",
    "nama": "Agama Islam",
    "urutan": 1,
    "is_active": true,
    "created_at": "2026-09-17T04:00:00.000000Z",
    "updated_at": "2026-09-18T08:15:00.000000Z"
  }
}
```

---

## PATCH /api/master-referensi/{id}/toggle

> Mengubah status aktif (`is_active`) menjadi aktif atau nonaktif secara instan.

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Status referensi berhasil diubah.",
  "data": {
    "id": 1,
    "tipe": "agama",
    "modul": "global",
    "kode": "ISLAM",
    "nama": "Islam",
    "urutan": 1,
    "is_active": false,
    "created_at": "2026-09-17T04:00:00.000000Z",
    "updated_at": "2026-09-18T08:20:00.000000Z"
  }
}
```

---

## DELETE /api/master-referensi/{id}

> Menghapus data item referensi secara permanen.

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Data referensi berhasil dihapus."
}
```

---

## GET /api/referensi/{tipe}

> Endpoint publik untuk pengisian opsi formulir dropdown berdasarkan kategori tipe referensi.

### Parameter Query (Opsional)

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `modul` | string | ❌ | `all` | Filter modul spesifik (secara otomatis menyertakan modul `global`) |

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "tipe": "agama",
      "modul": "global",
      "kode": "ISLAM",
      "nama": "Islam",
      "urutan": 1,
      "is_active": true,
      "created_at": "2026-09-17T04:00:00.000000Z",
      "updated_at": "2026-09-17T04:00:00.000000Z"
    }
  ]
}
```

---

## Response Error

### 401 Unauthorized
```json
{
  "status": "error",
  "message": "Unauthenticated."
}
```

### 403 Forbidden
```json
{
  "status": "error",
  "message": "Anda tidak memiliki hak akses untuk mengelola master referensi."
}
```

### 404 Not Found
```json
{
  "status": "error",
  "message": "No query results for model [App\\Models\\System\\MasterReferensi] 999"
}
```

### 422 Unprocessable Entity
```json
{
  "status": "error",
  "message": "Validasi gagal.",
  "errors": {
    "kode": [
      "The kode field is required."
    ],
    "nama": [
      "The nama field is required."
    ]
  }
}
```

---

## Catatan Keamanan & Arsitektur
- **Soft Delete**: Tabel `core_master_referensi` / `spmb_master_referensi` tidak menggunakan *soft delete*; penghapusan dilakukan secara *hard delete*.
- **Password**: Model ini tidak mengelola atau mengembalikan field `password` dalam respons apapun.

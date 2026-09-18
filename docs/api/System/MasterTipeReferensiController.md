# MasterTipeReferensiController

> **Modul**: System  
> **Base URL**: `/api/master-tipe-referensi` & `/api/tipe-referensi`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat/Diperbarui**: 2026-09-18

Controller ini menangani pengelolaan kategori induk master tipe referensi (`core_tipe_referensi`) sistem kampus. Tipe referensi berfungsi sebagai klasifikasi opsi untuk seluruh data referensi.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/master-tipe-referensi` | Daftar semua tipe referensi + filter & pagination | ✅ Admin |
| POST | `/api/master-tipe-referensi` | Tambah kategori tipe referensi baru | ✅ Admin |
| GET | `/api/master-tipe-referensi/{id}` | Detail satu tipe referensi | ✅ Admin |
| PUT | `/api/master-tipe-referensi/{id}` | Update data tipe referensi | ✅ Admin |
| PATCH | `/api/master-tipe-referensi/{id}/toggle` | Toggle status aktif tipe referensi | ✅ Admin |
| DELETE | `/api/master-tipe-referensi/{id}` | Hapus kategori tipe referensi | ✅ Admin |
| GET | `/api/tipe-referensi` | Lookup master tipe untuk dropdown | ❌ Publik |

---

## Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ (Kecuali endpoint publik `/api/tipe-referensi`) |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ (POST / PUT / PATCH) |

---

## GET /api/master-tipe-referensi

> Menampilkan daftar seluruh kategori tipe referensi beserta total item terkait (`items_count`).

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Pencarian kode, nama, atau deskripsi |
| `modul` | string | ❌ | `all` | Filter modul (`all`, `global`, `spmb`, `siakad`, `simpeg`, `sikeu`) |
| `is_active` | boolean | ❌ | — | Filter status aktif (`true` / `false` atau `1` / `0`) |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan (`kode`, `nama`, `modul`, `urutan`, `created_at`, `id`) |
| `sort_order` | string | ❌ | `desc` | Arah urutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `20` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses (200 OK - dengan Pagination)

```json
{
  "status": "success",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "kode": "agama",
        "nama": "Agama",
        "modul": "global",
        "deskripsi": "Daftar agama resmi",
        "urutan": 1,
        "is_active": true,
        "items_count": 6,
        "created_at": "2026-09-17T04:00:00.000000Z",
        "updated_at": "2026-09-17T04:00:00.000000Z"
      }
    ],
    "first_page_url": "http://localhost:8000/api/master-tipe-referensi?page=1",
    "from": 1,
    "last_page": 1,
    "last_page_url": "http://localhost:8000/api/master-tipe-referensi?page=1",
    "next_page_url": null,
    "path": "http://localhost:8000/api/master-tipe-referensi",
    "per_page": 20,
    "prev_page_url": null,
    "to": 1,
    "total": 1
  }
}
```

---

## POST /api/master-tipe-referensi

> Membuat kategori tipe referensi baru.

### Request Body

```json
{
  "kode": "kewarganegaraan",
  "nama": "Kewarganegaraan",
  "modul": "global",
  "deskripsi": "Kewarganegaraan civitas akademika",
  "urutan": 3,
  "is_active": true
}
```

### Response Sukses (201 Created)

```json
{
  "status": "success",
  "message": "Tipe referensi berhasil ditambahkan.",
  "data": {
    "id": 3,
    "kode": "kewarganegaraan",
    "nama": "Kewarganegaraan",
    "modul": "global",
    "deskripsi": "Kewarganegaraan civitas akademika",
    "urutan": 3,
    "is_active": true,
    "items_count": 0,
    "created_at": "2026-09-18T08:00:00.000000Z",
    "updated_at": "2026-09-18T08:00:00.000000Z"
  }
}
```

---

## GET /api/master-tipe-referensi/{id}

> Mengambil detail satu kategori tipe referensi berdasarkan ID atau kode unik.

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "data": {
    "id": 1,
    "kode": "agama",
    "nama": "Agama",
    "modul": "global",
    "deskripsi": "Daftar agama resmi",
    "urutan": 1,
    "is_active": true,
    "items_count": 6,
    "created_at": "2026-09-17T04:00:00.000000Z",
    "updated_at": "2026-09-17T04:00:00.000000Z"
  }
}
```

---

## PUT /api/master-tipe-referensi/{id}

> Mengubah data tipe referensi. Jika `kode` diubah, seluruh data item pada tabel referensi terkait akan otomatis diperbarui dalam satu transaksi database.

### Request Body

```json
{
  "kode": "agama",
  "nama": "Agama & Kepercayaan",
  "modul": "global",
  "deskripsi": "Daftar agama dan keyakinan resmi",
  "urutan": 1,
  "is_active": true
}
```

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Tipe referensi berhasil diperbarui.",
  "data": {
    "id": 1,
    "kode": "agama",
    "nama": "Agama & Kepercayaan",
    "modul": "global",
    "deskripsi": "Daftar agama dan keyakinan resmi",
    "urutan": 1,
    "is_active": true,
    "items_count": 6,
    "created_at": "2026-09-17T04:00:00.000000Z",
    "updated_at": "2026-09-18T08:30:00.000000Z"
  }
}
```

---

## PATCH /api/master-tipe-referensi/{id}/toggle

> Mengubah status aktif (`is_active`) tipe referensi.

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Status tipe referensi Agama berhasil diubah.",
  "data": {
    "id": 1,
    "kode": "agama",
    "nama": "Agama",
    "modul": "global",
    "deskripsi": "Daftar agama resmi",
    "urutan": 1,
    "is_active": false,
    "items_count": 6,
    "created_at": "2026-09-17T04:00:00.000000Z",
    "updated_at": "2026-09-18T08:35:00.000000Z"
  }
}
```

---

## DELETE /api/master-tipe-referensi/{id}

> Menghapus tipe referensi. Sistem akan menolak jika tipe tersebut masih memiliki data item.

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Tipe referensi 'Agama' berhasil dihapus."
}
```

---

## GET /api/tipe-referensi

> Endpoint publik untuk pengisian dropdown master tipe di formulir.

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "kode": "agama",
      "nama": "Agama",
      "modul": "global",
      "deskripsi": "Daftar agama resmi",
      "urutan": 1,
      "is_active": true,
      "items_count": 6,
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
  "message": "Anda tidak memiliki hak akses untuk mengelola master tipe referensi."
}
```

### 404 Not Found
```json
{
  "status": "error",
  "message": "No query results for model [App\\Models\\System\\MasterTipeReferensi] 999"
}
```

### 422 Unprocessable Entity
```json
{
  "status": "error",
  "message": "Tipe referensi 'Agama' tidak dapat dihapus karena masih memiliki 6 data item referensi. Harap hapus atau alihkan item terlebih dahulu."
}
```

---

## Catatan Keamanan & Arsitektur
- **Soft Delete**: Tabel `core_tipe_referensi` tidak menggunakan *soft delete*; penghapusan dilakukan secara *hard delete*.
- **Password**: Model ini tidak mengelola atau mengembalikan field `password` dalam respons apapun.

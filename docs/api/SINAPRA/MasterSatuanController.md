# MasterSatuanController

> **Modul**: SINAPRA (Sarana, Prasarana, & Aset)  
> **Base URL**: `/api/sinapra/master/satuan`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-09-24  
> **Diperbarui**: 2026-09-24  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/sinapra/master/satuan` | Listing master satuan barang dengan pagination & filter | ✅ |
| POST | `/api/sinapra/master/satuan` | Tambah master satuan barang baru | ✅ |
| GET | `/api/sinapra/master/satuan/{id}` | Detail master satuan barang | ✅ |
| PUT | `/api/sinapra/master/satuan/{id}` | Update data master satuan barang | ✅ |
| DELETE | `/api/sinapra/master/satuan/{id}` | Hapus master satuan barang | ✅ |

---

## Headers Standar
- `Authorization: Bearer {token}`
- `Accept: application/json`
- `Content-Type: application/json`

---

## GET /api/sinapra/master/satuan

Deskripsi: Mengambil daftar master satuan barang yang terdaftar dalam sistem.

### Query Parameters
- `search` (string, optional) - Filter pencarian kode atau nama satuan.
- `is_active` (boolean/integer: `0`, `1`, optional) - Filter status aktif satuan.
- `sort_by` (string, default: `created_at`) - Whitelist: `created_at`, `updated_at`, `kode`, `nama`, `urutan`.
- `sort_order` (enum: `asc`, `desc`, default: `desc`) - Urutan data.
- `per_page` (integer, default: 15, max: 100) - Jumlah data per halaman.
- `page` (integer, default: 1) - Halaman data yang diambil.
- `all` (boolean, optional) - Ambil seluruh data tanpa paginasi (untuk dropdown).

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Daftar master satuan berhasil diambil",
    "data": [
        {
            "id": 1,
            "kode": "UNIT",
            "nama": "Unit",
            "keterangan": "Satuan unit peralatan dan mesin",
            "is_active": true,
            "urutan": 1,
            "created_at": "2026-09-24T18:00:00.000000Z",
            "updated_at": "2026-09-24T18:00:00.000000Z"
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
        "is_active": null,
        "sort_by": "created_at",
        "sort_order": "desc"
    }
}
```

### Response Error
- **401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```
- **403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki kewenangan untuk mengakses sumber daya ini."
}
```

---

## POST /api/sinapra/master/satuan

Deskripsi: Menambahkan master satuan barang baru.

### Request Body
```json
{
    "kode": "PCS",
    "nama": "Pieces",
    "keterangan": "Satuan hitung butir / buah",
    "is_active": true,
    "urutan": 2
}
```

### Response Sukses (201 Created)
```json
{
    "status": "success",
    "message": "Master satuan berhasil ditambahkan",
    "data": {
        "id": 2,
        "kode": "PCS",
        "nama": "Pieces",
        "keterangan": "Satuan hitung butir / buah",
        "is_active": true,
        "urutan": 2,
        "created_at": "2026-09-24T18:05:00.000000Z",
        "updated_at": "2026-09-24T18:05:00.000000Z"
    }
}
```

### Response Error
- **401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```
- **403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki kewenangan untuk mengakses sumber daya ini."
}
```
- **422 Unprocessable Content**
```json
{
    "status": "error",
    "message": "Validasi gagal.",
    "errors": {
        "kode": [
            "Kode satuan sudah digunakan."
        ]
    }
}
```

---

## GET /api/sinapra/master/satuan/{id}

Deskripsi: Mengambil detail satu master satuan berdasarkan ID.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Detail master satuan berhasil diambil",
    "data": {
        "id": 1,
        "kode": "UNIT",
        "nama": "Unit",
        "keterangan": "Satuan unit peralatan dan mesin",
        "is_active": true,
        "urutan": 1,
        "created_at": "2026-09-24T18:00:00.000000Z",
        "updated_at": "2026-09-24T18:00:00.000000Z"
    }
}
```

### Response Error
- **401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```
- **403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki kewenangan untuk mengakses sumber daya ini."
}
```
- **404 Not Found**
```json
{
    "status": "error",
    "message": "Master satuan tidak ditemukan."
}
```

---

## PUT /api/sinapra/master/satuan/{id}

Deskripsi: Memperbarui data master satuan barang.

### Request Body
```json
{
    "kode": "UNIT",
    "nama": "Unit Barang",
    "keterangan": "Satuan unit peralatan, mesin, dan mebel",
    "is_active": true,
    "urutan": 1
}
```

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Master satuan berhasil diperbarui",
    "data": {
        "id": 1,
        "kode": "UNIT",
        "nama": "Unit Barang",
        "keterangan": "Satuan unit peralatan, mesin, dan mebel",
        "is_active": true,
        "urutan": 1,
        "created_at": "2026-09-24T18:00:00.000000Z",
        "updated_at": "2026-09-24T18:10:00.000000Z"
    }
}
```

### Response Error
- **401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```
- **403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki kewenangan untuk mengakses sumber daya ini."
}
```
- **404 Not Found**
```json
{
    "status": "error",
    "message": "Master satuan tidak ditemukan."
}
```
- **422 Unprocessable Content**
```json
{
    "status": "error",
    "message": "Validasi gagal.",
    "errors": {
        "kode": [
            "Kode satuan sudah digunakan."
        ]
    }
}
```

---

## DELETE /api/sinapra/master/satuan/{id}

Deskripsi: Menghapus data master satuan barang secara permanen.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Master satuan berhasil dihapus",
    "data": null
}
```

### Response Error
- **401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```
- **403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki kewenangan untuk mengakses sumber daya ini."
}
```
- **404 Not Found**
```json
{
    "status": "error",
    "message": "Master satuan tidak ditemukan."
}
```

---

## Catatan
- Penghapusan master satuan barang adalah hard-delete permanen dari tabel `sinapra_master_satuan` (bukan soft-delete).
- Data kredensial dan password tidak dikembalikan dalam response API.

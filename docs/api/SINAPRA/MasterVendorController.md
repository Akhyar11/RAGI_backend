# MasterVendorController

> **Modul**: SINAPRA (Sarana, Prasarana, & Aset)  
> **Base URL**: `/api/sinapra/master/vendor`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-09-24  
> **Diperbarui**: 2026-09-24  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/sinapra/master/vendor` | Listing master vendor / rekanan kampus dengan pagination & filter | ✅ |
| POST | `/api/sinapra/master/vendor` | Tambah vendor / rekanan baru | ✅ |
| GET | `/api/sinapra/master/vendor/{id}` | Detail vendor / rekanan | ✅ |
| PUT | `/api/sinapra/master/vendor/{id}` | Update data vendor / rekanan | ✅ |
| DELETE | `/api/sinapra/master/vendor/{id}` | Hapus vendor / rekanan (soft delete) | ✅ |

---

## Headers Standar
- `Authorization: Bearer {token}`
- `Accept: application/json`
- `Content-Type: application/json`

---

## GET /api/sinapra/master/vendor

Deskripsi: Mengambil daftar vendor, rekanan pengadaan, institusi kalibrasi, dan mitra penyedia jasa kampus.

### Query Parameters
- `search` (string, optional) - Filter pencarian kode, nama vendor, alamat, email, atau PIC.
- `jenis_rekanan` (enum: `penyedia_barang`, `jasa_maintenance`, `laboratorium_kalibrasi`, `kontraktor`, `umum`, optional) - Filter kategori jenis rekanan.
- `is_active` (boolean/integer: `0`, `1`, optional) - Filter status aktif vendor.
- `sort_by` (string, default: `urutan`) - Whitelist: `created_at`, `kode`, `nama`, `jenis_rekanan`, `urutan`.
- `sort_order` (enum: `asc`, `desc`, default: `asc`) - Urutan data.
- `per_page` (integer, default: 15, max: 100) - Jumlah data per halaman.
- `page` (integer, default: 1) - Halaman data yang diambil.
- `all` (boolean, optional) - Ambil seluruh data tanpa paginasi (untuk dropdown).

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Daftar master vendor berhasil diambil",
    "data": [
        {
            "id": 1,
            "kode": "VND-LAB-01",
            "nama": "PT Precision Kalibrasi Indonesia",
            "jenis_rekanan": "laboratorium_kalibrasi",
            "alamat": "Kawasan Industri Pulogadung No. 18, Jakarta Timur",
            "telepon": "021-4601234",
            "email": "kalibrasi@precision-indo.co.id",
            "pic_nama": "Hendra Setiawan",
            "pic_kontak": "081234567801",
            "nomor_npwp": "01.234.567.8-001.000",
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
        "jenis_rekanan": null,
        "is_active": null,
        "sort_by": "urutan",
        "sort_order": "asc"
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

## POST /api/sinapra/master/vendor

Deskripsi: Menambahkan data vendor / rekanan mitra kampus baru.

### Request Body
```json
{
    "kode": "VND-IT-02",
    "nama": "PT Global Solusi Informatika",
    "jenis_rekanan": "penyedia_barang",
    "alamat": "Jl. Gatot Subroto Kav. 52, Jakarta Selatan",
    "telepon": "021-5291234",
    "email": "sales@globalsolusi.co.id",
    "pic_nama": "Bambang Sudibyo",
    "pic_kontak": "08119876543",
    "nomor_npwp": "01.789.012.3-008.000",
    "is_active": true,
    "urutan": 8
}
```

### Response Sukses (201 Created)
```json
{
    "status": "success",
    "message": "Master vendor berhasil ditambahkan",
    "data": {
        "id": 8,
        "kode": "VND-IT-02",
        "nama": "PT Global Solusi Informatika",
        "jenis_rekanan": "penyedia_barang",
        "alamat": "Jl. Gatot Subroto Kav. 52, Jakarta Selatan",
        "telepon": "021-5291234",
        "email": "sales@globalsolusi.co.id",
        "pic_nama": "Bambang Sudibyo",
        "pic_kontak": "08119876543",
        "nomor_npwp": "01.789.012.3-008.000",
        "is_active": true,
        "urutan": 8,
        "created_at": "2026-09-24T18:10:00.000000Z",
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
- **422 Unprocessable Content**
```json
{
    "status": "error",
    "message": "Validasi gagal.",
    "errors": {
        "kode": [
            "Kode vendor / rekanan sudah digunakan."
        ]
    }
}
```

---

## GET /api/sinapra/master/vendor/{id}

Deskripsi: Mengambil detail satu vendor / rekanan berdasarkan ID.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Detail master vendor berhasil diambil",
    "data": {
        "id": 1,
        "kode": "VND-LAB-01",
        "nama": "PT Precision Kalibrasi Indonesia",
        "jenis_rekanan": "laboratorium_kalibrasi",
        "alamat": "Kawasan Industri Pulogadung No. 18, Jakarta Timur",
        "telepon": "021-4601234",
        "email": "kalibrasi@precision-indo.co.id",
        "pic_nama": "Hendra Setiawan",
        "pic_kontak": "081234567801",
        "nomor_npwp": "01.234.567.8-001.000",
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
    "message": "Master vendor tidak ditemukan."
}
```

---

## PUT /api/sinapra/master/vendor/{id}

Deskripsi: Memperbarui informasi profil atau kontak vendor / rekanan.

### Request Body
```json
{
    "kode": "VND-LAB-01",
    "nama": "PT Precision Kalibrasi Indonesia (Pusat)",
    "jenis_rekanan": "laboratorium_kalibrasi",
    "alamat": "Kawasan Industri Pulogadung Blok B No. 18, Jakarta Timur",
    "telepon": "021-4601234",
    "email": "cs@precision-indo.co.id",
    "pic_nama": "Hendra Setiawan, S.T.",
    "pic_kontak": "081234567801",
    "nomor_npwp": "01.234.567.8-001.000",
    "is_active": true,
    "urutan": 1
}
```

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Master vendor berhasil diperbarui",
    "data": {
        "id": 1,
        "kode": "VND-LAB-01",
        "nama": "PT Precision Kalibrasi Indonesia (Pusat)",
        "jenis_rekanan": "laboratorium_kalibrasi",
        "alamat": "Kawasan Industri Pulogadung Blok B No. 18, Jakarta Timur",
        "telepon": "021-4601234",
        "email": "cs@precision-indo.co.id",
        "pic_nama": "Hendra Setiawan, S.T.",
        "pic_kontak": "081234567801",
        "nomor_npwp": "01.234.567.8-001.000",
        "is_active": true,
        "urutan": 1,
        "created_at": "2026-09-24T18:00:00.000000Z",
        "updated_at": "2026-09-24T18:15:00.000000Z"
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
    "message": "Master vendor tidak ditemukan."
}
```
- **422 Unprocessable Content**
```json
{
    "status": "error",
    "message": "Validasi gagal.",
    "errors": {
        "email": [
            "Format email tidak valid."
        ]
    }
}
```

---

## DELETE /api/sinapra/master/vendor/{id}

Deskripsi: Menghapus data vendor / rekanan dengan mekanisme soft-delete.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Master vendor berhasil dihapus",
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
    "message": "Master vendor tidak ditemukan."
}
```

---

## Catatan
- Penghapusan vendor menggunakan mekanisme soft-delete (`deleted_at`), sehingga jejak audit transaksi kalibrasi dan pengadaan masa lalu tetap terpelihara utuh.
- Data kredensial dan password tidak dikembalikan dalam response API.

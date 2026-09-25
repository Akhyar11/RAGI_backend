# MasterKategoriBhpController

> **Modul**: SINAPRA (Sarana, Prasarana, & Aset)  
> **Base URL**: `/api/sinapra/master/kategori-bhp`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-09-24  
> **Diperbarui**: 2026-09-24  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/sinapra/master/kategori-bhp` | Listing master kategori bahan habis pakai (BHP) lab dengan pagination & filter | ✅ |
| POST | `/api/sinapra/master/kategori-bhp` | Tambah master kategori BHP baru | ✅ |
| GET | `/api/sinapra/master/kategori-bhp/{id}` | Detail master kategori BHP | ✅ |
| PUT | `/api/sinapra/master/kategori-bhp/{id}` | Update data master kategori BHP | ✅ |
| DELETE | `/api/sinapra/master/kategori-bhp/{id}` | Hapus master kategori BHP | ✅ |

---

## Headers Standar
- `Authorization: Bearer {token}`
- `Accept: application/json`
- `Content-Type: application/json`

---

## GET /api/sinapra/master/kategori-bhp

Deskripsi: Mengambil daftar master kategori bahan habis pakai (BHP) laboratorium.

### Query Parameters
- `search` (string, optional) - Filter pencarian kode atau nama kategori BHP.
- `is_active` (boolean/integer: `0`, `1`, optional) - Filter status aktif kategori.
- `sort_by` (string, default: `urutan`) - Whitelist: `created_at`, `kode`, `nama`, `urutan`.
- `sort_order` (enum: `asc`, `desc`, default: `asc`) - Urutan data.
- `per_page` (integer, default: 15, max: 100) - Jumlah data per halaman.
- `page` (integer, default: 1) - Halaman data yang diambil.
- `all` (boolean, optional) - Ambil seluruh data tanpa paginasi (untuk dropdown).

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Daftar master kategori BHP berhasil diambil",
    "data": [
        {
            "id": 1,
            "kode": "ELEKTRONIK",
            "nama": "Komponen Elektronik & Robotika",
            "deskripsi": "Sensor, mikrokontroler Arduino/ESP32, resistor, IC, dan modul rangkaian lab IoT",
            "is_active": true,
            "urutan": 1,
            "lab_bhp_count": 12,
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

## POST /api/sinapra/master/kategori-bhp

Deskripsi: Menambahkan master kategori bahan habis pakai lab baru.

### Request Body
```json
{
    "kode": "KIMIA_ORGANIK",
    "nama": "Pelarut & Kimia Organik",
    "deskripsi": "Etanol, metanol, aseton, kloroform dan pelarut organik laboratorium kimia dasar",
    "is_active": true,
    "urutan": 9
}
```

### Response Sukses (201 Created)
```json
{
    "status": "success",
    "message": "Master kategori BHP berhasil ditambahkan",
    "data": {
        "id": 9,
        "kode": "KIMIA_ORGANIK",
        "nama": "Pelarut & Kimia Organik",
        "deskripsi": "Etanol, metanol, aseton, kloroform dan pelarut organik laboratorium kimia dasar",
        "is_active": true,
        "urutan": 9,
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
            "Kode kategori BHP sudah digunakan."
        ]
    }
}
```

---

## GET /api/sinapra/master/kategori-bhp/{id}

Deskripsi: Mengambil detail satu master kategori BHP beserta jumlah stok terdaftar.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Detail master kategori BHP berhasil diambil",
    "data": {
        "id": 1,
        "kode": "ELEKTRONIK",
        "nama": "Komponen Elektronik & Robotika",
        "deskripsi": "Sensor, mikrokontroler Arduino/ESP32, resistor, IC, dan modul rangkaian lab IoT",
        "is_active": true,
        "urutan": 1,
        "lab_bhp_count": 12,
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
    "message": "Master kategori BHP tidak ditemukan."
}
```

---

## PUT /api/sinapra/master/kategori-bhp/{id}

Deskripsi: Memperbarui data master kategori BHP.

### Request Body
```json
{
    "kode": "ELEKTRONIK",
    "nama": "Komponen Elektronik, IoT & Robotika",
    "deskripsi": "Sensor, mikrokontroler, resistor, IC, dan modul praktikum IoT",
    "is_active": true,
    "urutan": 1
}
```

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Master kategori BHP berhasil diperbarui",
    "data": {
        "id": 1,
        "kode": "ELEKTRONIK",
        "nama": "Komponen Elektronik, IoT & Robotika",
        "deskripsi": "Sensor, mikrokontroler, resistor, IC, dan modul praktikum IoT",
        "is_active": true,
        "urutan": 1,
        "lab_bhp_count": 12,
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
    "message": "Master kategori BHP tidak ditemukan."
}
```
- **422 Unprocessable Content**
```json
{
    "status": "error",
    "message": "Validasi gagal.",
    "errors": {
        "nama": [
            "Nama kategori BHP wajib diisi."
        ]
    }
}
```

---

## DELETE /api/sinapra/master/kategori-bhp/{id}

Deskripsi: Menghapus master kategori BHP secara permanen jika tidak sedang digunakan oleh data barang BHP.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Master kategori BHP berhasil dihapus",
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
    "message": "Master kategori BHP tidak ditemukan."
}
```
- **422 Unprocessable Content**
```json
{
    "status": "error",
    "message": "Kategori BHP tidak dapat dihapus karena masih digunakan oleh data stok BHP."
}
```

---

## Catatan
- Penghapusan master kategori BHP adalah hard-delete permanen dari tabel `sinapra_master_kategori_bhp`. Penghapusan dicegah secara otomatis bila masih memiliki data stok BHP terkait (`lab_bhp_count > 0`).
- Data kredensial dan password tidak dikembalikan dalam response API.

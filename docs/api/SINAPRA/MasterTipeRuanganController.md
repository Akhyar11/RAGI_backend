# MasterTipeRuanganController

> **Modul**: SINAPRA (Sarana, Prasarana, & Aset)  
> **Base URL**: `/api/sinapra/master/tipe-ruangan`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-09-24  
> **Diperbarui**: 2026-09-24  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/sinapra/master/tipe-ruangan` | Listing master tipe ruangan dengan pagination & filter | ✅ |
| POST | `/api/sinapra/master/tipe-ruangan` | Tambah master tipe ruangan baru | ✅ |
| GET | `/api/sinapra/master/tipe-ruangan/{id}` | Detail master tipe ruangan | ✅ |
| PUT | `/api/sinapra/master/tipe-ruangan/{id}` | Update data master tipe ruangan | ✅ |
| DELETE | `/api/sinapra/master/tipe-ruangan/{id}` | Hapus master tipe ruangan | ✅ |

---

## Headers Standar
- `Authorization: Bearer {token}`
- `Accept: application/json`
- `Content-Type: application/json`

---

## GET /api/sinapra/master/tipe-ruangan

Deskripsi: Mengambil daftar master tipe ruangan yang terdaftar dalam sistem.

### Query Parameters
- `search` (string, optional) - Filter pencarian kode atau nama tipe ruangan.
- `is_active` (boolean/integer: `0`, `1`, optional) - Filter status aktif tipe ruangan.
- `sort_by` (string, default: `created_at`) - Whitelist: `created_at`, `updated_at`, `kode`, `nama`, `urutan`.
- `sort_order` (enum: `asc`, `desc`, default: `desc`) - Urutan data.
- `per_page` (integer, default: 15, max: 100) - Jumlah data per halaman.
- `page` (integer, default: 1) - Halaman data yang diambil.
- `all` (boolean, optional) - Ambil seluruh data tanpa paginasi (untuk dropdown).

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Daftar master tipe ruangan berhasil diambil",
    "data": [
        {
            "id": 1,
            "kode": "KELAS",
            "nama": "Ruang Kelas Teori",
            "deskripsi": "Ruangan untuk kegiatan belajar mengajar tatap muka teori",
            "is_active": true,
            "urutan": 1,
            "ruangan_count": 5,
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

## POST /api/sinapra/master/tipe-ruangan

Deskripsi: Menambahkan master tipe ruangan baru.

### Request Body
```json
{
    "kode": "LAB_KOMP",
    "nama": "Laboratorium Komputer",
    "deskripsi": "Lab khusus praktikum komputasi dan pemrograman",
    "is_active": true,
    "urutan": 2
}
```

### Response Sukses (201 Created)
```json
{
    "status": "success",
    "message": "Master tipe ruangan berhasil ditambahkan",
    "data": {
        "id": 2,
        "kode": "LAB_KOMP",
        "nama": "Laboratorium Komputer",
        "deskripsi": "Lab khusus praktikum komputasi dan pemrograman",
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
            "Kode tipe ruangan sudah digunakan."
        ]
    }
}
```

---

## GET /api/sinapra/master/tipe-ruangan/{id}

Deskripsi: Mengambil detail satu master tipe ruangan berdasarkan ID.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Detail master tipe ruangan berhasil diambil",
    "data": {
        "id": 1,
        "kode": "KELAS",
        "nama": "Ruang Kelas Teori",
        "deskripsi": "Ruangan untuk kegiatan belajar mengajar tatap muka teori",
        "is_active": true,
        "urutan": 1,
        "ruangan_count": 5,
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
    "message": "Master tipe ruangan tidak ditemukan."
}
```

---

## PUT /api/sinapra/master/tipe-ruangan/{id}

Deskripsi: Memperbarui data master tipe ruangan.

### Request Body
```json
{
    "kode": "KELAS",
    "nama": "Ruang Kelas Teori Reguler",
    "deskripsi": "Ruangan untuk kegiatan perkuliahan teori reguler",
    "is_active": true,
    "urutan": 1
}
```

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Master tipe ruangan berhasil diperbarui",
    "data": {
        "id": 1,
        "kode": "KELAS",
        "nama": "Ruang Kelas Teori Reguler",
        "deskripsi": "Ruangan untuk kegiatan perkuliahan teori reguler",
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
    "message": "Master tipe ruangan tidak ditemukan."
}
```
- **422 Unprocessable Content**
```json
{
    "status": "error",
    "message": "Validasi gagal.",
    "errors": {
        "kode": [
            "Kode tipe ruangan sudah digunakan."
        ]
    }
}
```

---

## DELETE /api/sinapra/master/tipe-ruangan/{id}

Deskripsi: Menghapus data master tipe ruangan secara permanen jika tidak sedang digunakan oleh data ruangan aktif.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Master tipe ruangan berhasil dihapus",
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
    "message": "Master tipe ruangan tidak ditemukan."
}
```
- **422 Unprocessable Content**
```json
{
    "status": "error",
    "message": "Tipe ruangan tidak dapat dihapus karena masih digunakan oleh data ruangan."
}
```

---

## Catatan
- Penghapusan master tipe ruangan adalah hard-delete permanen dari tabel `sinapra_master_tipe_ruangan` (bukan soft-delete). Penghapusan dicegah secara otomatis bila masih memiliki data ruangan terkait (`ruangan_count > 0`).
- Data kredensial dan password tidak dikembalikan dalam response API.

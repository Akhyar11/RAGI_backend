# GedungRuanganController

> **Modul**: SINAPRA (Sarana, Prasarana, & Aset)  
> **Base URL**: `/api/sinapra`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-08-19  
> **Diperbarui**: 2026-09-23  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/sinapra/gedung` | Listing gedung kampus dengan pagination & filter | ✅ |
| POST | `/api/sinapra/gedung` | Tambah gedung baru | ✅ |
| GET | `/api/sinapra/gedung/{id}` | Detail gedung beserta daftar ruangan | ✅ |
| PUT | `/api/sinapra/gedung/{id}` | Update data gedung | ✅ |
| DELETE | `/api/sinapra/gedung/{id}` | Soft delete gedung | ✅ |
| GET | `/api/sinapra/ruangan` | Listing ruangan dengan filter gedung/tipe/status | ✅ |
| POST | `/api/sinapra/ruangan` | Tambah ruangan baru | ✅ |
| POST | `/api/sinapra/ruangan/check-ketersediaan` | Cek ketersediaan jam ruangan | ✅ |
| GET | `/api/sinapra/ruangan/{id}` | Detail ruangan beserta daftar aset | ✅ |
| PUT | `/api/sinapra/ruangan/{id}` | Update data ruangan | ✅ |
| DELETE | `/api/sinapra/ruangan/{id}` | Soft delete ruangan | ✅ |
| GET | `/api/sinapra/ruangan/{id}/laboran` | Daftar staf laboran pada ruangan | ✅ |
| POST | `/api/sinapra/ruangan/{id}/laboran` | Penugasan laboran ke ruangan | ✅ |
| DELETE | `/api/sinapra/ruangan/{id}/laboran/{userId}` | Hapus penugasan laboran dari ruangan | ✅ |

---

## Headers Standar
- `Authorization: Bearer {token}`
- `Accept: application/json`
- `Content-Type: application/json`

---

## GET /api/sinapra/gedung

Deskripsi: Mengambil daftar gedung yang terdaftar dalam sistem.

### Query Parameters
- `search` (string, optional) - Filter pencarian kode, nama, atau alamat.
- `status` (enum: aktif, renovasi, nonaktif, optional) - Filter status gedung.
- `sort_by` (string, default: `created_at`) - Whitelist: `created_at`, `updated_at`, `kode`, `nama`, `jumlah_lantai`.
- `sort_order` (enum: `asc`, `desc`, default: `desc`) - Urutan data.
- `per_page` (integer, default: 15, max: 100) - Jumlah data per halaman.
- `page` (integer, default: 1) - Halaman data yang diambil.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Daftar gedung berhasil diambil",
    "data": [
        {
            "id": 1,
            "kode": "GDG-A",
            "nama": "Gedung Rektorat Utama",
            "jumlah_lantai": 4,
            "alamat": "Jl. Kampus Utama No. 1",
            "tahun_bangun": 2018,
            "luas_m2": 2500.0,
            "status": "aktif",
            "ruangan_count": 12,
            "created_at": "2026-08-19T09:00:00.000000Z"
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
        "status": null,
        "sort_by": "created_at",
        "sort_order": "desc"
    }
}
```

---

## POST /api/sinapra/ruangan/check-ketersediaan

Deskripsi: Mengecek ketersediaan waktu/jam pemakaian ruangan untuk mencegah bentrok jadwal.

### Request Body
```json
{
    "ruangan_id": 1,
    "tanggal": "2026-08-25",
    "jam_mulai": "09:00",
    "jam_selesai": "11:00"
}
```

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Ruangan tersedia untuk dipinjam",
    "data": {
        "is_available": true
    }
}
```

---

## GET /api/sinapra/ruangan/{id}/laboran

Deskripsi: Mengambil daftar staf laboran yang ditugaskan mengelola ruangan laboratorium terkait dengan paginasi.

### Query Parameters
- `search` (string, optional) - Pencarian nama, username, atau email laboran.
- `sort_by` (string, default: `created_at`) - Whitelist: `created_at`, `name`.
- `sort_order` (enum: `asc`, `desc`, default: `desc`) - Urutan data.
- `per_page` (integer, default: 15, max: 100) - Jumlah data per halaman.
- `page` (integer, default: 1) - Halaman data yang diambil.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Daftar laboran ruangan berhasil diambil",
    "data": [
        {
            "id": 2,
            "name": "Bayu Pratama",
            "username": "bayu_laboran",
            "email": "bayu@kampus.ac.id",
            "pivot": {
                "ruangan_id": 1,
                "user_id": 2,
                "is_primary": true
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
        "sort_by": "created_at",
        "sort_order": "desc"
    }
}
```

---

## POST /api/sinapra/ruangan/{id}/laboran

Deskripsi: Menugaskan staf laboran ke suatu ruangan laboratorium.

### Request Body
```json
{
    "user_id": 2,
    "is_primary": true
}
```

### Response Sukses (201 Created)
```json
{
    "status": "success",
    "message": "Laboran berhasil ditugaskan ke ruangan",
    "data": {
        "id": 1,
        "ruangan_id": 1,
        "user_id": 2,
        "is_primary": true,
        "created_at": "2026-09-23T14:30:00.000000Z"
    }
}
```

---

## DELETE /api/sinapra/ruangan/{id}/laboran/{userId}

Deskripsi: Menghapus penugasan laboran dari ruangan.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Penugasan laboran berhasil dihapus",
    "data": null
}
```

---

## Response Error Standar

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
    "message": "Anda tidak memiliki kewenangan untuk mengakses sumber daya ini."
}
```

### 404 Not Found
```json
{
    "status": "error",
    "message": "Ruangan tidak ditemukan."
}
```

### 422 Unprocessable Entity
```json
{
    "status": "error",
    "message": "Validasi gagal.",
    "errors": {
        "user_id": [
            "Pengguna laboran yang dipilih tidak valid."
        ]
    }
}
```

---

## Catatan
- Penghapusan gedung dan ruangan menggunakan mekanisme soft-delete.
- Data kredensial dan password tidak dikembalikan dalam response API.

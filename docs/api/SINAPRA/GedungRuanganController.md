# GedungRuanganController

> **Modul**: SINAPRA (Sarana, Prasarana, & Aset)  
> **Base URL**: `/api/sinapra`  
> **Autentikasi**: Bearer Token (`auth:api` / Passport)  
> **Dibuat**: 2026-08-19  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth | Permission |
|---|---|---|---|---|
| GET | `/api/sinapra/gedung` | Listing gedung kampus dengan pagination & filter | ✅ | `sinapra.gedung.read` |
| POST | `/api/sinapra/gedung` | Tambah gedung baru | ✅ | `sinapra.gedung.create` |
| GET | `/api/sinapra/gedung/{id}` | Detail gedung beserta daftar ruangan | ✅ | `sinapra.gedung.read` |
| PUT | `/api/sinapra/gedung/{id}` | Update data gedung | ✅ | `sinapra.gedung.update` |
| DELETE | `/api/sinapra/gedung/{id}` | Soft delete gedung | ✅ | `sinapra.gedung.delete` |
| GET | `/api/sinapra/ruangan` | Listing ruangan dengan filter gedung/tipe/status | ✅ | `sinapra.ruangan.read` |
| POST | `/api/sinapra/ruangan` | Tambah ruangan baru | ✅ | `sinapra.ruangan.create` |
| POST | `/api/sinapra/ruangan/check-ketersediaan` | Cek ketersediaan jam ruangan | ✅ | `sinapra.ruangan.read` |
| GET | `/api/sinapra/ruangan/{id}` | Detail ruangan beserta daftar aset | ✅ | `sinapra.ruangan.read` |
| PUT | `/api/sinapra/ruangan/{id}` | Update data ruangan | ✅ | `sinapra.ruangan.update` |
| DELETE | `/api/sinapra/ruangan/{id}` | Soft delete ruangan | ✅ | `sinapra.ruangan.delete` |

---

## GET /api/sinapra/gedung

Deskripsi: Mengambil daftar gedung yang terdaftar dalam sistem.

### Headers
- `Authorization: Bearer {token}`
- `Accept: application/json`

### Query Parameters
- `search` (string, optional) - Filter pencarian kode, nama, atau alamat.
- `status` (enum: aktif, renovasi, nonaktif, optional) - Filter status gedung.
- `sort_by` (string, default: `created_at`) - Whitelist: `created_at`, `updated_at`, `kode`, `nama`, `jumlah_lantai`.
- `sort_order` (enum: `asc`, `desc`, default: `desc`).
- `per_page` (integer, default: 15, max: 100).

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

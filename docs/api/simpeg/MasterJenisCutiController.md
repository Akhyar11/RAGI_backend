# MasterJenisCutiController

> **Modul**: SIMPEG (Sistem Informasi Manajemen Kepegawaian)  
> **Base URL**: `/api/simpeg/master-jenis-cuti`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-09-14  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/simpeg/master-jenis-cuti` | Mengambil daftar jenis cuti/izin dengan pagination, pencarian, dan filter | ✅ User / Admin SIMPEG |
| POST | `/api/simpeg/master-jenis-cuti` | Menambahkan jenis izin/cuti baru | ✅ Admin SDM |
| GET | `/api/simpeg/master-jenis-cuti/{id}` | Mengambil rincian jenis izin/cuti | ✅ User / Admin SIMPEG |
| PUT | `/api/simpeg/master-jenis-cuti/{id}` | Memperbarui data jenis izin/cuti | ✅ Admin SDM |
| DELETE | `/api/simpeg/master-jenis-cuti/{id}` | Menghapus atau menonaktifkan jenis izin/cuti | ✅ Admin SDM |

---

## GET /api/simpeg/master-jenis-cuti

> Mengambil daftar master jenis izin & cuti pegawai (durasi ditetapkan & fleksibel).

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari nama, kode, atau keterangan |
| `tipe_durasi` | string | ❌ | — | Filter tipe durasi: `ditetapkan` atau `fleksibel` |
| `is_active` | boolean | ❌ | — | Filter status aktif (`1` / `0` atau `true` / `false`) |
| `all` | boolean | ❌ | `false` | Ambil semua data tanpa pagination (misal untuk dropdown) |
| `sort_by` | string | ❌ | `nama` | Whitelist: `id`, `nama`, `kode`, `tipe_durasi`, `durasi_hari`, `created_at` |
| `sort_order` | string | ❌ | `asc` | `asc` atau `desc` |
| `per_page` | integer | ❌ | `15` | Limit data per halaman |
| `page` | integer | ❌ | `1` | Nomor halaman |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Data retrieved successfully",
    "data": [
        {
            "id": 1,
            "nama": "Izin Menikah",
            "kode": "IZIN_MENIKAH",
            "tipe_durasi": "ditetapkan",
            "durasi_hari": 14,
            "satuan": "hari",
            "lampiran_wajib": false,
            "keterangan": "Izin melangsungkan pernikahan pegawai. Durasi 14 hari.",
            "is_active": true,
            "created_at": "2026-09-14T11:00:00.000000Z",
            "updated_at": "2026-09-14T11:00:00.000000Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1,
        "from": 1,
        "to": 1
    }
}
```

---

## POST /api/simpeg/master-jenis-cuti

> Menambahkan data master jenis cuti / izin baru.

### Request Body

```json
{
    "nama": "Izin Khitanan Anak",
    "kode": "IZIN_KHITAN",
    "tipe_durasi": "ditetapkan",
    "durasi_hari": 3,
    "satuan": "hari",
    "lampiran_wajib": false,
    "keterangan": "Izin khitanan anak kandung pegawai.",
    "is_active": true
}
```

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Master jenis cuti berhasil ditambahkan",
    "data": {
        "id": 7,
        "nama": "Izin Khitanan Anak",
        "kode": "IZIN_KHITAN",
        "tipe_durasi": "ditetapkan",
        "durasi_hari": 3,
        "satuan": "hari",
        "lampiran_wajib": false,
        "keterangan": "Izin khitanan anak kandung pegawai.",
        "is_active": true,
        "created_at": "2026-09-14T11:25:00.000000Z",
        "updated_at": "2026-09-14T11:25:00.000000Z"
    }
}
```

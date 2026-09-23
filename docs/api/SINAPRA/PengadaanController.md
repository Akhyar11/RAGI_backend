# PengadaanController

> **Modul**: SINAPRA (Sarana, Prasarana, & Aset)  
> **Base URL**: `/api/sinapra`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-08-19  
> **Diperbarui**: 2026-09-23  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/sinapra/pengadaan` | Listing pengajuan pengadaan barang | ✅ |
| POST | `/api/sinapra/pengadaan` | Buat usulan pengadaan barang baru beserta rincian detail | ✅ |
| GET | `/api/sinapra/pengadaan/{id}` | Detail usulan pengadaan & rincian barang | ✅ |
| PATCH | `/api/sinapra/pengadaan/{id}/status` | Update status persetujuan pengadaan | ✅ |
| DELETE | `/api/sinapra/pengadaan/{id}` | Soft delete usulan pengadaan | ✅ |

---

## Headers Standar
- `Authorization: Bearer {token}`
- `Accept: application/json`
- `Content-Type: application/json`

---

## GET /api/sinapra/pengadaan

Deskripsi: Mengambil daftar pengajuan pengadaan barang dari seluruh unit kerja/laboratorium kampus.

### Query Parameters
- `search` (string, optional) - Pencarian judul atau alasan kebutuhan pengadaan.
- `status` (enum: `draft`, `diajukan`, `disetujui`, `ditolak`, `proses_pengadaan`, `selesai`, optional) - Filter status usulan.
- `unit_kerja_id` (integer, optional) - Filter unit kerja pemohon.
- `sort_by` (string, default: `created_at`) - Whitelist: `created_at`, `tanggal_pengajuan`, `estimasi_anggaran`, `status`.
- `sort_order` (enum: `asc`, `desc`, default: `desc`) - Urutan data.
- `per_page` (integer, default: 15, max: 100) - Jumlah data per halaman.
- `page` (integer, default: 1) - Halaman data yang diambil.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Daftar pengajuan pengadaan berhasil diambil",
    "data": [
        {
            "id": 1,
            "unit_kerja_id": 3,
            "diajukan_oleh": 5,
            "judul": "Pengadaan Komputer Desktop High-End Lab AI & Data",
            "alasan_kebutuhan": "Peningkatan kapasitas praktikum machine learning semester ganjil",
            "tanggal_pengajuan": "2026-09-23",
            "estimasi_anggaran": 150000000.0,
            "status": "diajukan",
            "disetujui_oleh": null,
            "details_count": 1,
            "created_at": "2026-09-23T08:00:00.000000Z",
            "unit_kerja": {
                "id": 3,
                "nama": "Program Studi TRPL"
            },
            "pengaju": {
                "id": 5,
                "username": "laboran_trpl",
                "name": "Laboran TRPL"
            },
            "approver": null
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
        "unit_kerja_id": null,
        "sort_by": "created_at",
        "sort_order": "desc"
    }
}
```

---

## POST /api/sinapra/pengadaan

Deskripsi: Membuat pengajuan usulan pengadaan barang baru beserta rincian detail barang (spesifikasi, estimasi harga satuan, dan kuantitas). Estimasi total anggaran otomatis dihitung di backend.

### Request Body
```json
{
    "unit_kerja_id": 3,
    "judul": "Pengadaan Komputer Desktop High-End Lab AI & Data",
    "alasan_kebutuhan": "Peningkatan kapasitas praktikum machine learning semester ganjil",
    "tanggal_pengajuan": "2026-09-23",
    "details": [
        {
            "kategori_aset_id": 1,
            "nama_barang": "PC Rakitan Core i9 RTX 4080",
            "spesifikasi": "RAM 64GB DDR5, SSD 2TB NVMe",
            "jumlah": 5,
            "satuan": "Unit",
            "harga_satuan_estimasi": 30000000
        }
    ]
}
```

### Response Sukses (201 Created)
```json
{
    "status": "success",
    "message": "Pengajuan pengadaan berhasil dibuat",
    "data": {
        "id": 1,
        "unit_kerja_id": 3,
        "diajukan_oleh": 5,
        "judul": "Pengadaan Komputer Desktop High-End Lab AI & Data",
        "alasan_kebutuhan": "Peningkatan kapasitas praktikum machine learning semester ganjil",
        "tanggal_pengajuan": "2026-09-23",
        "estimasi_anggaran": 150000000.0,
        "status": "draft",
        "details": [
            {
                "id": 1,
                "pengajuan_id": 1,
                "kategori_aset_id": 1,
                "nama_barang": "PC Rakitan Core i9 RTX 4080",
                "spesifikasi": "RAM 64GB DDR5, SSD 2TB NVMe",
                "jumlah": 5,
                "satuan": "Unit",
                "harga_satuan_estimasi": 30000000.0,
                "total_estimasi": 150000000.0
            }
        ]
    }
}
```

---

## GET /api/sinapra/pengadaan/{id}

Deskripsi: Melihat detail pengajuan pengadaan beserta rincian item barang dan approver.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Detail pengajuan pengadaan berhasil diambil",
    "data": {
        "id": 1,
        "judul": "Pengadaan Komputer Desktop High-End Lab AI & Data",
        "status": "diajukan",
        "estimasi_anggaran": 150000000.0,
        "details": [
            {
                "id": 1,
                "nama_barang": "PC Rakitan Core i9 RTX 4080",
                "jumlah": 5,
                "satuan": "Unit",
                "harga_satuan_estimasi": 30000000.0,
                "total_estimasi": 150000000.0
            }
        ]
    }
}
```

---

## PATCH /api/sinapra/pengadaan/{id}/status

Deskripsi: Mengubah status persetujuan usulan pengadaan (`diajukan`, `disetujui`, `ditolak`, `proses_pengadaan`, `selesai`) oleh Admin SINAPRA / Pimpinan.

### Request Body
```json
{
    "status": "disetujui"
}
```

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Status pengajuan pengadaan berhasil diperbarui",
    "data": {
        "id": 1,
        "status": "disetujui",
        "disetujui_oleh": 1
    }
}
```

---

## DELETE /api/sinapra/pengadaan/{id}

Deskripsi: Menghapus (soft delete) draft usulan pengadaan.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Pengajuan pengadaan berhasil dihapus",
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
    "message": "Pengajuan pengadaan tidak ditemukan."
}
```

### 422 Unprocessable Entity
```json
{
    "status": "error",
    "message": "Validasi gagal.",
    "errors": {
        "judul": [
            "Judul pengadaan wajib diisi."
        ],
        "unit_kerja_id": [
            "Unit kerja pemohon wajib dipilih."
        ]
    }
}
```

---

## Catatan
- Penghapusan draft usulan pengadaan menggunakan mekanisme soft-delete.
- Data kredensial dan password pengguna tidak dikembalikan dalam response API.
- Pemohon hanya dapat mengedit dan menghapus pengajuan ketika masih berstatus `draft`.

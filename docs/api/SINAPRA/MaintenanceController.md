# MaintenanceController

> **Modul**: SINAPRA (Sarana, Prasarana, & Aset)  
> **Base URL**: `/api/sinapra`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-08-19  
> **Diperbarui**: 2026-09-23  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/sinapra/maintenance` | Listing log maintenance/perawatan | ✅ |
| POST | `/api/sinapra/maintenance` | Buat tiket perbaikan/perawatan baru | ✅ |
| GET | `/api/sinapra/maintenance/{id}` | Detail tiket perbaikan | ✅ |
| PUT | `/api/sinapra/maintenance/{id}` | Update status perbaikan & biaya | ✅ |
| DELETE | `/api/sinapra/maintenance/{id}` | Soft delete tiket perbaikan | ✅ |

---

## Headers Standar
- `Authorization: Bearer {token}`
- `Accept: application/json`
- `Content-Type: application/json`

---

## GET /api/sinapra/maintenance

Deskripsi: Mengambil daftar tiket perawatan/perbaikan barang inventaris atau ruangan. Otomatis difilter hanya aset/ruangan di laboratorium binaan laboran saat diakses oleh akun `admin_laboratorium`.

### Query Parameters
- `search` (string, optional) - Pencarian judul atau deskripsi kerusakan.
- `status` (enum: `dilaporkan`, `sedang_diperbaiki`, `selesai`, `batal`, optional) - Filter status perbaikan.
- `prioritas` (enum: `rendah`, `sedang`, `tinggi`, `darurat`, optional) - Filter tingkat urgensi kerusakan.
- `aset_id` (integer, optional) - Filter ID aset.
- `ruangan_id` (integer, optional) - Filter ID ruangan.
- `sort_by` (string, default: `created_at`) - Whitelist: `created_at`, `tanggal_lapor`, `prioritas`, `status`, `biaya`.
- `sort_order` (enum: `asc`, `desc`, default: `desc`) - Urutan data.
- `per_page` (integer, default: 15, max: 100) - Jumlah data per halaman.
- `page` (integer, default: 1) - Halaman data yang diambil.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Daftar maintenance log berhasil diambil",
    "data": [
        {
            "id": 1,
            "aset_id": 5,
            "ruangan_id": null,
            "judul": "Kerusakan Lensa Objektif Mikroskop Lab RPL",
            "deskripsi_kerusakan": "Lensa 100x buram dan sekrup pengarah kasar macet",
            "prioritas": "tinggi",
            "tanggal_lapor": "2026-09-23",
            "tanggal_mulai": "2026-09-24",
            "tanggal_selesai": null,
            "biaya": 250000.0,
            "hasil_perbaikan": null,
            "status": "sedang_diperbaiki",
            "teknisi_id": 8,
            "created_at": "2026-09-23T08:00:00.000000Z",
            "aset": {
                "id": 5,
                "kode_aset": "AST-LAB-001",
                "nama": "Mikroskop Binokuler Olympus"
            },
            "ruangan": null,
            "teknisi": {
                "id": 8,
                "username": "teknisi_sarpras",
                "name": "Budi Teknisi"
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
        "status": null,
        "prioritas": null,
        "aset_id": null,
        "ruangan_id": null,
        "sort_by": "created_at",
        "sort_order": "desc"
    }
}
```

---

## POST /api/sinapra/maintenance

Deskripsi: Membuat laporan tiket perawatan/perbaikan baru untuk aset atau ruangan. Otomatis mengubah status aset/ruangan terkait menjadi `maintenance`.

### Request Body
```json
{
    "aset_id": 5,
    "ruangan_id": null,
    "judul": "Kerusakan Lensa Objektif Mikroskop Lab RPL",
    "deskripsi_kerusakan": "Lensa 100x buram dan sekrup pengarah kasar macet",
    "prioritas": "tinggi",
    "tanggal_lapor": "2026-09-23",
    "biaya": 250000
}
```

### Response Sukses (201 Created)
```json
{
    "status": "success",
    "message": "Tiket maintenance berhasil dibuat",
    "data": {
        "id": 1,
        "aset_id": 5,
        "ruangan_id": null,
        "judul": "Kerusakan Lensa Objektif Mikroskop Lab RPL",
        "deskripsi_kerusakan": "Lensa 100x buram dan sekrup pengarah kasar macet",
        "prioritas": "tinggi",
        "status": "dilaporkan",
        "biaya": 250000.0,
        "aset": {
            "id": 5,
            "kode_aset": "AST-LAB-001",
            "nama": "Mikroskop Binokuler Olympus"
        },
        "ruangan": null,
        "teknisi": null
    }
}
```

---

## GET /api/sinapra/maintenance/{id}

Deskripsi: Melihat rincian detail tiket maintenance beserta aset, ruangan, dan teknisi penanggung jawab.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Detail tiket maintenance berhasil diambil",
    "data": {
        "id": 1,
        "aset_id": 5,
        "ruangan_id": null,
        "judul": "Kerusakan Lensa Objektif Mikroskop Lab RPL",
        "deskripsi_kerusakan": "Lensa 100x buram dan sekrup pengarah kasar macet",
        "prioritas": "tinggi",
        "status": "sedang_diperbaiki",
        "biaya": 250000.0,
        "aset": {
            "id": 5,
            "kode_aset": "AST-LAB-001",
            "nama": "Mikroskop Binokuler Olympus"
        }
    }
}
```

---

## PUT /api/sinapra/maintenance/{id}

Deskripsi: Memperbarui progres penanganan tiket perbaikan, tanggal mulai/selesai, hasil perbaikan, dan biaya. Jika status diubah menjadi `selesai`, status aset dan ruangan terkait otomatis dipulihkan menjadi `tersedia` / `aktif`.

### Request Body
```json
{
    "status": "selesai",
    "tanggal_selesai": "2026-09-24",
    "biaya": 250000,
    "hasil_perbaikan": "Lensa sudah diganti baru dan sekrup pengarah diminyaki, alat berfungsi normal."
}
```

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Tiket maintenance berhasil diperbarui",
    "data": {
        "id": 1,
        "status": "selesai",
        "tanggal_selesai": "2026-09-24",
        "biaya": 250000.0,
        "hasil_perbaikan": "Lensa sudah diganti baru dan sekrup pengarah diminyaki, alat berfungsi normal."
    }
}
```

---

## DELETE /api/sinapra/maintenance/{id}

Deskripsi: Menghapus (soft delete) tiket maintenance log.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Tiket maintenance berhasil dihapus",
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
    "message": "Tiket maintenance tidak ditemukan."
}
```

### 422 Unprocessable Entity
```json
{
    "status": "error",
    "message": "Validasi gagal.",
    "errors": {
        "judul": [
            "Judul laporan maintenance wajib diisi."
        ],
        "prioritas": [
            "Prioritas yang dipilih tidak valid."
        ]
    }
}
```

---

## Catatan
- Operasi penghapusan data tiket maintenance menggunakan mekanisme soft-delete.
- Data kredensial dan password pengguna tidak dikembalikan dalam response API.
- Akun berstatus `admin_laboratorium` dapat memantau dan membuat laporan perbaikan untuk fasilitas laboratorium binaannya.

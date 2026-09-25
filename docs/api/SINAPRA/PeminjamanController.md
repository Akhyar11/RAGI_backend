# PeminjamanController

> **Modul**: SINAPRA (Sarana, Prasarana, & Aset)  
> **Base URL**: `/api/sinapra`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-08-19  
> **Diperbarui**: 2026-09-23  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/sinapra/peminjaman-ruangan` | Listing permohonan peminjaman ruangan | ✅ |
| POST | `/api/sinapra/peminjaman-ruangan` | Permohonan peminjaman ruangan baru | ✅ |
| GET | `/api/sinapra/peminjaman-ruangan/{id}` | Detail peminjaman ruangan | ✅ |
| POST | `/api/sinapra/peminjaman-ruangan/{id}/approve-laboran` | Verifikasi/Persetujuan tahap Laboran ruangan | ✅ |
| POST | `/api/sinapra/peminjaman-ruangan/{id}/approve` | Persetujuan/Penolakan akhir peminjaman ruangan (Admin) | ✅ |
| GET | `/api/sinapra/peminjaman-aset` | Listing permohonan peminjaman barang/aset | ✅ |
| POST | `/api/sinapra/peminjaman-aset` | Permohonan peminjaman barang/aset baru | ✅ |
| GET | `/api/sinapra/peminjaman-aset/{id}` | Detail peminjaman barang/aset | ✅ |
| POST | `/api/sinapra/peminjaman-aset/{id}/approve-laboran` | Verifikasi/Persetujuan tahap Laboran aset | ✅ |
| POST | `/api/sinapra/peminjaman-aset/{id}/approve` | Persetujuan/Penolakan akhir peminjaman barang (Admin) | ✅ |
| POST | `/api/sinapra/peminjaman-aset/{id}/kembalikan` | Pengembalian barang/aset | ✅ |

---

## Headers Standar
- `Authorization: Bearer {token}`
- `Accept: application/json`
- `Content-Type: application/json`

---

## GET /api/sinapra/peminjaman-ruangan

Deskripsi: Mengambil daftar permohonan peminjaman ruangan dengan filter dan pagination. Otomatis dibatasi untuk ruangan lab binaan jika diakses oleh akun laboran.

### Query Parameters
- `search` (string, optional) - Pencarian keperluan atau nama/email peminjam.
- `status` (string, optional) - Filter status: `pending_laboran`, `pending_admin_sinapra`, `disetujui`, `ditolak_laboran`, `ditolak_admin_sinapra`, `selesai`, `batal`.
- `ruangan_id` (integer, optional) - ID Ruangan.
- `tanggal` (date YYYY-MM-DD, optional) - Filter tanggal peminjaman.
- `sort_by` (string, default: `created_at`) - Whitelist: `created_at`, `tanggal`, `status`.
- `sort_order` (enum: `asc`, `desc`, default: `desc`) - Urutan data.
- `per_page` (integer, default: 15, max: 100) - Jumlah data per halaman.
- `page` (integer, default: 1) - Halaman data yang diambil.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Daftar peminjaman ruangan berhasil diambil",
    "data": [
        {
            "id": 1,
            "ruangan_id": 2,
            "user_id": 10,
            "keperluan": "Praktikum Tambahan Jaringan Komputer",
            "tanggal": "2026-09-25",
            "jam_mulai": "08:00:00",
            "jam_selesai": "11:00:00",
            "status": "pending_laboran",
            "laboran_approved_by": null,
            "laboran_approved_at": null,
            "catatan_laboran": null,
            "disetujui_oleh": null,
            "admin_approved_at": null,
            "catatan_penolakan": null,
            "created_at": "2026-09-23T08:00:00.000000Z",
            "ruangan": {
                "id": 2,
                "kode": "LAB-01",
                "nama": "Laboratorium Rekayasa Perangkat Lunak",
                "tipe": "lab",
                "gedung": {
                    "id": 1,
                    "nama": "Gedung Teori & Lab"
                }
            },
            "user": {
                "id": 10,
                "username": "mahasiswa1",
                "email": "mhs1@campus.ac.id"
            },
            "approver": null,
            "laboran_approver": null
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
        "ruangan_id": null,
        "tanggal": null,
        "sort_by": "created_at",
        "sort_order": "desc"
    }
}
```

---

## POST /api/sinapra/peminjaman-ruangan

Deskripsi: Mengajukan peminjaman ruangan. Jika ruangan bertipe `lab`, status awal otomatis menjadi `pending_laboran`. Jika non-lab, langsung menjadi `pending_admin_sinapra`. Terdapat validasi ketersediaan jam ruangan dan integrasi pencegahan bentrok dengan jadwal kelas perkuliahan aktif SIAKAD.

### Request Body
```json
{
    "ruangan_id": 2,
    "keperluan": "Praktikum Tambahan Jaringan Komputer",
    "tanggal": "2026-09-25",
    "jam_mulai": "08:00",
    "jam_selesai": "11:00"
}
```

### Response Sukses (201 Created)
```json
{
    "status": "success",
    "message": "Pengajuan peminjaman ruangan berhasil dikirim",
    "data": {
        "id": 1,
        "ruangan_id": 2,
        "user_id": 10,
        "keperluan": "Praktikum Tambahan Jaringan Komputer",
        "tanggal": "2026-09-25",
        "jam_mulai": "08:00",
        "jam_selesai": "11:00",
        "status": "pending_laboran",
        "ruangan": {
            "id": 2,
            "kode": "LAB-01",
            "nama": "Laboratorium Rekayasa Perangkat Lunak"
        }
    }
}
```

---

## POST /api/sinapra/peminjaman-ruangan/{id}/approve-laboran

Deskripsi: Verifikasi tahap Laboran yang bertanggung jawab atas laboratorium bersangkutan. Jika disetujui (`is_approved: true`), status berlanjut menjadi `pending_admin_sinapra`. Jika ditolak, status berubah menjadi `ditolak_laboran`.

### Request Body
```json
{
    "is_approved": true,
    "catatan_laboran": "Jadwal praktikum aman, alat lab sudah disiapkan."
}
```

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Persetujuan laboran berhasil diverifikasi",
    "data": {
        "id": 1,
        "status": "pending_admin_sinapra",
        "laboran_approved_by": 5,
        "laboran_approved_at": "2026-09-23T08:15:00.000000Z",
        "catatan_laboran": "Jadwal praktikum aman, alat lab sudah disiapkan."
    }
}
```

---

## POST /api/sinapra/peminjaman-ruangan/{id}/approve

Deskripsi: Persetujuan atau penolakan tahap akhir oleh Admin SINAPRA. Jika disetujui (`is_approved: true`), status menjadi `disetujui`. Jika ditolak, status menjadi `ditolak_admin_sinapra` dan wajib menyertakan `catatan_penolakan`.

### Request Body
```json
{
    "is_approved": true,
    "catatan_penolakan": null
}
```

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Peminjaman ruangan berhasil disetujui",
    "data": {
        "id": 1,
        "status": "disetujui",
        "disetujui_oleh": 1,
        "admin_approved_at": "2026-09-23T08:30:00.000000Z",
        "catatan_penolakan": null
    }
}
```

---

## GET /api/sinapra/peminjaman-aset

Deskripsi: Mengambil daftar permohonan peminjaman aset/barang dengan pagination dan filter. Otomatis difilter hanya aset yang berada di lab binaan laboran saat diakses oleh laboran.

### Query Parameters
- `search` (string, optional) - Pencarian nama aset, kode aset, atau keperluan.
- `status` (string, optional) - Filter status.
- `aset_id` (integer, optional) - Filter ID aset.
- `sort_by` (string, default: `created_at`) - Whitelist: `created_at`, `tanggal_pinjam`, `tanggal_kembali_rencana`, `status`.
- `sort_order` (enum: `asc`, `desc`, default: `desc`) - Urutan data.
- `per_page` (integer, default: 15, max: 100) - Jumlah data per halaman.
- `page` (integer, default: 1) - Halaman data yang diambil.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Daftar peminjaman aset berhasil diambil",
    "data": [
        {
            "id": 1,
            "aset_id": 5,
            "user_id": 10,
            "keperluan": "Peminjaman Mikroskop untuk Uji Praktikum",
            "tanggal_pinjam": "2026-09-25",
            "tanggal_kembali_rencana": "2026-09-26",
            "status": "pending_laboran",
            "aset": {
                "id": 5,
                "kode_aset": "AST-LAB-001",
                "nama": "Mikroskop Binokuler Olympus",
                "is_borrowable": true,
                "is_lab_asset": true
            },
            "user": {
                "id": 10,
                "username": "mahasiswa1"
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
        "aset_id": null,
        "sort_by": "created_at",
        "sort_order": "desc"
    }
}
```

---

## POST /api/sinapra/peminjaman-aset

Deskripsi: Mengajukan peminjaman aset/barang. Aset harus memiliki `is_borrowable = true` dan `status = 'tersedia'`. Jika aset merupakan aset laboratorium (`is_lab_asset: true` atau berada di ruangan bertipe lab), status awal menjadi `pending_laboran`. Jika aset kampus umum, status menjadi `pending_admin_sinapra`.

### Request Body
```json
{
    "aset_id": 5,
    "keperluan": "Peminjaman Mikroskop untuk Uji Praktikum",
    "tanggal_pinjam": "2026-09-25",
    "tanggal_kembali_rencana": "2026-09-26"
}
```

### Response Sukses (201 Created)
```json
{
    "status": "success",
    "message": "Pengajuan peminjaman aset berhasil dikirim",
    "data": {
        "id": 1,
        "aset_id": 5,
        "user_id": 10,
        "keperluan": "Peminjaman Mikroskop untuk Uji Praktikum",
        "tanggal_pinjam": "2026-09-25",
        "tanggal_kembali_rencana": "2026-09-26",
        "status": "pending_laboran"
    }
}
```

---

## POST /api/sinapra/peminjaman-aset/{id}/approve-laboran

Deskripsi: Persetujuan/penolakan tahap Laboran atas peminjaman aset laboratorium.

### Request Body
```json
{
    "is_approved": true,
    "catatan_laboran": "Kondisi mikroskop prima, lensa sudah dibersihkan."
}
```

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Persetujuan laboran berhasil diverifikasi",
    "data": {
        "id": 1,
        "status": "pending_admin_sinapra",
        "laboran_approved_by": 5,
        "laboran_approved_at": "2026-09-23T08:20:00.000000Z",
        "catatan_laboran": "Kondisi mikroskop prima, lensa sudah dibersihkan."
    }
}
```

---

## POST /api/sinapra/peminjaman-aset/{id}/approve

Deskripsi: Persetujuan/penolakan tahap akhir oleh Admin SINAPRA atas peminjaman aset/barang. Jika disetujui, status peminjaman menjadi `disetujui` dan status aset diperbarui menjadi `dipinjam`.

### Request Body
```json
{
    "is_approved": true,
    "catatan_penolakan": null
}
```

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Peminjaman aset berhasil disetujui",
    "data": {
        "id": 1,
        "status": "disetujui",
        "disetujui_oleh": 1,
        "admin_approved_at": "2026-09-23T08:35:00.000000Z",
        "catatan_penolakan": null
    }
}
```

---

## POST /api/sinapra/peminjaman-aset/{id}/kembalikan

Deskripsi: Memproses pengembalian barang/aset pinjaman serta memperbarui kondisi fisik dan status ketersediaan barang kembali ke `tersedia`.

### Request Body
```json
{
    "kondisi_kembali": "baik"
}
```

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Pengembalian aset berhasil diproses",
    "data": {
        "id": 1,
        "aset_id": 5,
        "user_id": 10,
        "tanggal_pinjam": "2026-09-25",
        "tanggal_kembali_aktual": "2026-09-26",
        "kondisi_kembali": "baik",
        "status": "selesai"
    }
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
    "message": "Anda tidak memiliki kewenangan verifikasi laboran untuk ruangan laboratorium ini."
}
```

### 404 Not Found
```json
{
    "status": "error",
    "message": "Peminjaman tidak ditemukan."
}
```

### 422 Unprocessable Entity
```json
{
    "status": "error",
    "message": "Validasi gagal.",
    "errors": {
        "ruangan_id": [
            "Ruangan sedang tidak tersedia pada jam tersebut."
        ]
    }
}
```

---

## Catatan
- Operasi penghapusan data peminjaman menggunakan mekanisme soft-delete.
- Data kredensial dan password pengguna tidak dikembalikan dalam response API.
- Akun dengan role `admin_laboratorium` hanya dapat melihat dan memverifikasi data yang terkait dengan laboratorium di mana mereka terdaftar di `sinapra_laboran_ruangan`.

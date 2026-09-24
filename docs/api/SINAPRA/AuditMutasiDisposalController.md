# AuditMutasiDisposalController

> **Modul**: SINAPRA (Sarana, Prasarana, & Aset)  
> **Base URL**: `/api/sinapra`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-09-24  
> **Diperbarui**: 2026-09-24  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/sinapra/stock-opname` | Listing sesi audit stock opname fisik aset | ✅ |
| POST | `/api/sinapra/stock-opname` | Buka sesi baru stock opname fisik & auto-snapshot aset | ✅ |
| GET | `/api/sinapra/stock-opname/{id}` | Detail sesi stock opname beserta daftar checklist fisik aset | ✅ |
| PUT | `/api/sinapra/stock-opname/{id}/items/{itemId}` | Update checklist keberadaan & kondisi fisik item aset | ✅ |
| POST | `/api/sinapra/stock-opname/{id}/finish` | Finalisasi / tutup sesi stock opname & sinkronisasi kondisi aset | ✅ |
| GET | `/api/sinapra/mutasi-aset` | Listing permohonan mutasi aset antar-ruang/lab | ✅ |
| POST | `/api/sinapra/mutasi-aset` | Ajukan permohonan pemindahan aset antar-ruang | ✅ |
| POST | `/api/sinapra/mutasi-aset/{id}/approve` | Persetujuan/penolakan serah terima mutasi aset oleh penerima/admin | ✅ |
| GET | `/api/sinapra/disposal-aset` | Listing usulan pemutihan, lelang, & penghapusan aset | ✅ |
| POST | `/api/sinapra/disposal-aset` | Catat usulan BAP pemutihan/penghapusan aset | ✅ |
| POST | `/api/sinapra/disposal-aset/{id}/approve` | Keputusan persetujuan BAP pemutihan aset (Admin Sarpras) | ✅ |

---

## Headers Standar
- `Authorization: Bearer {token}`
- `Accept: application/json`
- `Content-Type: application/json`

---

## 1. Stock Opname Fisik

### GET /api/sinapra/stock-opname
Mengambil daftar sesi stock opname aset. Bagi `admin_laboratorium`, data dibatasi otomatis pada ruangan lab yang ditugaskan.

#### Query Parameters
- `search` (string, optional) - Pencarian kode opname, catatan, atau ruangan.
- `ruangan_id` (integer, optional) - Filter ID ruangan.
- `status` (string, optional) - Filter status: `berlangsung`, `selesai`.
- `sort_by` (string, default: `created_at`) - `id`, `kode_opname`, `tanggal_mulai`, `tanggal_selesai`, `status`, `created_at`.
- `sort_dir` (enum: `asc`, `desc`, default: `desc`).
- `per_page` (integer, default: 15).

#### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Daftar audit stock opname berhasil diambil.",
    "data": [
        {
            "id": 1,
            "ruangan_id": 2,
            "kode_opname": "OPN-202609-0001",
            "tanggal_mulai": "2026-09-24",
            "tanggal_selesai": null,
            "petugas_user_id": 1,
            "status": "berlangsung",
            "catatan": "Pemeriksaan aset berkala semester ganjil",
            "ruangan": {
                "id": 2,
                "nama": "Lab Komputer 1",
                "kode": "LK-01"
            },
            "petugas": {
                "id": 1,
                "name": "Laboran Utama"
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
        "ruangan_id": null,
        "status": null,
        "sort_by": "created_at",
        "sort_dir": "desc"
    }
}
```

### POST /api/sinapra/stock-opname
Membuat sesi baru stock opname fisik. Sistem otomatis membuat snapshot seluruh aset di ruangan tersebut ke dalam checklist fisik dengan status default `sesuai`.

#### Body Request (JSON)
```json
{
    "ruangan_id": 2,
    "tanggal_mulai": "2026-09-24",
    "catatan": "Audit triwulan laboratorium"
}
```

### PUT /api/sinapra/stock-opname/{id}/items/{itemId}
Mengubah hasil verifikasi fisik sebuah item aset saat sesi audit berlangsung.

#### Body Request (JSON)
```json
{
    "status_keberadaan": "rusak",
    "kondisi_fisik": "rusak_berat",
    "catatan": "Layar monitor retak dan korslet"
}
```

### POST /api/sinapra/stock-opname/{id}/finish
Menutup sesi stock opname dan secara otomatis menyinkronkan kondisi fisik atau kehilangan ke tabel master aset `sinapra_aset`.

---

## 2. Mutasi Aset Antar-Ruangan

### GET /api/sinapra/mutasi-aset
Mengambil daftar pengajuan mutasi aset antar-ruang. Bagi laboran, otomatis dibatasi pada aset yang berasal dari atau menuju ke ruang lab binaannya.

#### Query Parameters
- `search` (string, optional) - Pencarian nama/kode aset, alasan, atau catatan.
- `ruangan_asal_id` (integer, optional) - Filter ID ruangan asal.
- `ruangan_tujuan_id` (integer, optional) - Filter ID ruangan tujuan.
- `status` (string, optional) - Filter status: `diajukan`, `disetujui`, `ditolak`.

### POST /api/sinapra/mutasi-aset
Mengajukan permohonan mutasi aset ke ruangan atau laboratorium lain.

#### Body Request (JSON)
```json
{
    "aset_id": 5,
    "ruangan_tujuan_id": 3,
    "alasan": "Kebutuhan praktikum jaringan komputer",
    "catatan": "Kabel power disertakan"
}
```

### POST /api/sinapra/mutasi-aset/{id}/approve
Menyetujui atau menolak mutasi aset. Apabila disetujui, `ruangan_id` pada entitas aset otomatis diperbarui ke ruangan tujuan.

#### Body Request (JSON)
```json
{
    "is_approved": true,
    "catatan": "Aset diterima dalam kondisi lengkap"
}
```

---

## 3. Penghapusan / Disposal Aset

### GET /api/sinapra/disposal-aset
Mengambil daftar BAP usulan pemutihan, lelang, hibah, atau penghapusan aset.

### POST /api/sinapra/disposal-aset
Mencatat pengajuan BAP penghapusan aset yang sudah rusak total atau kadaluwarsa.

#### Body Request (JSON)
```json
{
    "aset_id": 8,
    "nomor_bap": "BAP-DISP/2026/09/0012",
    "tanggal_disposal": "2026-09-24",
    "metode_disposal": "rusak_total",
    "nilai_residu": 150000,
    "alasan": "Motherboard terbakar dan tidak ekonomis diperbaiki",
    "catatan": "Komponen sparepart telah di-dismantle"
}
```

### POST /api/sinapra/disposal-aset/{id}/approve
Keputusan persetujuan pemutihan aset (Khusus Admin Sarpras / Superadmin). Apabila disetujui, status aset diubah menjadi `dihapus` dan dinonaktifkan dari peminjaman (`is_borrowable = false`).

#### Body Request (JSON)
```json
{
    "is_approved": true,
    "catatan": "Penghapusan aset disetujui pimpinan"
}
```

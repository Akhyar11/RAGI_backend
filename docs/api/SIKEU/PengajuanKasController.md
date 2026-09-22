# PengajuanKasController

> **Modul**: SIKEU / **Base URL**: /api/v1/sikeu / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-09-19

Modul ini mengelola siklus permohonan pencairan kas unit operasional dan panjar perjalanan dinas luar (SIMPEG), proses verifikasi dan persetujuan oleh bagian keuangan/pimpinan, pencairan saldo kas, pembuatan mutasi pengeluaran kampus, serta penolakan pengajuan.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/pengajuan-kas` | Daftar pengajuan pencairan kas & panjar dinas | ✅ Bearer |
| POST | `/api/v1/sikeu/pengajuan-kas` | Buat pengajuan pencairan kas baru | ✅ Bearer |
| POST | `/api/v1/sikeu/pengajuan-kas/{id}/approve` | Persetujuan & pencairan kas/panjar dinas | ✅ Bearer |
| POST | `/api/v1/sikeu/pengajuan-kas/{id}/reject` | Tolak pengajuan pencairan kas/panjar dinas | ✅ Bearer |

---

## 1. GET /api/v1/sikeu/pengajuan-kas

> Mengambil daftar pengajuan pencairan kas dengan filter status, unit kas, pencarian, dan pagination.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari nomor pengajuan, judul, deskripsi |
| `status` | string | ❌ | — | Filter status: `pending_keuangan`, `dicairkan`, `ditolak` |
| `unit_kas_id` | integer | ❌ | — | Filter berdasarkan unit kas |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan (`created_at`, `nominal_diajukan`, `status`, `id`) |
| `sort_order` | string | ❌ | `desc` | Arah urutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Data pengajuan pencairan kas berhasil diambil",
    "data": [
        {
            "id": 1,
            "nomor_pengajuan": "PK-1726700000",
            "unit_kas_id": 1,
            "pemohon_id": 2,
            "judul_pengajuan": "Panjar Perjalanan Dinas: Konsorsium AI",
            "deskripsi": "Pencairan panjar dana tugas dinas No. ST/2026/001 ke Jakarta",
            "nominal_diajukan": "1500000.00",
            "nominal_disetujui": "1500000.00",
            "status": "pending_keuangan",
            "created_at": "2026-09-19T08:00:00.000000Z",
            "updated_at": "2026-09-19T08:00:00.000000Z",
            "unit_kas": {
                "id": 1,
                "nama_kas": "Kas Operasional Rektorat",
                "kode_kas": "KAS-REK-01",
                "saldo_saat_ini": "50000000.00"
            },
            "pemohon": {
                "id": 2,
                "name": "Dr. Ahmad Yani",
                "email": "ahmad.yani@kampus.ac.id"
            },
            "surat_tugas": {
                "id": 5,
                "nomor_surat": "ST/2026/001",
                "status_pencairan": "belum_cair"
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
        "search": "",
        "sort_by": "created_at",
        "sort_order": "desc"
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Akses Ditolak: Anda tidak memiliki izin untuk melihat pengajuan kas."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data pengajuan kas tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Parameter query tidak valid.",
    "errors": {
        "per_page": [
            "Nilai per_page harus berupa angka."
        ]
    }
}
```

---

## 2. POST /api/v1/sikeu/pengajuan-kas

> Membuat pengajuan permohonan pencairan kas unit baru.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "unit_kas_id": 1,
    "judul_pengajuan": "Pembelian Perlengkapan Kantor Fakultas",
    "deskripsi": "Kertas, tinta printer, dan ATK bulanan",
    "nominal_diajukan": 2500000
}
```

| Field | Type | Required | Validasi |
|---|---|---|---|
| `unit_kas_id` | integer | ✅ | Exists di tabel `sikeu_unit_kas,id` |
| `judul_pengajuan` | string | ✅ | Maksimal 255 karakter |
| `deskripsi` | string | ❌ | Teks opsional |
| `nominal_diajukan` | numeric | ✅ | Min: 1000 |

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Pengajuan pencairan kas berhasil dibuat",
    "data": {
        "id": 2,
        "nomor_pengajuan": "PK-1726710000",
        "unit_kas_id": 1,
        "pemohon_id": 2,
        "judul_pengajuan": "Pembelian Perlengkapan Kantor Fakultas",
        "deskripsi": "Kertas, tinta printer, dan ATK bulanan",
        "nominal_diajukan": "2500000.00",
        "nominal_disetujui": null,
        "status": "pending_keuangan",
        "created_at": "2026-09-19T08:30:00.000000Z",
        "updated_at": "2026-09-19T08:30:00.000000Z"
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Akses Ditolak: Anda tidak memiliki izin untuk membuat pengajuan kas."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "unit_kas_id": [
            "Unit kas yang dipilih tidak valid."
        ],
        "nominal_diajukan": [
            "Nominal diajukan minimal Rp 1.000."
        ]
    }
}
```

---

## 3. POST /api/v1/sikeu/pengajuan-kas/{id}/approve

> Menyetujui dan mencairkan pengajuan kas. Sistem otomatis memotong saldo unit kas, mencatat transaksi mutasi debet kas, membuat rekaman pengeluaran kampus, dan mengupdate status pencairan surat tugas SIMPEG menjadi `sudah_cair`.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "nominal_disetujui": 2500000,
    "unit_kas_id": 1,
    "catatan": "Disetujui dan ditransfer via bendahara pengeluaran"
}
```

| Field | Type | Required | Validasi |
|---|---|---|---|
| `nominal_disetujui` | numeric | ❌ | Min: 0 (default: mengikuti nominal_diajukan) |
| `unit_kas_id` | integer | ❌ | Exists di `sikeu_unit_kas,id` |
| `catatan` | string | ❌ | Catatan opsional approval |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Dana berhasil dicairkan dan pengeluaran kas telah dicatat.",
    "data": {
        "id": 2,
        "nomor_pengajuan": "PK-1726710000",
        "unit_kas_id": 1,
        "nominal_diajukan": "2500000.00",
        "nominal_disetujui": "2500000.00",
        "status": "dicairkan",
        "approved_keuangan_by": 1,
        "approved_keuangan_at": "2026-09-19T08:35:00.000000Z"
    }
}
```

### Response Error

**400 Bad Request**
```json
{
    "status": "error",
    "message": "Pengajuan kas sudah dicairkan sebelumnya atau saldo unit kas tidak mencukupi."
}
```

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Akses Ditolak: Anda tidak memiliki izin untuk menyetujui pengajuan kas."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Pengajuan pencairan kas tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "nominal_disetujui": [
            "Nominal disetujui harus berupa angka bernilai minimal 0."
        ]
    }
}
```

---

## 4. POST /api/v1/sikeu/pengajuan-kas/{id}/reject

> Menolak permohonan pencairan kas dan mencatat alasan penolakan. Jika terhubung dengan surat tugas dinas, status pencairan pada surat tugas diperbarui menjadi `ditolak`.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "catatan": "Anggaran unit untuk pos belanja ini telah habis untuk periode berjalan."
}
```

| Field | Type | Required | Validasi |
|---|---|---|---|
| `catatan` | string | ❌ | Alasan penolakan pengajuan kas |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Pengajuan pencairan kas berhasil ditolak.",
    "data": {
        "id": 2,
        "nomor_pengajuan": "PK-1726710000",
        "status": "ditolak",
        "deskripsi": "Pembelian Perlengkapan Kantor Fakultas\n[Ditolak Keuangan]: Anggaran unit untuk pos belanja ini telah habis untuk periode berjalan.",
        "approved_keuangan_by": 1,
        "approved_keuangan_at": "2026-09-19T08:36:00.000000Z"
    }
}
```

### Response Error

**400 Bad Request**
```json
{
    "status": "error",
    "message": "Pengajuan yang sudah dicairkan tidak dapat ditolak."
}
```

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Akses Ditolak: Anda tidak memiliki izin untuk menolak pengajuan kas."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Pengajuan pencairan kas tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "catatan": [
            "Catatan penolakan harus berupa teks."
        ]
    }
}
```

---

## Catatan Khusus & Integritas Data
- **Posting Pengeluaran & Audit Transaksi**: Persetujuan pencairan kas secara otomatis memicu pencatatan transaksi kas keluar (`TransaksiKasUnit`), pengurangan saldo kas terkait, dan rekaman pengeluaran kampus (`PengeluaranKampus`).
- **Soft Delete**: Data riwayat pengajuan kas dilindungi dengan soft delete (`deleted_at`), mencegah penghapusan fisik permanen untuk kepatuhan audit akuntansi.
- **Password & Token**: Password, hashed password, dan token autentikasi tidak pernah dikembalikan dalam response API ini.

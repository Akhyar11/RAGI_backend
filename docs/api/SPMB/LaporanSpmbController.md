# LaporanSpmbController

> **Modul**: SPMB / **Base URL**: `/api/spmb/laporan` / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-09-25

Statistik dan export data pendaftaran SPMB (read-only / data agregat). Sumber tabel: `spmb_pendaftaran_calon_mhs`, `spmb_hasil_seleksi`, `spmb_gelombang_penerimaan`, dan `siakad_program_studi`.

## Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ❌ (endpoint hanya GET) |

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/spmb/laporan/statistik` | Statistik agregat pendaftaran & kelulusan | ✅ |
| GET | `/api/spmb/laporan/export-csv` | Export seluruh pendaftar ke CSV | ✅ |

---

## [GET] /api/spmb/laporan/statistik

> Membutuhkan permission `spmb.laporan.read`.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari nama/kode prodi (opsional) |
| `sort_by` | string | ❌ | `created_at` | `created_at`, `total`, `nama` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

### Request Body

```json
{}
```

> Endpoint GET tidak memerlukan body.

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": {
        "per_status": [
            { "status": "submitted", "total": 120 },
            { "status": "lulus", "total": 80 }
        ],
        "lulus_per_prodi": [
            { "nama_prodi": "Teknik Informatika", "program_studi_diterima_id": 7, "total_lulus": 40 }
        ],
        "per_gelombang": [
            { "nama_gelombang": "Gelombang 1", "total": 120 }
        ],
        "funnel_data": [
            { "label": "Total Pendaftar", "value": 120 },
            { "label": "Sudah Membayar", "value": 100 },
            { "label": "Lolos Administrasi", "value": 90 },
            { "label": "Lolos Seleksi", "value": 80 },
            { "label": "Daftar Ulang Lunas", "value": 70 }
        ]
    },
    "meta": { "current_page": 1, "per_page": 15, "total": 120, "last_page": 8, "from": 1, "to": 15 },
    "filters": { "search": null, "sort_by": "created_at", "sort_order": "desc" }
}
```

### Response Error

**401 Unauthorized**
```json
{ "status": "error", "message": "Unauthenticated." }
```
**403 Forbidden**
```json
{ "status": "error", "message": "This action is unauthorized." }
```
**404 Not Found**
```json
{ "status": "error", "message": "Data laporan tidak ditemukan." }
```
**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": { "per_page": ["The per page must not be greater than 100."] }
}
```

---

## [GET] /api/spmb/laporan/export-csv

> Membutuhkan permission `spmb.laporan.export`. Mengembalikan file `text/csv`.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari nama/no pendaftaran |
| `sort_by` | string | ❌ | `created_at` | `created_at`, `nama` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

### Request Body

```json
{}
```

> Endpoint GET tidak memerlukan body.

### Response Sukses

**200 OK** — `Content-Type: text/csv`
```
No Pendaftaran,Nama Lengkap,NIK,Asal Sekolah,Status Pendaftaran,Status Pembayaran,Status Kelulusan,Status Daftar Ulang
REG-20260901-1234,Budi Santoso,3201010101010001,SMAN 1,lulus,lunas,lulus,lunas
```

### Response Error

**401 Unauthorized**
```json
{ "status": "error", "message": "Unauthenticated." }
```
**403 Forbidden**
```json
{ "status": "error", "message": "This action is unauthorized." }
```
**404 Not Found**
```json
{ "status": "error", "message": "Tidak ada data untuk diexport." }
```
**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": { "tgl_selesai": ["The tgl selesai must be a date after or equal to tgl mulai."] }
}
```

---

### Catatan Tambahan

> - Tidak ada soft-delete pada endpoint laporan ini (data agregat read-only).
> - Password/token tidak pernah dikembalikan pada response.
> - Data pribadi pendaftar hanya untuk keperluan internal administrasi SPMB.

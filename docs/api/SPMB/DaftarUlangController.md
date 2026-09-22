# DaftarUlangController

> **Modul**: SPMB / **Base URL**: `/api/spmb/daftar-ulang` / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-09-25

Menangani proses **daftar ulang** calon mahasiswa yang telah lulus seleksi: penerbitan tagihan (dari komponen Master Biaya yang tidak ditandai beban pendaftaran, fallback tarif UKT SIKEU) dan konfirmasi daftar ulang yang memicu konversi ke SIAKAD.

## Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ untuk POST |

## Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Tidak digunakan (endpoint aksi, non-paginasi) |
| `sort_by` | string | ❌ | `created_at` | Tidak digunakan (endpoint aksi) |
| `sort_order` | string | ❌ | `desc` | Tidak digunakan (endpoint aksi) |
| `per_page` | integer | ❌ | `15` | Tidak digunakan (endpoint aksi) |
| `page` | integer | ❌ | `1` | Tidak digunakan (endpoint aksi) |

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| POST | `/api/spmb/daftar-ulang/{pendaftaran_id}/generate-tagihan` | Terbitkan tagihan daftar ulang (tagihan eksternal SIKEU) | ✅ |
| POST | `/api/spmb/daftar-ulang/{pendaftaran_id}/konfirmasi` | Konfirmasi daftar ulang & konversi ke SIAKAD | ✅ |

---

## [POST] /api/spmb/daftar-ulang/{pendaftaran_id}/generate-tagihan

> Membuat tagihan daftar ulang untuk pendaftar yang berstatus **lulus**. Komponen diambil dari Master Biaya (`gelombang_id` + `program_studi_id`) yang **tidak** ditandai `dibebankan_saat_pendaftaran` dan bernominal > 0. Bila belum dikonfigurasi, fallback ke Master Tarif UKT SIKEU.

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `pendaftaran_id` | integer | ✅ | ID `spmb_pendaftaran_calon_mhs` |

### Request Body

```json
{}
```

> Tidak ada field body; hanya path parameter `pendaftaran_id`.

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Tagihan Daftar Ulang berhasil dibuat.",
    "data": {
        "tagihan": {
            "id": 25,
            "nomor_tagihan": "INV-SPMB-20260925-A1B2C",
            "calon_mahasiswa_id": 7,
            "tipe_referensi": "calon_mahasiswa",
            "total_tagihan": 11750000,
            "total_bayar": 0,
            "status": "belum_bayar",
            "source_system": "SPMB",
            "detail_tagihan": [
                { "master_biaya_id": 101, "nominal": 2750000, "keterangan": "Paket Seragam" },
                { "master_biaya_id": 102, "nominal": 9000000, "keterangan": "Dana Pengembangan Institusi" }
            ]
        },
        "virtual_account": {
            "va_number": "88826091200025",
            "bank_kode": "BNI",
            "nominal": 11750000,
            "status": "aktif"
        }
    },
    "meta": null,
    "filters": { "search": null, "sort_by": "created_at", "sort_order": "desc" }
}
```

### Response Error

**400 Bad Request**
```json
{ "status": "error", "message": "Peserta belum lulus seleksi." }
```
```json
{ "status": "error", "message": "Tagihan daftar ulang sudah dibuat, silakan lanjutkan pembayaran." }
```
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
{ "status": "error", "message": "No query results for model [App\\Models\\Spmb\\HasilSeleksi] 99." }
```
**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": { "pendaftaran_id": ["The selected pendaftaran id is invalid."] }
}
```

---

## [POST] /api/spmb/daftar-ulang/{pendaftaran_id}/konfirmasi

> Menandai daftar ulang **lunas** dan memicu event `MahasiswaDiterima` untuk konversi ke SIAKAD.

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `pendaftaran_id` | integer | ✅ | ID `spmb_pendaftaran_calon_mhs` |

### Request Body

```json
{}
```

> Tidak ada field body; hanya path parameter `pendaftaran_id`.

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Daftar Ulang selesai. Mahasiswa berhasil dikonversi ke SIAKAD.",
    "data": null,
    "meta": null
}
```

### Response Error

**400 Bad Request**
```json
{ "status": "error", "message": "Sudah melakukan daftar ulang." }
```
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
{ "status": "error", "message": "No query results for model [App\\Models\\Spmb\\PendaftaranCalonMhs] 99." }
```
**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": { "pendaftaran_id": ["The selected pendaftaran id is invalid."] }
}
```

---

### Catatan Tambahan

> - Komponen biaya bernilai `0` tidak dihitung.
> - Response endpoint aksi ini **non-paginasi** (tanpa `meta`).
> - Perubahan status daftar ulang tercatat pada audit log (`PendaftaranCalonMhsObserver`).
> - Tidak ada operasi soft-delete pada endpoint ini.
> - Password/token tidak pernah dikembalikan pada response.

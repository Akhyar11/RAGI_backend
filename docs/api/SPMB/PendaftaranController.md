# PendaftaranController

> **Modul**: SPMB / **Base URL**: `/api/spmb/pendaftaran` / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat**: 2026-09-30 / **Diperbarui**: 2026-09-30

Menangani data pendaftaran calon mahasiswa untuk sisi admin/panitia SPMB: daftar pendaftar, detail pendaftar (termasuk ringkasan pembayaran **daftar ulang**), verifikasi berkas, dan penetapan status kelulusan administrasi.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/spmb/pendaftaran` | Daftar pendaftar (filter, sorting, paginasi) | ✅ |
| GET | `/api/spmb/pendaftaran/{id}` | Detail pendaftar + ringkasan daftar ulang | ✅ |
| POST | `/api/spmb/pendaftaran/berkas/{id}/verify` | Verifikasi satu berkas pendaftaran | ✅ |
| POST | `/api/spmb/pendaftaran/{id}/status` | Tetapkan status pendaftaran (verifikasi) | ✅ |

---

## GET /api/spmb/pendaftaran

> Mengambil daftar pendaftar calon mahasiswa beserta relasi gelombang, program studi, dan user.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | Hanya POST/PUT |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari berdasarkan nama, no pendaftaran, NIK, atau akun user |
| `status` | string | ❌ | — | Filter status pendaftaran |
| `status_pembayaran` | string | ❌ | — | Filter status pembayaran pendaftaran |
| `gelombang_id` | integer | ❌ | — | Filter berdasarkan gelombang |
| `referral_code` | string | ❌ | — | Filter kode referral terpakai |
| `sort_by` / `order_by` | string | ❌ | `created_at` | Kolom pengurutan |
| `sort_order` / `order_dir` | string | ❌ | `desc` | Arah urutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 7,
                "no_pendaftaran": "REG-20260930-1234",
                "nama_lengkap": "Budi Santoso",
                "nik": "3273010102030001",
                "status": "lulus_administrasi",
                "status_pembayaran": "lunas",
                "gelombang_penerimaan": { "id": 2, "nama": "Gelombang 1" },
                "program_studi": { "id": 12, "nama": "S1 Informatika" }
            }
        ],
        "per_page": 15,
        "total": 1,
        "last_page": 1,
        "from": 1,
        "to": 1
    }
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
**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": { "per_page": ["The per page must be an integer."] }
}
```

---

## GET /api/spmb/pendaftaran/{id}

> Mengambil detail satu pendaftar beserta relasi. Field `daftar_ulang` berisi ringkasan pembayaran daftar ulang (tagihan diterbitkan & dibayar di modul **SIKEU**): total tagihan, sudah dibayar, sisa kurang, virtual account, dan riwayat pembayaran.

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID `spmb_pendaftaran_calon_mhs` |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Detail pendaftaran berhasil diambil",
    "data": {
        "id": 7,
        "no_pendaftaran": "REG-20260930-1234",
        "nama_lengkap": "Budi Santoso",
        "status": "lulus_administrasi",
        "status_pembayaran": "lunas",
        "program_studi": { "id": 12, "nama": "S1 Informatika" },
        "dokumen_pendaftaran": [],
        "daftar_ulang": {
            "has_tagihan": true,
            "status_daftar_ulang": "menunggu_pembayaran",
            "tagihan": {
                "id": 25,
                "nomor_tagihan": "INV-SPMB-20260930-A1B2C",
                "status": "sebagian",
                "due_date": "2026-10-30",
                "total_tagihan": 11750000,
                "total_potongan": 0,
                "total_denda": 0,
                "total_bersih": 11750000,
                "sudah_dibayar": 5000000,
                "sisa_kurang": 6750000,
                "persen_terbayar": 42.55,
                "virtual_account": {
                    "va_number": "88826091100025",
                    "bank_kode": "BNI",
                    "bank_nama": "Bank BNI",
                    "nominal": 11750000,
                    "status": "aktif",
                    "expired_at": "2026-10-30 23:59:59"
                },
                "riwayat_pembayaran": [
                    {
                        "id": 41,
                        "kode_transaksi": "TRX-9001",
                        "jumlah_bayar": 5000000,
                        "channel_bayar": "BANK_VA",
                        "status": "success",
                        "paid_at": "2026-09-30T10:15:00.000000Z"
                    }
                ]
            }
        }
    }
}
```

> Bila pendaftar belum memiliki tagihan daftar ulang, field `daftar_ulang` bernilai:
> ```json
> { "has_tagihan": false, "status_daftar_ulang": "belum", "tagihan": null }
> ```

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
{ "status": "error", "message": "No query results for model [App\\Models\\Spmb\\PendaftaranCalonMhs] 99." }
```

---

## POST /api/spmb/pendaftaran/berkas/{id}/verify

> Menandai satu berkas pendaftaran sebagai valid / belum valid disertai catatan opsional.

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID `spmb_dokumen_pendaftaran` |

### Request Body

```json
{
    "is_verified": "boolean, required",
    "catatan": "string, nullable"
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Status berkas berhasil diperbarui",
    "data": {
        "id": 88,
        "pendaftaran_id": 7,
        "jenis_dokumen": "ijazah",
        "file_path": "spmb/dokumen_pendaftaran/uuid.pdf",
        "is_verified": true,
        "catatan": null
    }
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
{ "status": "error", "message": "No query results for model [App\\Models\\Spmb\\DokumenPendaftaran] 99." }
```
**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": { "is_verified": ["The is verified field is required."] }
}
```

---

## POST /api/spmb/pendaftaran/{id}/status

> Menetapkan status pendaftaran (mis. `lulus_administrasi` / `gagal_administrasi`) dan menyinkronkan status referral terkait.

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID `spmb_pendaftaran_calon_mhs` |

### Request Body

```json
{
    "status": "string, required, in:draft,submitted,verified,lulus_administrasi,gagal_administrasi",
    "catatan_verifikasi": "string, nullable"
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Status pendaftaran berhasil diperbarui",
    "data": {
        "id": 7,
        "status": "lulus_administrasi",
        "catatan_verifikasi": "Berkas lengkap.",
        "diverifikasi_oleh": 3,
        "diverifikasi_at": "2026-09-30T10:20:00.000000Z"
    }
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
{ "status": "error", "message": "No query results for model [App\\Models\\Spmb\\PendaftaranCalonMhs] 99." }
```
**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": { "status": ["The selected status is invalid."] }
}
```

---

### Catatan Tambahan

> - Tagihan & pembayaran daftar ulang dikelola modul **SIKEU** (`sikeu_tagihan_mahasiswa`, `tipe_referensi = spmb_daftar_ulang`); SPMB hanya membaca status terkini.
> - `sisa_kurang` dihitung dari `total_tagihan + total_denda - total_potongan - total_bayar` (minimal 0).
> - Endpoint ini tidak melakukan operasi soft-delete.
> - Password/token tidak pernah dikembalikan pada response.

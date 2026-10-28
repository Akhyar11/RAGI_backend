# PendaftaranController

> **Modul**: SPMB / **Base URL**: `/api/spmb/pendaftaran` / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat**: 2026-09-30 / **Diperbarui**: 2026-10-12

Menangani data pendaftaran calon mahasiswa untuk sisi admin/panitia SPMB dan calon mahasiswa: daftar pendaftar, detail pendaftar (termasuk ringkasan pembayaran **daftar ulang**), unduh dokumen PDF **SK Tanda Lulus**, verifikasi berkas, penetapan status kelulusan administrasi, dan sinkronisasi data dari sistem eksternal.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/spmb/pendaftaran` | Daftar pendaftar (filter, sorting, paginasi) | ✅ |
| POST | `/api/spmb/pendaftaran/sync-external` | Sinkronisasi pendaftar dari API sistem eksternal via IAM | ✅ `spmb.manage` |
| GET | `/api/spmb/pendaftaran/{id}` | Detail pendaftar + ringkasan daftar ulang | ✅ |
| GET | `/api/spmb/pendaftaran/{id}/sk-lulus` | Unduh PDF SK bernomor resmi Arsip | ✅ |
| POST | `/api/spmb/pendaftaran/{id}/terbitkan-sk` | Ajukan nomor ke Arsip atau buat PDF setelah nomor disetujui | ✅ `spmb.manage` |
| POST | `/api/spmb/pendaftaran/berkas/{id}/verify` | Verifikasi satu berkas pendaftaran | ✅ |
| POST | `/api/spmb/pendaftaran/{id}/status` | Tetapkan status pendaftaran (verifikasi) | ✅ |
| POST | `/api/spmb/pendaftaran/{id}/konversi-mahasiswa` | Konversi manual calon mahasiswa → mahasiswa resmi (generate NIM, role, email) | ✅ `spmb.manage` |

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

## POST /api/spmb/pendaftaran/sync-external

> Menarik dan menyinkronkan data calon mahasiswa baru dari endpoint API eksternal (sistem lama) yang terkonfigurasi pada modul IAM (`ApiIntegrationService::call('spmb_pendaftaran')`). Proses berjalan secara idempoten berbasis `no_pendaftaran`/`nik`, membuat akun portal (`calon_mhs`), dan memetakan master relasi secara dinamis.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tahun` / `angkatan` | string | ❌ | — | Filter periode/angkatan pendaftaran di API eksternal |
| `limit` | integer | ❌ | — | Batas data yang ditarik dari API eksternal |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Sinkronisasi data pendaftaran SPMB eksternal selesai.",
    "data": {
        "total_fetched": 30,
        "inserted": 20,
        "updated": 10,
        "skipped": 0,
        "errors": []
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

**500 Internal Server Error** — kegagalan koneksi API integrasi eksternal
```json
{
    "status": "error",
    "message": "Gagal melakukan sinkronisasi data SPMB: Integrasi API dengan key 'spmb_pendaftaran' belum terdaftar di sistem."
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
        "nomor_sk": null,
        "sk_arsip_request_id": 34,
        "sk_arsip_request": {
            "id": 34,
            "kode_request": "REQ-ARSIP-2026-1727777777-ABCD",
            "status": "menunggu_verifikasi"
        },
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

## GET /api/spmb/pendaftaran/{id}/sk-lulus

> Mengunduh berkas Surat Keterangan (SK) Tanda Lulus resmi berformat PDF. Dapat diakses oleh calon mahasiswa pemilik pendaftaran atau panitia/admin SPMB jika pendaftaran telah berstatus `lulus_administrasi` atau `mahasiswa_baru`. Parameter `{id}` juga mendukung nilai `'me'` untuk merujuk pada pendaftaran milik user yang sedang terautentikasi.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/pdf, application/json` | ✅ |

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer \| string | ✅ | ID Pendaftaran (`spmb_pendaftaran_calon_mhs.id`) atau `'me'` |

### Response Sukses

**200 OK**
Mengembalikan stream biner berkas PDF (`application/pdf`) dengan header:
- `Content-Type: application/pdf`
- `Content-Disposition: attachment; filename="SK-Tanda-Lulus-REG-20260930-1234.pdf"`

> Endpoint hanya menyajikan PDF yang menggunakan nomor SK resmi dari Arsip. Jika nomor belum disetujui atau tidak terhubung ke record Arsip, endpoint mengembalikan `409 Conflict`; endpoint ini tidak membuat nomor atau PDF internal.

### Response Error

**401 Unauthorized**
```json
{ "status": "error", "message": "Unauthenticated." }
```
**403 Forbidden**
```json
{ "status": "error", "message": "Anda tidak memiliki hak akses untuk mengunduh SK pendaftaran ini." }
```
**404 Not Found**
```json
{ "status": "error", "message": "No query results for model [App\\Models\\Spmb\\PendaftaranCalonMhs] 99." }
```
**400 Bad Request**
```json
{
    "status": "error",
    "message": "SK Tanda Lulus belum dapat diunduh karena pendaftaran belum dinyatakan lulus seleksi administrasi."
}
```
**409 Conflict**
```json
{
    "status": "error",
    "message": "SK belum dapat diunduh karena nomor resminya masih menunggu persetujuan Arsip."
}
```

---

## POST /api/spmb/pendaftaran/{id}/terbitkan-sk

> Mengajukan nomor SK ke **Arsip** memakai konfigurasi template. Arsip memverifikasi dan menerbitkan nomor; setelah nomor disetujui, pemanggilan endpoint ini membuat PDF dengan nomor tersebut dan menyimpannya pada disk privat. Asal permohonan harus `spmb`; unit penerbit mengikuti ID unit aktif pada template (template penerbitan SPMB dikonfigurasi menggunakan unit `INDO`). Arsip menolak override unit dari nilai yang diminta. Jika nomor sesuai unit permohonan tidak ditemukan setelah persetujuan, SPMB mengembalikan `422` dan tidak membuat permohonan pengganti otomatis. Hanya dapat dipanggil pengguna dengan permission `spmb.manage` dan pendaftaran berstatus lulus.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID `spmb_pendaftaran_calon_mhs` |

### Response Saat Nomor Menunggu Persetujuan Arsip

**200 OK**
```json
{
    "status": "success",
    "message": "Permohonan nomor SK dikirim ke Arsip dan sedang menunggu persetujuan.",
    "data": {
        "status": "menunggu_verifikasi",
        "arsip_request_id": 34,
        "nomor_sk": null,
        "sk_file_path": null,
        "sk_file_url": null
    }
}
```

### Response Setelah Nomor Disetujui Arsip

**200 OK**
```json
{
    "status": "success",
    "message": "Nomor resmi Arsip telah disetujui dan SK berhasil diterbitkan serta diarsipkan.",
    "data": {
        "status": "diterbitkan",
        "arsip_request_id": 34,
        "nomor_sk": "1/DII/INDO/X/2026",
        "sk_file_path": "spmb/sk-tanda-lulus/2026/10/781138ef-761c-4cf9-9313-adefda652778.pdf",
        "sk_file_url": "http://localhost:9000/api/files/view?path=spmb%2Fsk-tanda-lulus%2F...&signature=..."
    }
}
```

### Response Error

**400 Bad Request**
```json
{
    "status": "error",
    "message": "SK Tanda Lulus hanya dapat diterbitkan untuk pendaftaran yang sudah dinyatakan lulus seleksi."
}
```
**403 Forbidden**
```json
{ "status": "error", "message": "Anda tidak memiliki izin untuk melakukan aksi ini." }
```
**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "unit_surat_id": ["Unit penerbit harus berasal dari master unit Arsip yang aktif."]
    }
}
```
Jika Arsip sudah menyetujui permohonan tetapi nomor INDO tidak ditemukan, error dikembalikan pada field `nomor_sk` dan permohonan pengganti tidak dibuat otomatis.

### Catatan Tambahan

> - `module_id`, `klasifikasi_surat_id`, dan `unit_surat_id` memakai ID dari master; unit penerbit harus berkode `INDO`. Template aktif yang belum lengkap tidak dapat menerbitkan SK.
> - Permohonan yang ditolak Arsip dapat diajukan ulang. SK tidak diterbitkan sampai Arsip menyetujui permohonan.
> - `nomor_sk`, `sk_file_path`, dan `sk_file_url` juga tersedia pada response `GET /api/spmb/pendaftaran/{id}`.

---

## POST /api/spmb/pendaftaran/{id}/konversi-mahasiswa

> Mengonversi calon mahasiswa yang berstatus `lulus_administrasi` menjadi mahasiswa resmi secara langsung (sinkron). Proses ini akan: (1) menerbitkan NIM baru, (2) membuat/memperbarui record `siakad_mahasiswa`, (3) sync ke SIKEU, (4) assign role `mahasiswa` pada akun IAM, dan (5) membuat email kampus via Google Workspace. Hanya dapat dipanggil oleh pengguna dengan permission `spmb.manage`.

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID `spmb_pendaftaran_calon_mhs` |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Calon mahasiswa berhasil dikonversi menjadi mahasiswa. NIM: 260100001",
    "data": {
        "pendaftaran": {
            "id": 7,
            "no_pendaftaran": "REG-20260930-1234",
            "nama_lengkap": "Budi Santoso",
            "status": "mahasiswa_baru",
            "nim": "260100001"
        },
        "nim": "260100001",
        "mahasiswa_id": 42
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
**422 Unprocessable Entity** — sudah dikonversi
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": { "status": ["Pendaftar ini sudah dikonversi menjadi mahasiswa (NIM: 260100001)."] }
}
```
**422 Unprocessable Entity** — status tidak memenuhi syarat
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": { "status": ["Konversi ke mahasiswa hanya dapat dilakukan pada pendaftaran berstatus Lulus Administrasi. Status saat ini: draft"] }
}
```

---

### Catatan Tambahan

> - Tagihan & pembayaran daftar ulang dikelola modul **SIKEU** (`sikeu_tagihan_mahasiswa`, `tipe_referensi = spmb_daftar_ulang`); SPMB hanya membaca status terkini.
> - `sisa_kurang` dihitung dari `total_tagihan + total_denda - total_potongan - total_bayar` (minimal 0).
> - Endpoint ini tidak melakukan operasi soft-delete.
> - Password/token tidak pernah dikembalikan pada response.
> - Endpoint `konversi-mahasiswa` memproses pembuatan email kampus Google Workspace di luar DB Transaction (non-blocking). Kegagalan API Google Workspace tidak akan membatalkan konversi NIM dan role.

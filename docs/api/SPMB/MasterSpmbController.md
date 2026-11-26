# MasterSpmbController

> **Modul**: SPMB / **Base URL**: `/api/spmb/master` / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-10-01  

Dokumentasi API untuk referensi master SPMB meliputi tahun akademik, jalur pendaftaran, gelombang pendaftaran, dan opsi master SPMB.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/spmb/master/tahun-akademik` | Mendapatkan daftar master tahun akademik aktif | ✅ Admin / Staff |
| GET | `/api/spmb/master/jalur` | Mendapatkan daftar jalur seleksi penerimaan | ✅ Admin / Staff |
| GET | `/api/spmb/master/gelombang` | Mendapatkan daftar gelombang penerimaan berpaginasi | ✅ Admin / Staff |
| GET | `/api/spmb/gelombang/resolve` | Menentukan gelombang (otomatis/pilih/tidak tersedia) untuk jalur yang dipilih | ✅ Admin SPMB / Calon Mhs |
| GET | `/api/spmb/master/gelombang/{id}` | Mendapatkan detail informasi gelombang penerimaan | ✅ Admin / Staff |
| POST | `/api/spmb/master/gelombang` | Membuat gelombang penerimaan baru | ✅ Admin / Staff |
| PUT | `/api/spmb/master/gelombang/{id}` | Memperbarui gelombang penerimaan | ✅ Admin / Staff |
| DELETE | `/api/spmb/master/gelombang/{id}` | Menghapus gelombang penerimaan | ✅ Admin / Staff |
| GET | `/api/spmb/biaya-pendaftaran` | Rincian beban awal pendaftaran & daftar ulang per jalur masuk + prodi | ❌ Publik |
| GET | `/api/spmb/master/options` | Mendapatkan seluruh opsi master SPMB secara ringkas | ✅ Admin / Staff |

---

## [GET] /api/spmb/master/tahun-akademik

> Mengambil daftar master tahun akademik SPMB yang aktif.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data tahun akademik berhasil dimuat.",
    "data": [
        {
            "id": 1,
            "kode": "20261",
            "nama": "2026/2027 Ganjil",
            "is_active": true,
            "is_current": true
        },
        {
            "id": 2,
            "kode": "20252",
            "nama": "2025/2026 Genap",
            "is_active": false,
            "is_current": false
        }
    ]
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
    "message": "Anda tidak memiliki izin untuk melihat tahun akademik SPMB."
}
```

---

## [GET] /api/spmb/master/jalur

> Mengambil daftar master jalur seleksi penerimaan mahasiswa baru.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Default | Deskripsi |
|---|---|---|---|
| `search` | string | - | Pencarian kata kunci nama atau kode jalur |
| `status` | boolean | - | Filter status aktif (`true` atau `false`) |
| `sort_by` | string | `created_at` | Kolom pengurutan (`id`, `kode`, `nama`, `created_at`) |
| `sort_order` | string | `desc` | Arah pengurutan (`asc`, `desc`) |
| `per_page` | integer | `15` | Jumlah data per halaman (1 s/d 100) |
| `page` | integer | `1` | Nomor halaman data |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data jalur penerimaan berhasil dimuat.",
    "data": [
        {
            "id": 1,
            "kode": "REG",
            "nama": "Reguler",
            "is_active": true,
            "created_at": "2026-09-21T00:00:00.000000Z"
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
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki izin untuk melihat jalur masuk SPMB."
}
```

---

## [GET] /api/spmb/master/gelombang

> Mengambil daftar gelombang pendaftaran mahasiswa baru dengan fitur pencarian, filter kriteria, pengurutan whitelist, dan paginasi standar.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Default | Deskripsi |
|---|---|---|---|
| `search` | string | - | Pencarian kata kunci pada nama gelombang |
| `nama` | string | - | Filter spesifik berdasarkan nama gelombang |
| `jalur_masuk_id` | integer | - | Filter gelombang yang dipetakan ke jalur ini (salah satu jalurnya) |
| `umum` | boolean | - | `true` hanya menampilkan gelombang umum (tidak dipetakan ke jalur) |
| `tanggal_buka` | string | - | Filter periode tanggal buka minimal (format `MM-DD`) |
| `tanggal_tutup` | string | - | Filter periode tanggal tutup maksimal (format `MM-DD`) |
| `kuota` | string | - | Filter status kuota: `tersedia` atau `penuh` |
| `status` | string | - | Filter status (`draft`, `aktif`, `ditutup`, `selesai`) |
| `sort_by` | string | `created_at` | Kolom pengurutan (`id`, `nama`, `tanggal_buka`, `tanggal_tutup`, `kuota_total`, `status`, `created_at`) |
| `sort_order` | string | `desc` | Arah urutan (`asc`, `desc`) |
| `per_page` | integer | `15` | Jumlah data per halaman (1 s/d 100) |
| `page` | integer | `1` | Nomor halaman data |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data gelombang berhasil dimuat.",
    "data": [
        {
            "id": 1,
            "nama": "Gelombang 1 Reguler 2026/2027",
            "jalur_masuk_id": 1,
            "tanggal_buka": "01-01",
            "tanggal_tutup": "04-30",
            "tanggal_pengumuman": "2026-05-05",
            "kuota_total": 150,
            "kuota_terisi": 45,
            "potongan_biaya_daftar_ulang": 10.00,
            "status": "aktif",
            "pendaftaran_dibuka": true,
            "created_at": "2026-09-21T10:00:00.000000Z",
            "updated_at": "2026-09-21T10:00:00.000000Z",
            "jalur_masuk": {
                "id": 1,
                "nama": "Reguler",
                "kode": "REG"
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
        "nama": null,
        "jalur_masuk_id": null,
        "tanggal_buka": null,
        "tanggal_tutup": null,
        "kuota": null,
        "status": null,
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
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki akses ke resource SPMB ini."
}
```

---

## [GET] /api/spmb/gelombang/resolve

> Menentukan gelombang untuk jalur pendaftaran yang dipilih. Pilih jalur lebih dulu; bila ada gelombang aktif yang dipetakan ke jalur itu, gelombang tersebut dipakai. Bila tidak ada, pilihan berupa gelombang aktif yang tidak dipetakan ke jalur mana pun (gelombang umum). Diperbarui: 2026-11-26.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Wajib | Deskripsi |
|---|---|---|---|
| `jalur_masuk_id` | integer | ✅ | ID jalur pendaftaran yang dipilih |
| `konteks` | string | - | `manual` (default; hanya status `aktif`) atau `online` (status `aktif` dan sedang dalam periode buka) |

### Nilai `mode`

| mode | Arti |
|---|---|
| `otomatis` | Tepat satu gelombang aktif terpetakan ke jalur; `gelombang` terisi dan dipakai |
| `pilih` | Pengguna memilih dari `options`: beberapa gelombang terpetakan (`sumber: terpetakan`) atau gelombang umum (`sumber: umum`) |
| `tidak_tersedia` | Tidak ada gelombang yang dapat dipakai; `options` kosong |

### Response 200 — otomatis

```json
{
  "status": "success",
  "message": "Gelombang untuk jalur berhasil ditentukan.",
  "data": {
    "jalur_masuk_id": 1,
    "mode": "otomatis",
    "sumber": "terpetakan",
    "gelombang": {
      "id": 2,
      "jalur_masuk_id": 1,
      "nama": "Khusus A",
      "tanggal_buka": "01-01",
      "tanggal_tutup": "12-31",
      "kuota_total": 10,
      "kuota_terisi": 0,
      "status": "aktif",
      "pendaftaran_dibuka": true
    },
    "options": []
  }
}
```

### Response 200 — otomatis untuk gelombang yang dipetakan ke banyak jalur

Gelombang yang sama dikembalikan untuk setiap jalur pemetaannya; `jalur_masuk_ids` memuat seluruh jalur gelombang.

```json
{
  "status": "success",
  "message": "Gelombang untuk jalur berhasil ditentukan.",
  "data": {
    "jalur_masuk_id": 2,
    "mode": "otomatis",
    "sumber": "terpetakan",
    "gelombang": {
      "id": 1,
      "nama": "Gelombang 1",
      "tanggal_buka": "01-01",
      "tanggal_tutup": "03-31",
      "kuota_total": 100,
      "status": "aktif",
      "pendaftaran_dibuka": false,
      "jalur_masuk_ids": [
        1,
        2
      ],
      "daftar_jalur": [
        {
          "id": 1,
          "kode": "JA",
          "nama": "Jalur A"
        },
        {
          "id": 2,
          "kode": "JB",
          "nama": "Jalur B"
        }
      ]
    },
    "options": []
  }
}
```

### Response 200 — pilih (gelombang umum)

```json
{
  "status": "success",
  "message": "Gelombang untuk jalur berhasil ditentukan.",
  "data": {
    "jalur_masuk_id": 1,
    "mode": "pilih",
    "sumber": "umum",
    "gelombang": null,
    "options": [
      {
        "id": 1,
        "jalur_masuk_id": null,
        "nama": "Gelombang 1",
        "tanggal_buka": "01-01",
        "tanggal_tutup": "03-31",
        "kuota_total": 100,
        "kuota_terisi": 0,
        "status": "aktif",
        "pendaftaran_dibuka": false
      }
    ]
  }
}
```

### Response 200 — tidak tersedia

```json
{
  "status": "success",
  "message": "Gelombang untuk jalur berhasil ditentukan.",
  "data": {
    "jalur_masuk_id": 2,
    "mode": "tidak_tersedia",
    "sumber": "umum",
    "gelombang": null,
    "options": []
  }
}
```

### Response 401

```json
{
  "status": "error",
  "message": "Token tidak valid atau sesi telah berakhir."
}
```

### Response 422

```json
{
  "status": "error",
  "message": "Jalur pendaftaran wajib dipilih.",
  "errors": {
    "jalur_masuk_id": [
      "Jalur pendaftaran wajib dipilih."
    ]
  }
}
```

### Response 403

Hanya admin SPMB (`spmb.manage`) dan calon mahasiswa (role `calon_mhs`) yang dapat memakai endpoint ini.

```json
{
  "status": "error",
  "message": "This action is unauthorized."
}
```

### Response 404

Tidak berlaku: jalur yang tidak ditemukan menghasilkan 422 pada `jalur_masuk_id`.


## [GET] /api/spmb/master/gelombang/{id}

> Mengambil informasi detail satu gelombang pendaftaran berdasarkan ID.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data detail gelombang berhasil dimuat.",
    "data": {
        "id": 1,
        "nama": "Gelombang 1 Reguler 2026/2027",
        "jalur_masuk_id": 1,
        "tanggal_buka": "01-01",
        "tanggal_tutup": "04-30",
        "tanggal_pengumuman": "2026-05-05",
        "kuota_total": 150,
        "kuota_terisi": 45,
        "potongan_biaya_daftar_ulang": 10.00,
        "status": "aktif",
        "pendaftaran_dibuka": true,
        "created_at": "2026-09-21T10:00:00.000000Z",
        "updated_at": "2026-09-21T10:00:00.000000Z",
        "jalur_masuk": {
            "id": 1,
            "nama": "Reguler",
            "kode": "REG"
        }
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
    "message": "Anda tidak memiliki izin untuk melihat detail gelombang SPMB."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Gelombang penerimaan tidak ditemukan."
}
```

---

## [POST] /api/spmb/master/gelombang

> Membuat periode, kuota, dan status gelombang penerimaan. Satu gelombang dapat dipetakan ke banyak jalur lewat `jalur_masuk_ids` (pilihan ganda). Gelombang tanpa jalur adalah gelombang umum. Pada PUT, `jalur_masuk_ids` yang tidak dikirim membiarkan pemetaan; array kosong menjadikannya gelombang umum. Nominal biaya dikelola melalui Master Biaya dan Komponen Biaya SPMB. `status: aktif` mengaktifkan jadwal; backend menghitung `pendaftaran_dibuka` dari tanggal saat ini di zona waktu `SPMB_TIMEZONE` (default `Asia/Jakarta`).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "jalur_masuk_ids": "array<integer>, nullable, tiap id exists:spmb_jalur_masuk,id dan unik (kosong/tidak dikirim = gelombang umum untuk semua jalur; pilihan ganda)",
    "jalur_masuk_id": "integer, nullable (lama; dilipat ke jalur_masuk_ids)",
    "nama": "string, required",
    "tanggal_buka": "string, required (MM-DD; recurring annual period)",
    "tanggal_tutup": "string, required (MM-DD; recurring annual period)",
    "tanggal_pengumuman": "date, nullable",
    "kuota_total": "integer, required, min:1",
    "potongan_biaya_daftar_ulang": "numeric, nullable, min:0, max:100 (persen)",
    "status": "enum: draft|aktif|ditutup|selesai"
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Gelombang penerimaan berhasil dibuat.",
    "data": {
        "id": 2,
        "jalur_masuk_id": 1,
        "nama": "Gelombang 2 Reguler 2026/2027",
        "tanggal_buka": "05-01",
        "tanggal_tutup": "07-31",
        "tanggal_pengumuman": "2026-08-05",
        "kuota_total": 150,
        "kuota_terisi": 0,
        "potongan_biaya_daftar_ulang": "10.00",
        "status": "draft",
        "pendaftaran_dibuka": false,
        "created_at": "2026-10-01T10:00:00.000000Z",
        "updated_at": "2026-10-01T10:00:00.000000Z",
        "jalur_masuk": {
            "id": 1,
            "nama": "Reguler",
            "kode": "REG"
        }
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
{ "status": "error", "message": "Anda tidak memiliki akses ke resource SPMB ini." }
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "jalur_masuk_id": ["The selected jalur masuk id is invalid."],
        "tanggal_tutup": ["The tanggal tutup period is invalid."],
        "potongan_biaya_daftar_ulang": ["The potongan biaya daftar ulang must not be greater than 100."]
    }
}
```

---

### Contoh: gelombang umum (tanpa jalur)

Request tanpa `jalur_masuk_id` menghasilkan gelombang umum:

```json
{
  "status": "success",
  "message": "Gelombang penerimaan berhasil dibuat.",
  "data": {
    "nama": "Gelombang 1",
    "tanggal_buka": "01-01",
    "tanggal_tutup": "03-31",
    "kuota_total": 100,
    "status": "aktif",
    "id": 1,
    "pendaftaran_dibuka": false,
    "jalur_masuk": null,
    "jalur_masuk_id": null
  }
}
```

### Contoh: gelombang untuk banyak jalur

Request dengan `jalur_masuk_ids` memetakan gelombang ke beberapa jalur sekaligus:

```json
{
  "status": "success",
  "message": "Gelombang penerimaan berhasil dibuat.",
  "data": {
    "id": 1,
    "nama": "Gelombang 1",
    "tanggal_buka": "01-01",
    "tanggal_tutup": "03-31",
    "kuota_total": 100,
    "status": "aktif",
    "pendaftaran_dibuka": false,
    "jalur_masuk_ids": [
      1,
      2
    ],
    "daftar_jalur": [
      {
        "id": 1,
        "kode": "JA",
        "nama": "Jalur A"
      },
      {
        "id": 2,
        "kode": "JB",
        "nama": "Jalur B"
      }
    ]
  }
}
```

Id jalur yang tidak dikenal menghasilkan 422:

```json
{
  "status": "error",
  "message": "The selected jalur_masuk_ids.0 is invalid.",
  "errors": {
    "jalur_masuk_ids.0": [
      "The selected jalur_masuk_ids.0 is invalid."
    ]
  }
}
```


## [PUT] /api/spmb/master/gelombang/{id}

> Memperbarui data gelombang penerimaan. Body mengikuti aturan validasi yang sama dengan POST.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "jalur_masuk_ids": "array<integer>, nullable, tiap id exists:spmb_jalur_masuk,id dan unik (kosong/tidak dikirim = gelombang umum untuk semua jalur; pilihan ganda)",
    "jalur_masuk_id": "integer, nullable (lama; dilipat ke jalur_masuk_ids)",
    "nama": "string, required",
    "tanggal_buka": "string, required (MM-DD; recurring annual period)",
    "tanggal_tutup": "string, required (MM-DD; recurring annual period)",
    "tanggal_pengumuman": "date, nullable",
    "kuota_total": "integer, required, min:1",
    "potongan_biaya_daftar_ulang": "numeric, nullable, min:0, max:100 (persen)",
    "status": "enum: draft|aktif|ditutup|selesai"
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Gelombang penerimaan berhasil diperbarui.",
    "data": {
        "id": 2,
        "jalur_masuk_id": 1,
        "nama": "Gelombang 2 Reguler 2026/2027",
        "tanggal_buka": "05-01",
        "tanggal_tutup": "07-31",
        "tanggal_pengumuman": "2026-08-05",
        "kuota_total": 200,
        "kuota_terisi": 0,
        "potongan_biaya_daftar_ulang": "15.50",
        "status": "aktif",
        "pendaftaran_dibuka": true,
        "created_at": "2026-10-01T10:00:00.000000Z",
        "updated_at": "2026-10-01T10:05:00.000000Z",
        "jalur_masuk": {
            "id": 1,
            "nama": "Reguler",
            "kode": "REG"
        }
    }
}
```

### Response Error

**404 Not Found**
```json
{ "status": "error", "message": "No query results for model [App\\Models\\Spmb\\GelombangPenerimaan] 99." }
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "potongan_biaya_daftar_ulang": ["The potongan biaya daftar ulang must not be greater than 100."]
    }
}
```

---

## [DELETE] /api/spmb/master/gelombang/{id}

> Menghapus data gelombang penerimaan.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Gelombang penerimaan berhasil dihapus."
}
```

### Response Error

**404 Not Found**
```json
{ "status": "error", "message": "No query results for model [App\\Models\\Spmb\\GelombangPenerimaan] 99." }
```

---

## [GET] /api/spmb/biaya-pendaftaran

> Rincian komponen biaya untuk form pendaftaran online: komponen yang dibebankan sebagai **beban awal** dan komponen yang akan dibebankan saat **daftar ulang**. Endpoint bersifat publik (dipakai saat pengisian formulir).

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `jalur_masuk_id` | integer | ✅ | — | ID jalur masuk (`core_jalur_masuk`) |
| `program_studi_id` | integer | ✅ | — | ID program studi (`siakad_program_studi`) |
| `search` | string | ❌ | — | Tidak digunakan (endpoint non-paginasi) |
| `sort_by` | string | ❌ | `created_at` | Tidak digunakan (endpoint non-paginasi) |
| `sort_order` | string | ❌ | `desc` | Tidak digunakan (endpoint non-paginasi) |
| `per_page` | integer | ❌ | `15` | Tidak digunakan (endpoint non-paginasi) |
| `page` | integer | ❌ | `1` | Tidak digunakan (endpoint non-paginasi) |

> Catatan: endpoint ini **non-paginasi** (mengembalikan seluruh komponen beban untuk jalur masuk + prodi). Response menyertakan `"meta": null` dan `filters`.

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
    "message": "Rincian biaya pendaftaran berhasil dimuat.",
    "data": {
        "beban_pendaftaran": [
            { "komponen_biaya_id": 6, "kode": "REG-AWAL", "nama": "Biaya Pendaftaran", "kategori": "pendaftaran", "nominal": 250000 }
        ],
        "total_pendaftaran": 250000,
        "beban_daftar_ulang": [
            { "komponen_biaya_id": 8, "kode": "SERAGAM", "nama": "Paket Seragam", "kategori": "daftar_ulang", "nominal": 2750000 },
            { "komponen_biaya_id": 7, "kode": "DPI", "nama": "Dana Pengembangan Institusi", "kategori": "daftar_ulang", "nominal": 9000000 }
        ],
        "total_daftar_ulang": 11750000
    },
    "meta": null,
    "filters": { "jalur_masuk_id": 2, "program_studi_id": 7, "search": null, "sort_by": "created_at", "sort_order": "desc" }
}
```

### Response Error

**401 Unauthorized** (bila token dikirim tidak valid)
```json
{ "status": "error", "message": "Unauthenticated." }
```
**403 Forbidden** (bila endpoint dibatasi izin — pada endpoint publik ini tidak terpicu)
```json
{ "status": "error", "message": "This action is unauthorized." }
```

**404 Not Found**
```json
{ "status": "error", "message": "No query results for model [App\\Models\\Spmb\\JalurMasuk] 99." }
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "jalur_masuk_id": ["The selected jalur masuk id is invalid."],
        "program_studi_id": ["The selected program studi id is invalid."]
    }
}
```

---

## [GET] /api/spmb/master/options

> Mendapatkan seluruh opsi master SPMB secara ringkas (tahun akademik, jalur, gelombang) untuk dropdown formulir pendaftaran.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Opsi master SPMB berhasil dimuat.",
    "data": {
        "tahun_akademik": [
            {
                "id": 1,
                "nama": "2026/2027 Ganjil",
                "is_current": true
            }
        ],
        "jalur_masuk": [
            {
                "id": 1,
                "kode": "REG",
                "nama": "Reguler"
            }
        ],
        "gelombang": [
            {
                "id": 1,
                "nama": "Gelombang 1 Reguler 2026/2027",
                "status": "aktif"
            }
        ]
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
    "message": "Anda tidak memiliki akses ke opsi SPMB."
}
```

---

> **Catatan Keamanan:** Seluruh operasi referensi master SPMB dilindungi oleh autentikasi Sanctum dan otorisasi berbasis peran. Password dan kredensial sensitif pengguna tidak pernah disertakan dalam respons API. Penghapusan dan perubahan status mengikuti aturan integritas referensial dan soft delete pada data transaksional pendaftar aktif.

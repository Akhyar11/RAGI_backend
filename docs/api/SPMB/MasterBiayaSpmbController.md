# MasterBiayaSpmbController

> **Modul**: SPMB / **Base URL**: `/api/spmb/master` / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-10-01

Mengelola **Master Komponen Biaya** dan **Master Biaya SPMB** (per pasangan `master_tipe_jalur_id` + `program_studi_id`). Scoping memakai **Tipe Jalur Masuk** (`core_master_tipe_jalur`), bukan gelombang, sehingga satu konfigurasi biaya berlaku untuk semua gelombang pada tipe jalur tersebut. Setiap item biaya memiliki dua penanda:
- `dibebankan_saat_pendaftaran`: `true` → menjadi **beban awal** pada form pendaftaran online; `false` → dibebankan saat **daftar ulang**.
- `berlaku_diskon`: `true` → komponen ini **berhak atas diskon/potongan**; `false` → tidak.

Pada master komponen biaya terdapat penanda `is_default_master_biaya`: bila `true`, komponen tersebut **otomatis dimuat** saat menambah Master Biaya SPMB.

## Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ untuk POST/PUT |

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/spmb/master/komponen-biaya` | Daftar komponen biaya berpaginasi | ✅ |
| GET | `/api/spmb/master/komponen-biaya-role-options` | Opsi role aktif untuk pemetaan reward referral | ✅ |
| GET | `/api/spmb/master/komponen-biaya/{id}` | Detail komponen biaya | ✅ |
| POST | `/api/spmb/master/komponen-biaya` | Tambah komponen biaya | ✅ |
| PUT | `/api/spmb/master/komponen-biaya/{id}` | Perbarui komponen biaya | ✅ |
| DELETE | `/api/spmb/master/komponen-biaya/{id}` | Hapus komponen (soft delete) | ✅ |
| POST | `/api/spmb/master/komponen-biaya/{id}/restore` | Pulihkan komponen terhapus | ✅ |
| GET | `/api/spmb/master/biaya` | Daftar master biaya berpaginasi | ✅ |
| GET | `/api/spmb/master/biaya/{id}` | Detail master biaya | ✅ |
| POST | `/api/spmb/master/biaya` | Buat / idempoten master biaya | ✅ |
| PUT | `/api/spmb/master/biaya/{id}` | Perbarui master biaya | ✅ |
| DELETE | `/api/spmb/master/biaya/{id}` | Hapus master biaya (soft delete) | ✅ |
| POST | `/api/spmb/master/biaya/{id}/restore` | Pulihkan master biaya terhapus | ✅ |

> Semua endpoint memerlukan permission `spmb.manage` (lihat `Gate::before` di `AppServiceProvider`). Tanpa token → `401`, tanpa izin → `403`.

## Response Error Umum (berlaku untuk semua endpoint)

**401 Unauthorized**
```json
{ "status": "error", "message": "Unauthenticated." }
```
**403 Forbidden**
```json
{ "status": "error", "message": "This action is unauthorized." }
```
**404 Not Found** (saat `{id}` tidak ditemukan)
```json
{ "status": "error", "message": "No query results for model [App\\Models\\Spmb\\MasterBiaya] 99." }
```
**422 Unprocessable Entity** (saat payload tidak valid)
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": { "master_tipe_jalur_id": ["The master tipe jalur id field is required."] }
}
```

---

## [GET] /api/spmb/master/komponen-biaya

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari `nama` / `kode` |
| `kategori` | string | ❌ | — | `pendaftaran`, `daftar_ulang`, `perkuliahan`, `lainnya` |
| `is_active` | boolean | ❌ | — | Filter status aktif |
| `sort_by` | string | ❌ | `created_at` | `created_at`, `updated_at`, `urutan`, `nama`, `kode`, `id` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data komponen biaya berhasil dimuat.",
    "data": [
        {
            "id": 1,
            "kode": "REG-AWAL",
            "nama": "Biaya Pendaftaran",
            "kategori": "pendaftaran",
            "tipe_potongan": false,
            "is_default_master_biaya": true,
            "urutan": 1,
            "is_active": true,
            "keterangan": null
        }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1 },
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

---

## [GET] /api/spmb/master/komponen-biaya/{id}

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Detail komponen biaya berhasil dimuat.",
    "data": { "id": 1, "kode": "REG-AWAL", "nama": "Biaya Pendaftaran", "kategori": "pendaftaran", "is_active": true }
}
```

**404 Not Found**
```json
{ "status": "error", "message": "No query results for model [App\\Models\\Spmb\\MasterKomponenBiaya] 99." }
```

---

## [GET] /api/spmb/master/komponen-biaya-role-options

> Daftar role aktif (`id`, `slug`, `name`) untuk mengisi pemetaan `role_rewards` pada komponen biaya bertipe reward referral.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

Permission: `spmb.manage`.

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Opsi role berhasil dimuat.",
    "data": [
        { "id": 1, "slug": "mahasiswa", "name": "Mahasiswa" },
        { "id": 5, "slug": "admin_spmb", "name": "Admin SPMB" }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 2, "last_page": 1, "from": 1, "to": 2 },
    "filters": { "search": null, "sort_by": "name", "sort_order": "asc" }
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

---

## [POST] /api/spmb/master/komponen-biaya

### Request Body

```json
{
    "kode": "REG-AWAL",
    "nama": "Biaya Pendaftaran",
    "kategori": "pendaftaran",
    "tipe_potongan": false,
    "is_referral_reward": false,
    "is_default_master_biaya": true,
    "role_rewards": [
        { "role_id": 5, "nominal": 50000 },
        { "role_id": 6, "nominal": 25000 }
    ],
    "urutan": 1,
    "position_type": "end",
    "reference_id": null,
    "is_active": true,
    "keterangan": "Biaya formulir pendaftaran"
}
```

> **Reward Referral**: Jika komponen ini menjadi sumber reward referral, set `is_referral_reward = true` dan isi `role_rewards[]` (mapping `role_id` → `nominal`). Nominal reward referrer dihitung dari mapping role referrer (mis. Mahasiswa berbeda dari Dosen). Mapping disimpan di `spmb_komponen_biaya_role_reward`.

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Komponen biaya berhasil ditambahkan.",
    "data": { "id": 1, "kode": "REG-AWAL", "nama": "Biaya Pendaftaran", "kategori": "pendaftaran", "is_active": true }
}
```

### Response Error

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": { "nama": ["The nama field is required."] }
}
```

---

## [PUT] /api/spmb/master/komponen-biaya/{id}

### Request Body

Sama seperti POST (tanpa `kode` unik konflik, unik diabaikan untuk dirinya sendiri).

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Komponen biaya berhasil diperbarui.",
    "data": { "id": 1, "kode": "REG-AWAL", "nama": "Biaya Pendaftaran (Revisi)", "is_active": true }
}
```

---

## [DELETE] /api/spmb/master/komponen-biaya/{id}

> Menghapus komponen secara **soft delete** (`deleted_at` diisi). Data item biaya terkait ikut tidak tampil.

### Response Sukses

**200 OK**
```json
{ "status": "success", "message": "Komponen biaya berhasil dihapus (soft delete)." }
```

---

## [POST] /api/spmb/master/komponen-biaya/{id}/restore

> Memulihkan komponen yang ter-soft delete.

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Komponen biaya berhasil dipulihkan.",
    "data": { "id": 1, "kode": "REG-AWAL", "nama": "Biaya Pendaftaran", "deleted_at": null }
}
```

---

## [GET] /api/spmb/master/biaya

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari nama / kode prodi |
| `master_tipe_jalur_id` | integer | ❌ | — | Filter tipe jalur masuk (`core_master_tipe_jalur`) |
| `program_studi_id` | integer | ❌ | — | Filter program studi |
| `is_active` | boolean | ❌ | — | Filter status aktif |
| `sort_by` | string | ❌ | `created_at` | `created_at`, `updated_at`, `total_biaya`, `id` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data master biaya berhasil dimuat.",
    "data": [
        {
            "id": 1,
            "master_tipe_jalur_id": 2,
            "program_studi_id": 7,
            "total_biaya": "12000000.00",
            "is_active": true,
            "keterangan": null,
            "master_tipe_jalur": { "id": 2, "kode": "REGULER", "nama": "Reguler" },
            "program_studi": { "id": 7, "nama": "Teknik Informatika", "kode_prodi": "TI01", "jenjang": "S1" },
            "items": [
                { "id": 10, "komponen_biaya_id": 6, "nominal": "250000.00", "dibebankan_saat_pendaftaran": true, "berlaku_diskon": false, "komponen_biaya": { "kode": "REG-AWAL", "nama": "Biaya Pendaftaran" } },
                { "id": 11, "komponen_biaya_id": 8, "nominal": "2750000.00", "dibebankan_saat_pendaftaran": false, "berlaku_diskon": true, "komponen_biaya": { "kode": "SERAGAM", "nama": "Paket Seragam" } },
                { "id": 12, "komponen_biaya_id": 7, "nominal": "9000000.00", "dibebankan_saat_pendaftaran": false, "berlaku_diskon": true, "komponen_biaya": { "kode": "DPI", "nama": "Dana Pengembangan Institusi" } }
            ]
        }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1 },
    "filters": { "search": null, "sort_by": "created_at", "sort_order": "desc" }
}
```

---

## [GET] /api/spmb/master/biaya/{id}

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Detail master biaya berhasil dimuat.",
    "data": {
        "id": 1,
        "master_tipe_jalur_id": 2,
        "program_studi_id": 7,
        "total_biaya": "12000000.00",
        "is_active": true,
        "items": [
            { "komponen_biaya_id": 6, "nominal": "250000.00", "dibebankan_saat_pendaftaran": true, "berlaku_diskon": false }
        ]
    }
}
```

**404 Not Found**
```json
{ "status": "error", "message": "No query results for model [App\\Models\\Spmb\\MasterBiaya] 99." }
```

---

## [POST] /api/spmb/master/biaya

> Idempoten untuk pasangan `(master_tipe_jalur_id, program_studi_id)`.

### Request Body

```json
{
    "master_tipe_jalur_id": 2,
    "program_studi_id": 7,
    "is_active": true,
    "keterangan": "Paket biaya awal masuk",
    "items": [
        { "komponen_biaya_id": 6, "nominal": 250000, "dibebankan_saat_pendaftaran": true, "berlaku_diskon": false, "keterangan": "Formulir" },
        { "komponen_biaya_id": 8, "nominal": 2750000, "dibebankan_saat_pendaftaran": false, "berlaku_diskon": true },
        { "komponen_biaya_id": 7, "nominal": 9000000, "dibebankan_saat_pendaftaran": false, "berlaku_diskon": true }
    ]
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Master biaya program studi berhasil disimpan.",
    "data": {
        "id": 1,
        "master_tipe_jalur_id": 2,
        "program_studi_id": 7,
        "total_biaya": 12000000,
        "is_active": true,
        "items": [],
        "master_tipe_jalur": { "id": 2, "kode": "REGULER", "nama": "Reguler" },
        "program_studi": { "id": 7, "nama": "Teknik Informatika" }
    }
}
```

### Response Error

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": { "items.0.nominal": ["The items.0.nominal field is required."] }
}
```

---

## [PUT] /api/spmb/master/biaya/{id}

> **Sinkronisasi item**: daftar `items` yang dikirim menjadi acuan final. Item yang **tidak ada** di payload akan **dihapus** dari rincian (hanya komponen yang dipilih yang tersimpan), lalu `total_biaya` dihitung ulang.

### Request Body

```json
{
    "master_tipe_jalur_id": 2,
    "program_studi_id": 7,
    "is_active": true,
    "keterangan": "Diperbarui",
    "items": [
        { "komponen_biaya_id": 6, "nominal": 300000, "dibebankan_saat_pendaftaran": true, "berlaku_diskon": false }
    ]
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Master biaya program studi berhasil diperbarui.",
    "data": { "id": 1, "total_biaya": 12000000, "is_active": true }
}
```

---

## [DELETE] /api/spmb/master/biaya/{id}

> Soft delete (`deleted_at` diisi). Data masih dapat dipulihkan via endpoint restore.

### Response Sukses

**200 OK**
```json
{ "status": "success", "message": "Master biaya program studi berhasil dihapus (soft delete)." }
```

---

## [POST] /api/spmb/master/biaya/{id}/restore

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Master biaya program studi berhasil dipulihkan.",
    "data": { "id": 1, "deleted_at": null }
}
```

---

### Catatan Tambahan

> - Scoping biaya memakai **Tipe Jalur Masuk** (`core_master_tipe_jalur`), bukan gelombang. Satu konfigurasi berlaku lintas gelombang pada tipe jalur yang sama.
> - Menghapus tipe jalur akan membuat `master_tipe_jalur_id` pada konfigurasi biaya menjadi `NULL` (null on delete); data biaya tidak ikut terhapus.
> - Komponen bernilai `0` **tidak dihitung** sebagai komponen beban pada tagihan pendaftaran/daftar ulang.
> - `berlaku_diskon` menyimpan penanda apakah komponen berhak atas diskon/potongan; default `false`.
> - Password/token tidak pernah dikembalikan pada response.
> - Seluruh aksi tulis (create/update/delete/restore) dicatat oleh observer audit (`MasterBiaya`, `MasterBiayaItem`, `MasterKomponenBiaya`).

# AkademikController

> **Modul**: SIAKAD / **Base URL**: `/api/v1/siakad/akademik` / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-09-25

Master akademik SIAKAD: tahun akademik, fakultas, program studi, kurikulum, mata kuliah, dan dosen. Sumber program studi kini tabel `siakad_program_studi`.

## Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ untuk POST/PUT/PATCH |

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
| GET | `/api/v1/siakad/akademik/referensi-options?tipe={tipe}` | Opsi dropdown master akademik dari database | ✅ |
|---|---|---|---|
| GET | `/api/v1/siakad/akademik/dashboard/summary` | Ringkasan dashboard akademik | ✅ |
| GET | `/api/v1/siakad/akademik/tahun-akademik` | Daftar tahun akademik | ✅ |
| POST | `/api/v1/siakad/akademik/tahun-akademik` | Tambah tahun akademik | ✅ |
| PATCH | `/api/v1/siakad/akademik/tahun-akademik/{id}/set-active` | Aktifkan tahun akademik | ✅ |
| PATCH | `/api/v1/siakad/akademik/tahun-akademik/{id}/mode-penilaian` | Ubah mode penilaian | ✅ |
| GET | `/api/v1/siakad/akademik/skala-nilai` | Daftar skala nilai mutu | ✅ |
| POST | `/api/v1/siakad/akademik/skala-nilai` | Tambah skala nilai mutu | ✅ |
| PUT | `/api/v1/siakad/akademik/skala-nilai/{id}` | Perbarui skala nilai mutu | ✅ |
| DELETE | `/api/v1/siakad/akademik/skala-nilai/{id}` | Hapus skala nilai mutu | ✅ |
| GET | `/api/v1/siakad/akademik/fakultas` | Daftar fakultas | ✅ |
| POST | `/api/v1/siakad/akademik/fakultas` | Tambah fakultas | ✅ |
| PUT | `/api/v1/siakad/akademik/fakultas/{id}` | Perbarui fakultas | ✅ |
| DELETE | `/api/v1/siakad/akademik/fakultas/{id}` | Hapus fakultas | ✅ |
| GET | `/api/v1/siakad/akademik/prodi` | Daftar program studi | ⚠️ Kondisional |
| POST | `/api/v1/siakad/akademik/prodi` | Tambah program studi | ✅ |
| PUT | `/api/v1/siakad/akademik/prodi/{id}` | Perbarui program studi | ✅ |
| DELETE | `/api/v1/siakad/akademik/prodi/{id}` | Hapus program studi | ✅ |
| GET | `/api/v1/siakad/akademik/kurikulum` | Daftar kurikulum | ✅ |
| POST | `/api/v1/siakad/akademik/kurikulum` | Tambah kurikulum | ✅ |
| PUT | `/api/v1/siakad/akademik/kurikulum/{id}` | Perbarui kurikulum | ✅ |
| DELETE | `/api/v1/siakad/akademik/kurikulum/{id}` | Hapus kurikulum | ✅ |
| GET | `/api/v1/siakad/akademik/matakuliah` | Daftar mata kuliah | ✅ |
| POST | `/api/v1/siakad/akademik/matakuliah` | Tambah mata kuliah | ✅ |
| PUT | `/api/v1/siakad/akademik/matakuliah/{id}` | Perbarui mata kuliah | ✅ |
| DELETE | `/api/v1/siakad/akademik/matakuliah/{id}` | Hapus mata kuliah | ✅ |
| GET | `/api/v1/siakad/akademik/dosen` | Daftar dosen | ✅ |
| POST | `/api/v1/siakad/akademik/dosen` | Tambah dosen | ✅ |
| PUT | `/api/v1/siakad/akademik/dosen/{id}` | Perbarui dosen | ✅ |
| DELETE | `/api/v1/siakad/akademik/dosen/{id}` | Hapus dosen | ✅ |

---

## [GET] /api/v1/siakad/akademik/tahun-akademik

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari `kode` / `nama` |
| `sort_by` | string | ❌ | `created_at` | `created_at`, `updated_at`, `kode`, `nama`, `id` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": [
        { "id": 1, "kode": "20261", "nama": "2026/2027 Ganjil", "tahun_mulai": 2026, "tahun_selesai": 2027, "is_active": true, "mode_penilaian": "semi_obe" }
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

## [POST] /api/v1/siakad/akademik/tahun-akademik

> Membutuhkan permission `siakad.master.manage`. Bila `is_active = true`, periode lain otomatis dinonaktifkan dalam satu transaksi.

### Request Body

```json
{
    "kode": "20261",
    "nama": "2026/2027 Ganjil",
    "tahun_mulai": 2026,
    "tahun_selesai": 2027,
    "is_active": true,
    "mode_penilaian": "semi_obe"
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Periode Tahun Akademik berhasil ditambahkan",
    "data": { "id": 1, "kode": "20261", "nama": "2026/2027 Ganjil", "is_active": true }
}
```

### Response Error

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": { "kode": ["The kode has already been taken."] }
}
```

---

## [PATCH] /api/v1/siakad/akademik/tahun-akademik/{id}/set-active

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Tahun Akademik 2026/2027 Ganjil (20261) berhasil diaktifkan sebagai periode semester berjalan.",
    "data": { "id": 1, "is_active": true }
}
```

**404 Not Found**
```json
{ "status": "error", "message": "No query results for model [App\\Models\\Siakad\\TahunAkademik] 99." }
```

---

## [PATCH] /api/v1/siakad/akademik/tahun-akademik/{id}/mode-penilaian

### Request Body

```json
{ "mode_penilaian": "full_obe" }
```

### Response Sukses

**200 OK**
```json
{ "status": "success", "message": "Mode penilaian periode akademik berhasil diperbarui." }
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
{ "status": "error", "message": "No query results for model [App\\Models\\Siakad\\TahunAkademik] 99." }
```
**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": { "mode_penilaian": ["The selected mode penilaian is invalid."] }
}
```

---

## [GET] /api/v1/siakad/akademik/skala-nilai

Mengambil daftar skala nilai mutu akademik (opsional paginated).

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari `nilai_huruf`, `keterangan`, atau nama/kode prodi |
| `program_studi_id` | integer | ❌ | — | Filter berdasarkan ID program studi |
| `is_lulus` | boolean | ❌ | — | Filter status kelulusan |
| `sort_by` | string | ❌ | `bobot_indeks` | `nilai_huruf`, `bobot_indeks`, `batas_bawah`, `batas_atas`, `is_lulus`, `id`, `created_at` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Nomor halaman |

### Response Sukses (200 OK - Paginated)
```json
{
    "status": "success",
    "message": "Data skala nilai berhasil dimuat",
    "data": [
        {
            "id": 1,
            "program_studi_id": null,
            "nilai_huruf": "A",
            "bobot_indeks": "4.00",
            "batas_bawah": "85.00",
            "batas_atas": "100.00",
            "is_lulus": true,
            "keterangan": "Sangat Baik",
            "is_active": true,
            "program_studi": null
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1,
        "from": 1,
        "to": 1
    }
}
```

---

## [POST] /api/v1/siakad/akademik/skala-nilai

Tambah skala nilai mutu baru.

### Request Body
```json
{
    "program_studi_id": null,
    "nilai_huruf": "A",
    "bobot_indeks": 4.0,
    "batas_bawah": 85.0,
    "batas_atas": 100.0,
    "is_lulus": true,
    "keterangan": "Sangat Baik",
    "is_active": true
}
```

### Response Sukses (201 Created)
```json
{
    "status": "success",
    "message": "Skala nilai mutu berhasil ditambahkan",
    "data": {
        "id": 1,
        "program_studi_id": null,
        "nilai_huruf": "A",
        "bobot_indeks": "4.00",
        "batas_bawah": "85.00",
        "batas_atas": "100.00",
        "is_lulus": true,
        "keterangan": "Sangat Baik",
        "is_active": true,
        "program_studi": null
    }
}
```

---

## [PUT] /api/v1/siakad/akademik/skala-nilai/{id}

> Memperbarui skala nilai mutu. Wajib permission `siakad.master.manage`.

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID skala nilai mutu |

### Request Body

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `program_studi_id` | integer | ❌ | `exists:siakad_program_studi,id` (null = global) |
| `nilai_huruf` | string | ✅ | Maks. 5 (A, A-, B+, dst) |
| `bobot_indeks` | number | ✅ | 0 - 4.00 |
| `batas_bawah` | number | ✅ | 0 - 100 |
| `batas_atas` | number | ✅ | 0 - 100 |
| `is_lulus` | boolean | ❌ | Default true |
| `keterangan` | string | ❌ | Deskripsi predikat |
| `is_active` | boolean | ❌ | Default true |

```json
{
    "nilai_huruf": "A",
    "bobot_indeks": 4.0,
    "batas_bawah": 85.0,
    "batas_atas": 100.0,
    "is_lulus": true,
    "keterangan": "Sangat Baik",
    "is_active": true
}
```

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Skala nilai mutu berhasil diperbarui",
    "data": {
        "id": 1,
        "nilai_huruf": "A",
        "bobot_indeks": "4.00",
        "batas_bawah": "85.00",
        "batas_atas": "100.00",
        "is_lulus": true,
        "keterangan": "Sangat Baik",
        "is_active": true
    }
}
```

### Response Error

**401 Unauthorized** / **403 Forbidden** / **404 Not Found** / **422 Unprocessable Entity**.

---

## [DELETE] /api/v1/siakad/akademik/skala-nilai/{id}

> Menghapus skala nilai mutu. Wajib permission `siakad.master.manage`.

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID skala nilai mutu |

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Skala nilai mutu berhasil dihapus",
    "data": null
}
```

### Response Error

**401 Unauthorized** / **403 Forbidden** / **404 Not Found**.

---

## [GET] /api/v1/siakad/akademik/prodi

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari `nama` / `kode_prodi` |
| `fakultas_id` | integer | ❌ | — | Filter fakultas |
| `sort_by` | string | ❌ | `created_at` | `created_at`, `updated_at`, `nama`, `kode_prodi`, `id` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": [
        { "id": 7, "kode_prodi": "TI01", "prefix_nim": "A", "nama": "Teknik Informatika", "jenjang": "S1", "is_active": true }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1 }
}
```

### Prefix NIM per Prodi

> Atur via `POST /prodi` / `PUT /prodi/{id}` dengan field `prefix_nim` (nullable, huruf/angka maks. 10, unik, cth `"A"`).
> Jika terisi, generate NIM otomatis memakai `{PREFIX}{YY}{3-digit urut}` (cth `A25001`); jika kosong memakai format standar.

---

## [GET] /api/v1/siakad/akademik/kurikulum

Mengambil daftar kurikulum (tahun kurikulum OBE). Tanpa parameter paginasi, mengembalikan seluruh data aktif; dengan `page`/`per_page`/`limit`, mengembalikan envelope paginasi standar (`data` + `meta`).

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari `nama` / `kode` |
| `program_studi_id` | integer | ❌ | — | Filter ID program studi |
| `status` | string | ❌ | aktif saja | `aktif` / `nonaktif` (tanpa parameter ini hanya kurikulum aktif yang dikembalikan) |
| `is_active` | boolean | ❌ | — | Alternatif filter status (`true` / `false`) |
| `sort_by` | string | ❌ | `tahun_berlaku` | `kode`, `nama`, `tahun_berlaku`, `total_sks_lulus`, `created_at`; nilai di luar daftar itu diabaikan dan fallback ke `tahun_berlaku` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 (alias: `limit`) |
| `page` | integer | ❌ | `1` | Halaman |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "data": [
        { "id": 1, "program_studi_id": 1, "kode": "KUR-2026-TI", "nama": "Kurikulum OBE Berbasis MBKM 2026", "tahun_berlaku": 2026, "total_sks_lulus": 144, "is_active": true }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1 }
}
```

### Catatan Tambahan

> - `DELETE /kurikulum/{id}` ditolak (422) bila kurikulum masih memiliki mata kuliah.
> - Relasi `programStudi` dan `mataKuliahs` ikut dimuat (`with`) pada setiap item.

---

## [GET] /api/v1/siakad/akademik/matakuliah

Mengambil daftar mata kuliah kurikulum (paginated).

> **Scope prodi (2026-10-08):** user scoped (non-admin tanpa role `admin`/`admin_siakad`) yang memiliki mapping prodi hanya melihat MK dari kurikulum prodinya (`kurikulum.program_studi_id ∈ getSiakadProdiIds()`), cermin `listKurikulum`.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari kode (`kode_mk`) atau nama mata kuliah |
| `kurikulum_id` | integer | ❌ | — | Filter ID kurikulum |
| `program_studi_id` | integer | ❌ | — | Filter ID program studi (melalui `kurikulum.program_studi_id`) |
| `tipe` | string | ❌ | — | Filter tipe mata kuliah |
| `angkatan` | integer | ❌ | — | Filter tahun berlaku kurikulum (`tahun_berlaku <= angkatan`, kurikulum terbaru bila tidak ada yang sama persis) |
| `sort_by` | string | ❌ | `nama` | Kolom pengurutan (`nama`, `kode_mk`, `total_sks`, `semester_anjuran`, `tipe`, `created_at`); nilai di luar daftar itu diabaikan dan fallback ke `nama` |
| `sort_order` | string | ❌ | `asc` | Arah pengurutan (`asc`, `desc`) |
| `per_page` | integer | ❌ | 20 | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | 1 | Nomor halaman |

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Daftar mata kuliah berhasil diambil.",
    "data": [
        {
            "id": 1,
            "kurikulum_id": 1,
            "kode_mk": "IF101",
            "nama": "Algoritma & Pemrograman I",
            "sks_teori": 2,
            "sks_praktik": 1,
            "total_sks": 3,
            "semester_anjuran": 1,
            "tipe": "wajib_prodi",
            "is_active": true,
            "kurikulum": {
                "id": 1,
                "program_studi_id": 1,
                "kode": "KUR-2024-IF",
                "tahun_berlaku": 2024
            },
            "prasyarats": []
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 20,
        "total": 8,
        "last_page": 1,
        "from": 1,
        "to": 1
    }
}
```

### Response Error

**403 Forbidden** — caller tidak memegang `siakad.kurikulum.read`.
```json
{
    "message": "This action is unauthorized."
}
```

---

### Catatan Tambahan

> - Seluruh aksi tulis pada `TahunAkademik` dan `ProgramStudi` dicatat oleh observer audit (modul `SIAKAD`).
> - Soft delete + restore berlaku pada tahun akademik dan program studi.
> - Password/token tidak pernah dikembalikan pada response.

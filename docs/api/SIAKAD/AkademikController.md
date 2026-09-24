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
        { "id": 7, "kode_prodi": "TI01", "nama": "Teknik Informatika", "jenjang": "S1", "is_active": true }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1 }
}
```

---

### Catatan Tambahan

> - Seluruh aksi tulis pada `TahunAkademik` dan `ProgramStudi` dicatat oleh observer audit (modul `SIAKAD`).
> - Soft delete + restore berlaku pada tahun akademik dan program studi.
> - Password/token tidak pernah dikembalikan pada response.

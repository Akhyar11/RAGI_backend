# ObeController

> **Modul**: SIAKAD / **Base URL**: `/api/v1/siakad/obe` / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-09-25

Kurikulum berbasis capaian (OBE): CPL/CPMK, Profil Lulusan, Bahan Kajian, RPS, komponen & nilai OBE. Validasi `program_studi_id` mengacu ke `siakad_program_studi`.

## Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ untuk POST/PATCH |

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/siakad/obe/dashboard` | Ringkasan OBE | ✅ |
| GET | `/api/v1/siakad/obe/cpl` | Daftar CPL | ✅ |
| POST | `/api/v1/siakad/obe/cpl` | Tambah CPL | ✅ |
| GET | `/api/v1/siakad/obe/cpmk` | Daftar CPMK | ✅ |
| POST | `/api/v1/siakad/obe/cpmk` | Tambah CPMK | ✅ |
| GET | `/api/v1/siakad/obe/profil-lulusan` | Daftar Profil Lulusan | ✅ |
| POST | `/api/v1/siakad/obe/profil-lulusan` | Tambah Profil Lulusan | ✅ |
| DELETE | `/api/v1/siakad/obe/profil-lulusan/{id}` | Hapus Profil Lulusan | ✅ |
| POST | `/api/v1/siakad/obe/profil-lulusan/cpl` | Pemetaan Profil Lulusan ↔ CPL | ✅ |
| GET | `/api/v1/siakad/obe/bahan-kajian` | Daftar Bahan Kajian | ✅ |
| POST | `/api/v1/siakad/obe/bahan-kajian` | Tambah Bahan Kajian | ✅ |
| DELETE | `/api/v1/siakad/obe/bahan-kajian/{id}` | Hapus Bahan Kajian | ✅ |
| POST | `/api/v1/siakad/obe/matakuliah/bahan-kajian` | Pemetaan Mata Kuliah ↔ Bahan Kajian | ✅ |
| GET | `/api/v1/siakad/obe/rps` | Daftar RPS | ✅ |
| GET | `/api/v1/siakad/obe/rps/{id}` | Detail RPS | ✅ |
| POST | `/api/v1/siakad/obe/rps` | Simpan RPS | ✅ |
| POST | `/api/v1/siakad/obe/rps/{id}/submit` | Ajukan RPS | ✅ |
| PATCH | `/api/v1/siakad/obe/rps/{id}/approve` | Setujui RPS | ✅ |
| GET | `/api/v1/siakad/obe/kelas/{kelasId}/komponen` | Komponen nilai kelas | ✅ |
| POST | `/api/v1/siakad/obe/kelas/{kelasId}/komponen` | Simpan komponen nilai | ✅ |
| DELETE | `/api/v1/siakad/obe/komponen/{id}` | Hapus komponen nilai | ✅ |
| GET | `/api/v1/siakad/obe/kelas/{kelasId}/nilai` | Nilai OBE kelas | ✅ |
| POST | `/api/v1/siakad/obe/kelas/{kelasId}/nilai` | Simpan nilai OBE | ✅ |
| POST | `/api/v1/siakad/obe/kelas/{kelasId}/bulk-nilai` | Simpan nilai massal | ✅ |
| GET | `/api/v1/siakad/obe/mahasiswa/portofolio` | Portofolio OBE mahasiswa | ✅ |
| GET | `/api/v1/siakad/obe/mahasiswa/{mahasiswaId}/portofolio` | Portofolio OBE per mahasiswa | ✅ |

## Query Parameters (berlaku untuk endpoint list)

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari kode/nama |
| `program_studi_id` | integer | ❌ | — | Filter program studi |
| `sort_by` | string | ❌ | `created_at` | `created_at`, `updated_at`, `nama`, `id` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

---

## [GET] /api/v1/siakad/obe/profil-lulusan

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": [
        { "id": 1, "program_studi_id": 7, "kode": "PL-1", "deskripsi": "Lulusan mampu merancang sistem informasi" }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1 },
    "filters": { "search": null, "program_studi_id": null, "sort_by": "created_at", "sort_order": "desc" }
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
    "errors": { "program_studi_id": ["The selected program studi id is invalid."] }
}
```

---

## [POST] /api/v1/siakad/obe/profil-lulusan

### Request Body

```json
{
    "program_studi_id": 7,
    "kode": "PL-1",
    "deskripsi": "Lulusan mampu merancang sistem informasi"
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Profil Lulusan berhasil disimpan.",
    "data": { "id": 1, "program_studi_id": 7, "kode": "PL-1" }
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
    "errors": { "program_studi_id": ["The selected program studi id is invalid."] }
}
```

---

## [POST] /api/v1/siakad/obe/bahan-kajian

### Request Body

```json
{
    "program_studi_id": 7,
    "kode": "BK-1",
    "nama": "Rekayasa Perangkat Lunak"
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Bahan Kajian berhasil disimpan.",
    "data": { "id": 3, "program_studi_id": 7, "kode": "BK-1" }
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
    "errors": { "program_studi_id": ["The selected program studi id is invalid."] }
}
```

---

## [GET] /api/v1/siakad/obe/kelas/{kelasId}/nilai

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": [
        { "mahasiswa_id": 1, "nim": "20260001", "nama_lengkap": "Budi Santoso", "nilai_akhir": 85.5, "grade": "A" }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1 }
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
{ "status": "error", "message": "Kelas tidak ditemukan." }
```

---

### Catatan Tambahan

> - Validasi `program_studi_id` mengacu ke `siakad_program_studi` (bukan tabel SPMB).
> - Soft delete + restore berlaku pada entitas OBE (Profil Lulusan, Bahan Kajian, RPS).
> - Password/token tidak pernah dikembalikan pada response.

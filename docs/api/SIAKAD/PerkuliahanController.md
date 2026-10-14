# PerkuliahanController

> **Modul**: SIAKAD / **Base URL**: `/api/v1/siakad/perkuliahan` / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-09-25

Kelas, KRS, nilai, transkrip, pertemuan & absensi. Validasi `program_studi_id` mengacu ke `siakad_program_studi` dan `tahun_akademik_id` ke `siakad_tahun_akademik`.

## Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ untuk POST/PUT/PATCH/DELETE |

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/siakad/perkuliahan/ref/ruangan` | Referensi ruangan (SINAPRA) | ✅ |
| GET | `/api/v1/siakad/perkuliahan/kelas` | Daftar kelas | ✅ |
| POST | `/api/v1/siakad/perkuliahan/kelas` | Tambah kelas | ✅ |
| PUT | `/api/v1/siakad/perkuliahan/kelas/{id}` | Perbarui kelas | ✅ |
| DELETE | `/api/v1/siakad/perkuliahan/kelas/{id}` | Hapus kelas | ✅ |
| GET | `/api/v1/siakad/perkuliahan/krs` | Daftar KRS | ✅ |
| GET | `/api/v1/siakad/perkuliahan/krs/active` | KRS aktif | ✅ |
| GET | `/api/v1/siakad/perkuliahan/krs/available-classes` | Kelas tersedia | ✅ |
| POST | `/api/v1/siakad/perkuliahan/krs/add-class` | Tambah kelas ke KRS | ✅ |
| DELETE | `/api/v1/siakad/perkuliahan/krs/drop-class/{detailId}` | Drop kelas dari KRS | ✅ |
| POST | `/api/v1/siakad/perkuliahan/krs/submit` | Submit KRS | ✅ |
| POST | `/api/v1/siakad/perkuliahan/krs/reopen` | Buka kembali KRS | ✅ |
| POST | `/api/v1/siakad/perkuliahan/krs/bulk-approve` | Setujui KRS massal | ✅ |
| PATCH | `/api/v1/siakad/perkuliahan/krs/{id}/approve` | Setujui KRS | ✅ |
| GET | `/api/v1/siakad/perkuliahan/nilai` | Daftar nilai | ✅ |
| PUT | `/api/v1/siakad/perkuliahan/nilai/{id}` | Perbarui nilai | ✅ |
| GET | `/api/v1/siakad/perkuliahan/transkrip` | Transkrip mahasiswa | ✅ |
| GET | `/api/v1/siakad/perkuliahan/kelas/{kelasId}/pertemuan` | Daftar pertemuan | ✅ |
| POST | `/api/v1/siakad/perkuliahan/kelas/{kelasId}/pertemuan` | Tambah pertemuan | ✅ |
| GET | `/api/v1/siakad/perkuliahan/pertemuan/{pertemuanId}/absensi` | Daftar absensi | ✅ |
| POST | `/api/v1/siakad/perkuliahan/pertemuan/{pertemuanId}/absensi` | Simpan absensi | ✅ |

## Query Parameters (berlaku untuk endpoint list)

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari nama kelas / mata kuliah / mahasiswa |
| `program_studi_id` | integer | ❌ | — | Filter program studi |
| `tahun_akademik_id` | integer | ❌ | tahun akademik aktif | Filter periode (`siakad_tahun_akademik`) |
| `tipe_sesi` | string | ❌ | — | `gabungan` / `teori` / `praktik` |
| `parent_kelas_id` | integer | ❌ | — | Filter anak dari induk tertentu |
| `rombel_id` | integer | ❌ | — | Filter kelas yang menempel rombel (`siakad_master_kelas`) |
| `sort_by` | string | ❌ | `created_at` | `created_at`, `updated_at`, `nama`, `id` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

---

## [GET] /api/v1/siakad/perkuliahan/kelas

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": [
        {
            "id": 5, "nama_kelas": "TI-1A", "mata_kuliah_id": 12, "program_studi_id": 7,
            "tahun_akademik_id": 1, "kapasitas": 40, "tipe_sesi": "teori",
            "parent_kelas_id": null,
            "parent": null,
            "children": [{ "id": 6, "kode_kelas": "IF101-P-A", "tipe_sesi": "praktik" }],
            "rombels": [{ "id": 4, "nama_kelas": "24A" }]
        }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1 },
    "filters": { "search": null, "program_studi_id": null, "tahun_akademik_id": null, "sort_by": "created_at", "sort_order": "desc" }
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

## [POST] /api/v1/siakad/perkuliahan/kelas

### Request Body

```json
{
    "mata_kuliah_id": 12,
    "program_studi_id": 7,
    "tahun_akademik_id": 1,
    "dosen_id": 3,
    "nama_kelas": "TI-1A",
    "kapasitas": 40,
    "tipe_sesi": "gabungan | teori | praktik",
    "parent_kelas_id": "integer, nullable, exists:siakad_kelas,id — wajib untuk tipe praktik (1 level, satu periode dengan induk; MK boleh beda)",
    "rombel_ids": "array, nullable — ID siakad_master_kelas peserta (cth. 24A/24B); praktik cukup 1 rombel, teori gabungan bisa banyak"
}
```

> **Aturan induk-anak (teori digabung, praktik per rombel):** kelas `teori` sebagai induk gabungan (1 dosen, 1 ruang besar, rombel 24A+24B); kelas `praktik` sebagai anak per rombel (ruang lab, dosen, jam beda, `parent_kelas_id` ke induk). Teori dan praktik masing-masing MK sendiri (boleh beda `mata_kuliah_id`), syarat hanya satu periode; nilai & absensi mengikuti model OBE per kelas masing-masing (waktu bisa berbeda). KRS: bila teori+praktik satu MK yang sama, diizinkan maksimal 1 induk + 1 anak per semester; dua praktik saudara dari induk yang sama ditolak.

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Kelas berhasil ditambahkan.",
    "data": { "id": 5, "nama_kelas": "TI-1A", "program_studi_id": 7, "tahun_akademik_id": 1 }
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
    "errors": { "tahun_akademik_id": ["The selected tahun akademik id is invalid."] }
}
```

---

## [GET] /api/v1/siakad/perkuliahan/krs

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari mahasiswa / NIM |
| `tahun_akademik_id` | integer | ❌ | tahun akademik aktif | Filter periode |
| `status` | string | ❌ | — | `draft`, `diajukan`, `disetujui` |
| `sort_by` | string | ❌ | `created_at` | `created_at`, `updated_at`, `id` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": [
        { "id": 9, "mahasiswa_id": 1, "tahun_akademik_id": 1, "total_sks": 20, "status": "draft" }
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
**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": { "tahun_akademik_id": ["The selected tahun akademik id is invalid."] }
}
```

---

## [GET] /api/v1/siakad/perkuliahan/krs/active

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": {
        "id": 9,
        "mahasiswa_id": 1,
        "tahun_akademik_id": 1,
        "total_sks": 20,
        "status": "draft",
        "details": []
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
{ "status": "error", "message": "KRS aktif tidak ditemukan." }
```

---

## [PUT] /api/v1/siakad/perkuliahan/nilai/{id}

### Request Body

```json
{ "nilai_akhir": 85.5, "grade": "A" }
```

### Response Sukses

**200 OK**
```json
{ "status": "success", "message": "Nilai berhasil diperbarui." }
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
{ "status": "error", "message": "Data nilai tidak ditemukan." }
```
**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": { "nilai_akhir": ["The nilai akhir must be between 0 and 100."] }
}
```

---

## [GET] /api/v1/siakad/perkuliahan/transkrip

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `mahasiswa_id` | integer | ❌ | dari user login | Filter mahasiswa |
| `sort_by` | string | ❌ | `created_at` | `created_at`, `id` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": [
        { "mata_kuliah": "Algoritma", "sks": 3, "nilai_akhir": 85.5, "grade": "A" }
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

---

### Catatan Tambahan

> - Soft delete + restore berlaku pada KRS dan kelas (data transaksional akademik).
> - Aksi tulis nilai/KRS/kelas tercatat pada audit log SIAKAD.
> - Password/token tidak pernah dikembalikan pada response.

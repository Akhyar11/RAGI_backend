# ObeController

> **Modul**: SIAKAD / **Base URL**: `/api/v1/siakad/obe` / **Autentikasi**: Bearer Token (Passport) / **Dibuat/Diperbarui**: 2026-10-09

Kurikulum berbasis capaian (OBE): CPL/CPMK, Profil Lulusan, Bahan Kajian, RPS, Referensi RPS (Bentuk, Metode, Kriteria, Komponen), komponen & nilai OBE. Seluruh data grup RPS terisolasi per Program Studi (`program_studi_id`).

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
| GET | `/api/v1/siakad/obe/cpl` | Daftar CPL (search, filter prodi/kurikulum/kategori/jenis/status, sort whitelist, pagination) | ✅ |
| POST | `/api/v1/siakad/obe/cpl` | Tambah CPL | ✅ `StoreCplRequest::authorize()` (per prodi aktif) |
| PUT | `/api/v1/siakad/obe/cpl/{id}` | Perbarui CPL | ✅ `StoreCplRequest::authorize()` (per prodi aktif) |
| DELETE | `/api/v1/siakad/obe/cpl/{id}` | Hapus CPL (soft delete) | ✅ `canManageObeForProdi()` |
| GET | `/api/v1/siakad/obe/cpmk` | Daftar CPMK | ✅ |
| POST | `/api/v1/siakad/obe/cpmk` | Tambah CPMK | ✅ |
| GET | `/api/v1/siakad/obe/profil-lulusan` | Daftar Profil Lulusan | ✅ |
| POST | `/api/v1/siakad/obe/profil-lulusan` | Tambah Profil Lulusan | ✅ |
| DELETE | `/api/v1/siakad/obe/profil-lulusan/{id}` | Hapus Profil Lulusan | ✅ |
| POST | `/api/v1/siakad/obe/profil-lulusan/cpl` | Pemetaan Profil Lulusan ↔ CPL | ✅ |
| GET | `/api/v1/siakad/obe/bahan-kajian` | Daftar Bahan Kajian (filter prodi/kurikulum/koordinator/search, sort whitelist, pagination) | ✅ `siakad.kurikulum.read` |
| POST | `/api/v1/siakad/obe/bahan-kajian` | Simpan Bahan Kajian (create/update via kode+prodi) | ✅ `siakad.kurikulum.manage` |
| PUT | `/api/v1/siakad/obe/bahan-kajian/{id}` | Perbarui Bahan Kajian | ✅ `siakad.kurikulum.manage` |
| DELETE | `/api/v1/siakad/obe/bahan-kajian/{id}` | Hapus Bahan Kajian (soft delete + lepas pivot CPL/MK) | ✅ `siakad.kurikulum.manage` |
| GET | `/api/v1/siakad/obe/bahan-kajian/matrix/cpl` | Matriks pemetaan CPL ↔ BK | ✅ `siakad.kurikulum.read` |
| POST | `/api/v1/siakad/obe/cpl/bahan-kajian` | Simpan pemetaan satu CPL → daftar BK (checkbox) | ✅ `siakad.kurikulum.manage` |
| GET | `/api/v1/siakad/obe/bahan-kajian/matrix/mata-kuliah` | Matriks pemetaan BK ↔ MK | ✅ `siakad.kurikulum.read` |
| POST | `/api/v1/siakad/obe/bahan-kajian/mata-kuliah` | Simpan pemetaan satu BK → daftar MK (checkbox) | ✅ `siakad.kurikulum.manage` |
| POST | `/api/v1/siakad/obe/matakuliah/bahan-kajian` | Pemetaan Mata Kuliah ↔ Bahan Kajian (payload `mata_kuliah_id` + `bahan_kajian_ids`) | ✅ |
| GET | `/api/v1/siakad/obe/matrix/cpl-mata-kuliah` | Matriks Pemetaan CPL-MK + status kelayakan sel (`eligible` / `pairs` / `yatim`) | ✅ `siakad.kurikulum.read` |
| POST | `/api/v1/siakad/obe/cpl/mata-kuliah` | Simpan / lepas satu sel CPL-MK (hanya jika ada jalur CPL → BK → MK) | ✅ `siakad.kurikulum.manage` |
| GET | `/api/v1/siakad/obe/matrix/cpl-bahan-kajian-mata-kuliah` | Laporan read-only Pemetaan CPL-BK-MK (baris BK × kolom CPL, isi sel = daftar MK) | ✅ `siakad.kurikulum.read` |
| GET | `/api/v1/siakad/obe/cpmk-prodi` | Daftar Rumusan CPMK Program Studi (CPMK-PS) | ✅ `siakad.kurikulum.read` |
| POST | `/api/v1/siakad/obe/cpmk-prodi` | Tambah Rumusan CPMK Program Studi | ✅ `StoreCpmkProdiRequest::authorize()` |
| PUT | `/api/v1/siakad/obe/cpmk-prodi/{id}` | Perbarui Rumusan CPMK Program Studi | ✅ `StoreCpmkProdiRequest::authorize()` |
| DELETE | `/api/v1/siakad/obe/cpmk-prodi/{id}` | Hapus Rumusan CPMK Program Studi (soft delete) | ✅ `canManageObeForProdi()` |
| GET | `/api/v1/siakad/obe/pemetaan-cpl-cpmk-mk` | Daftar Pemetaan CPL-CPMK-MK (distribusi ke MK) | ✅ `siakad.kurikulum.read` |
| POST | `/api/v1/siakad/obe/pemetaan-cpl-cpmk-mk/sync` | Simpan Pemetaan Mata Kuliah untuk CPMK Prodi | ✅ `siakad.kurikulum.manage` |
| GET | `/api/v1/siakad/obe/rps-referensi` | Daftar referensi RPS (Bentuk, Metode, Kriteria, Komponen) | ✅ `siakad.kurikulum.read` |
| POST | `/api/v1/siakad/obe/rps-referensi` | Tambah referensi RPS | ✅ `siakad.kurikulum.manage` |
| PUT | `/api/v1/siakad/obe/rps-referensi/{id}` | Perbarui referensi RPS | ✅ `siakad.kurikulum.manage` |
| DELETE | `/api/v1/siakad/obe/rps-referensi/{id}` | Hapus referensi RPS (soft delete) | ✅ `siakad.kurikulum.manage` |
| GET | `/api/v1/siakad/obe/rps` | Daftar RPS (filter + pagination) | ✅ |
| GET | `/api/v1/siakad/obe/rps/{id}` | Detail RPS | ✅ |
| POST | `/api/v1/siakad/obe/rps` | Simpan RPS (header + pengesahan + mingguan) | ✅ |
| PATCH | `/api/v1/siakad/obe/rps/{id}/toggle-dosen-edit` | Toggle izin edit dosen pada RPS | ✅ `siakad.kurikulum.manage` |
| DELETE | `/api/v1/siakad/obe/rps/{id}` | Hapus dokumen RPS | ✅ `canManageObeForProdi()` |
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
| GET | `/api/v1/siakad/obe/soal` | Daftar bank soal (filter + pagination) | ✅ |
| POST | `/api/v1/siakad/obe/soal` | Simpan bank soal (create/update via `id`) | ✅ |
| DELETE | `/api/v1/siakad/obe/soal/{id}` | Hapus bank soal | ✅ |
| GET | `/api/v1/siakad/obe/soal-kategori` | Daftar kategori bank soal | ✅ |
| POST | `/api/v1/siakad/obe/soal-kategori` | Buat kategori bank soal | ✅ |
| DELETE | `/api/v1/siakad/obe/soal-kategori/{id}` | Hapus kategori (soal dilepas, tidak ikut terhapus) | ✅ |
| POST | `/api/v1/siakad/obe/soal/{soalId}/opsi` | Tambah opsi jawaban pilihan ganda | ✅ |
| DELETE | `/api/v1/siakad/obe/soal/{soalId}/opsi/{opsiId}` | Hapus opsi jawaban | ✅ |

## Query Parameters (berlaku untuk endpoint list)

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari kode/nama |
| `program_studi_id` | integer | ❌ | — | Filter program studi |
| `kurikulum_id` | integer | ❌ | — | Filter kurikulum (CPL, Profil Lulusan) |
| `kategori` | string | ❌ | — | Filter kategori CPL (`sikap`, `pengetahuan`, `keterampilan_umum`, `keterampilan_khusus`); cocok ke kolom `kategori` atau isi `jenis_list` (multi) |
| `jenis_cpl_id` | integer | ❌ | — | Filter jenis CPL (`siakad_jenis_cpl`) |
| `is_active` | boolean | ❌ | — | Filter status aktif (CPL) |
| `sort_by` | string | ❌ | `created_at` | Umum: `created_at`, `updated_at`, `nama`, `id`; CPL whitelist: `kode_cpl`, `kategori`, `created_at`, `id` (default `kode_cpl` asc) |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

### Otorisasi per prodi

`POST` dan `PUT` memvalidasi akses pada `StoreCplRequest::authorize()`, yaitu
`canManageObeForProdi()`. Dengan demikian **Tim Kurikulum / Kaprodi yang terdaftar di
`siakad_admin_prodi` tetap boleh menambah dan mengubah CPL prodi aktifnya**, walaupun
hanya memiliki permission `siakad.kurikulum.read` dan bukan `siakad.kurikulum.manage`.
`DELETE` memakai pemeriksaan `canManageObeForProdi()` terhadap prodi milik CPL yang
dihapus. Request ke luar scope prodi user dijawab `403`.

### Body `POST /api/v1/siakad/obe/cpl` & `PUT /api/v1/siakad/obe/cpl/{id}`

| Field | Type | Wajib | Deskripsi |
|---|---|---|---|
| `program_studi_id` | integer | ❌ bila `kurikulum_id` diisi | FK `siakad_program_studi`; diturunkan dari kurikulum bila kosong |
| `kurikulum_id` | integer | ❌ | FK `siakad_kurikulum` |
| `jenis_cpl_id` | integer | ❌ | FK `siakad_jenis_cpl` |
| `kode_cpl` | string | ✅ | Kode unik per prodi, maks 50 |
| `kategori` | string | ✅ | Kode master `kategori_cpl` (kategori utama; bila multi, kirim yang pertama) |
| `jenis_list` | array | ❌ | Daftar kode kategori tambahan (multiple choice, mis. `["sikap","pengetahuan"]`) |
| `deskripsi` | string | ✅ | Deskripsi capaian |
| `is_active` | boolean | ❌ | Default `true` |

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

## [GET] /api/v1/siakad/obe/bahan-kajian

> Daftar Bahan Kajian (BK) milik program studi pengguna. Wajib permission `siakad.kurikulum.read`.
> Untuk user non-superadmin, hasil **selalu** dibatasi ke program studi aktif miliknya
> (user tanpa penugasan prodi akan memperoleh `data: []`, bukan seluruh data).

### Request Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari pada `kode_bk` / `nama_bk` |
| `program_studi_id` | integer | ❌ | — | Filter program studi (diabaikan bila user ter-scope prodi) |
| `kurikulum_id` | integer | ❌ | — | Filter kurikulum acuan |
| `koordinator_id` | integer | ❌ | — | Filter dosen koordinator |
| `sort_by` | string | ❌ | `kode_bk` | Whitelist: `kode_bk`, `nama_bk`, `kurikulum_id`, `created_at`, `id` |
| `sort_order` | string | ❌ | `asc` | `asc` / `desc` |
| `page` | integer | ❌ | `1` | Halaman |
| `per_page` | integer | ❌ | `15` | Maks. 100 |

### Response Sukses

**200 OK** (dengan `page`/`per_page`/`limit`)
```json
{
    "status": "success",
    "message": "Daftar bahan kajian berhasil diambil",
    "data": [
        {
            "id": 3,
            "program_studi_id": 7,
            "kurikulum_id": 2,
            "kode_bk": "BK-01",
            "nama_bk": "Analisis dan Perancangan Sistem",
            "koordinator_id": 11,
            "kurikulum": { "id": 2, "nama": "Kurikulum OBE 2026", "tahun_berlaku": 2026 },
            "koordinator": { "id": 11, "nama_lengkap": "Dr. Koordinator", "nik": "3273..." },
            "program_studi": { "id": 7, "kode_prodi": "S1-TI", "nama": "Teknik Informatika" },
            "cpls": [],
            "mata_kuliahs": []
        }
    ],
    "meta": {
        "current_page": 1, "per_page": 15, "total": 1,
        "last_page": 1, "from": 1, "to": 1
    }
}
```

Tanpa parameter paginasi, field `data` berisi seluruh hasil (tanpa `meta`).

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

## [POST] /api/v1/siakad/obe/bahan-kajian

> Menyimpan Bahan Kajian. Wajib permission `siakad.kurikulum.manage`. Bila `program_studi_id` kosong, diturunkan dari `kurikulum_id`; bila tetap kosong dan user tidak memiliki prodi, dibalas **422**.

### Request Body

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `program_studi_id` | integer | ❌ | `exists:siakad_program_studi,id` |
| `kurikulum_id` | integer | ❌ | `exists:siakad_kurikulum,id` |
| `koordinator_id` | integer | ❌ | `exists:siakad_dosen,id` |
| `kode_bk` | string | ✅ | Maks 50, unik per program studi |
| `nama_bk` | string | ✅ | Rumusan bahan kajian, maks 255 |
| `deskripsi` | string | ❌ | Keterangan, maks 2000 |

```json
{
    "program_studi_id": 7,
    "kurikulum_id": 2,
    "koordinator_id": 11,
    "kode_bk": "BK-01",
    "nama_bk": "Analisis dan Perancangan Sistem",
    "deskripsi": null
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Bahan Kajian berhasil disimpan",
    "data": { "id": 3, "kode_bk": "BK-01", "nama_bk": "Analisis dan Perancangan Sistem" }
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
    "errors": { "kode_bk": ["Kode bahan kajian sudah digunakan pada program studi ini."] }
}
```

---

## [PUT] /api/v1/siakad/obe/bahan-kajian/{id}

> Memperbarui Bahan Kajian. Wajib permission `siakad.kurikulum.manage`.

### URL Parameters

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | integer | ID bahan kajian |

### Request Body

Body sama seperti `POST /bahan-kajian`. Bila `program_studi_id` tidak dikirim, memakai nilai lama.

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Bahan Kajian berhasil diperbarui",
    "data": { "id": 3, "kode_bk": "BK-01", "nama_bk": "Analisis dan Perancangan Sistem (Revisi)" }
}
```

### Response Error

**401 Unauthorized** / **403 Forbidden** / **422 Unprocessable Entity** — sama seperti `POST /bahan-kajian`.
**404 Not Found**
```json
{ "status": "error", "message": "No query results for model [App\\Models\\Siakad\\BahanKajian] 999" }
```

---

## [DELETE] /api/v1/siakad/obe/bahan-kajian/{id}

> Menghapus Bahan Kajian (soft delete) dan melepas seluruh pivot CPL & MK. Wajib permission `siakad.kurikulum.manage`.

### URL Parameters

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | integer | ID bahan kajian |

### Response Sukses

**200 OK**
```json
{ "status": "success", "message": "Bahan Kajian berhasil dihapus", "data": null }
```

### Response Error

**401 Unauthorized** / **403 Forbidden** — seperti endpoint lain.
**404 Not Found** — bila ID tidak ada.

---

## [GET] /api/v1/siakad/obe/bahan-kajian/matrix/cpl

> Matriks pemetaan CPL ↔ BK untuk keperluan matriks cetak. Wajib permission `siakad.kurikulum.read`.

### Query Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `program_studi_id` | integer | ❌ | Filter program studi |
| `kurikulum_id` | integer | ❌ | Filter kurikulum acuan |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Matriks pemetaan CPL-BK berhasil diambil",
    "data": {
        "cpls": [{ "id": 1, "kode_cpl": "CPL-01", "kategori": "pengetahuan", "program_studi_id": 7 }],
        "bahan_kajians": [{ "id": 3, "kode_bk": "BK-01", "nama_bk": "Analisis dan Perancangan Sistem", "program_studi_id": 7 }],
        "pairs": [{ "cpl_id": 1, "bahan_kajian_id": 3 }]
    }
}
```

### Response Error

**401 Unauthorized** / **403 Forbidden** — seperti endpoint lain.

---

## [POST] /api/v1/siakad/obe/cpl/bahan-kajian

> Menyimpan pemetaan satu CPL ke daftar Bahan Kajian (checkbox). Wajib permission `siakad.kurikulum.manage`.

### Request Body

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `cpl_id` | integer | ✅ | `exists:siakad_cpl,id` |
| `bahan_kajian_ids` | array | ❌ | Daftar `exists:siakad_bahan_kajian,id` (boleh kosong untuk melepas semua) |

```json
{ "cpl_id": 1, "bahan_kajian_ids": [3, 4] }
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Pemetaan CPL ke Bahan Kajian berhasil disimpan",
    "data": { "id": 1, "kode_cpl": "CPL-01" }
}
```

### Response Error

**401 Unauthorized** / **403 Forbidden** / **422 Unprocessable Entity** — seperti endpoint lain.

---

## [GET] /api/v1/siakad/obe/bahan-kajian/matrix/mata-kuliah

> Matriks pemetaan BK ↔ MK. Wajib permission `siakad.kurikulum.read`.

### Query Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `program_studi_id` | integer | ❌ | Filter program studi |
| `kurikulum_id` | integer | ❌ | Filter kurikulum |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Matriks pemetaan BK-MK berhasil diambil",
    "data": {
        "mata_kuliahs": [{ "id": 5, "kode_mk": "PM-IK-1-1-005", "nama": "Teori Fotografi", "kurikulum_id": 2 }],
        "bahan_kajians": [{ "id": 3, "kode_bk": "BK-01", "nama_bk": "Analisis dan Perancangan Sistem" }],
        "pairs": [{ "mata_kuliah_id": 5, "bahan_kajian_id": 3 }]
    }
}
```

### Response Error

**401 Unauthorized** / **403 Forbidden** — seperti endpoint lain.

---

## [POST] /api/v1/siakad/obe/bahan-kajian/mata-kuliah

> Menyimpan pemetaan satu Bahan Kajian ke daftar Mata Kuliah (checkbox). Wajib permission `siakad.kurikulum.manage`.

### Request Body

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `bahan_kajian_id` | integer | ✅ | `exists:siakad_bahan_kajian,id` |
| `mata_kuliah_ids` | array | ❌ | Daftar `exists:siakad_mata_kuliah,id` (boleh kosong untuk melepas semua) |

```json
{ "bahan_kajian_id": 3, "mata_kuliah_ids": [5, 6] }
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Pemetaan Bahan Kajian ke Mata Kuliah berhasil disimpan",
    "data": { "id": 3, "kode_bk": "BK-01" }
}
```

### Response Error

**401 Unauthorized** / **403 Forbidden** / **422 Unprocessable Entity** — seperti endpoint lain.

---

## [GET] /api/v1/siakad/obe/matrix/cpl-mata-kuliah

> Matriks Pemetaan CPL ↔ MK beserta **status kelayakan tiap sel**. Wajib permission `siakad.kurikulum.read`.
>
> **Aturan kelayakan:** sebuah sel (CPL × MK) hanya boleh dicentang bila sudah ada
> jalur `CPL -> BK -> MK`, yaitu ada minimal satu Bahan Kajian yang dipetakan ke CPL
> tersebut sekaligus dipetakan ke MK tersebut. Kelayakan dihitung ulang oleh
> backend pada setiap pemanggilan (bukan rely on cache), sehingga perubahan pada
> Pemetaan CPL-BK maupun Pemetaan BK-MK langsung tercermin.

### Query Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `program_studi_id` | integer | ❌ | Membatasi tampilan pada satu prodi; diabaikan bila di luar scope prodi aktif user |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Matriks pemetaan CPL-MK berhasil diambil",
    "data": {
        "cpls": [{ "id": 1, "kode_cpl": "CPL-01", "deskripsi": "Mampu menganalisis data" }],
        "mata_kuliahs": [{ "id": 5, "kode_mk": "PM-IK-1-1-005", "nama": "Teori Fotografi" }],
        "eligible": [{ "cpl_id": 1, "mata_kuliah_id": 5 }],
        "pairs": [{ "cpl_id": 1, "mata_kuliah_id": 5 }],
        "yatim": []
    }
}
```

| Field | Tipe | Keterangan |
|---|---|---|
| `eligible` | array | Pasangan yang punya jalur CPL → BK → MK; sel ini boleh dicentang |
| `pairs` | array | Pasangan yang sudah dicentang pada `siakad_mata_kuliah_cpl` |
| `yatim` | array of string | Kunci `${cpl_id}-${mata_kuliah_id}` yang sudah dicentang tetapi jalurnya sudah hilang. Tetap ditampilkan agar riwayat akreditasi tidak hilang, namun ditandai ⚠️ untuk ditinjau |

### Response Error

**401 Unauthorized** / **403 Forbidden** — seperti endpoint lain.

---

## [POST] /api/v1/siakad/obe/cpl/mata-kuliah

> Menyimpan atau melepas satu sel Pemetaan CPL-MK. Wajib permission `siakad.kurikulum.manage`.
>
> Guard kelayakan ditegakkan di server: pasangan tanpa jalur CPL → BK → MK ditolak
> `422` meskipun request dibuat langsung ke API, bukan hanya dari UI.

### Request Body

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `cpl_id` | integer | ✅ | `exists:siakad_cpl,id` |
| `mata_kuliah_id` | integer | ✅ | `exists:siakad_mata_kuliah,id` |
| `is_checked` | boolean | ✅ | `true` menyimpan, `false` melepas |

```json
{ "cpl_id": 1, "mata_kuliah_id": 5, "is_checked": true }
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Pemetaan CPL-MK berhasil disimpan",
    "data": { "cpl_id": 1, "mata_kuliah_id": 5, "is_checked": true }
}
```

### Response Error

| Kode | Keterangan |
|---|---|
| **401 / 403** | Token tidak valid atau prodi di luar scope user |
| **404** | CPL atau mata kuliah tidak ditemukan |
| **422** | CPL dan MK berada pada prodi berbeda, atau belum ada jalur CPL → BK → MK |

> Melepas centang (`is_checked: false`) **selalu diizinkan**, termasuk untuk sel yang
> sudah berstatus `yatim` — justru itulah perbaikan atas pemetaan yang jalurnya hilang.

---

## [GET] /api/v1/siakad/obe/matrix/cpl-bahan-kajian-mata-kuliah

> Laporan **read-only** Pemetaan CPL-BK-MK. Wajib permission `siakad.kurikulum.read`.
>
> Tidak ada endpoint tulis untuk matriks ini. Isi laporan disusun langsung dari
> komposisi Pemetaan CPL-BK dan Pemetaan BK-MK, sehingga mustahil melenceng dari
> pemetaan induknya. Baris = Bahan Kajian, kolom = CPL, isi sel = daftar Mata Kuliah
> yang menjembatani keduanya.

### Query Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `program_studi_id` | integer | ❌ | Membatasi tampilan pada satu prodi; diabaikan bila di luar scope prodi aktif user |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Matriks pemetaan CPL-BK-MK berhasil diambil",
    "data": {
        "cpls": [{ "id": 1, "kode_cpl": "CPL-01" }],
        "bahan_kajians": [{ "id": 3, "kode_bk": "BK01", "nama_bk": "Social Issues, Ethics and Profesionalism" }],
        "isi": {
            "1": {
                "3": [
                    { "kode_mk": "PM-IK-1-1-005", "nama_mk": "Teori Fotografi" },
                    { "kode_mk": "PM-IK-2-3-003", "nama_mk": "Psikologi Komunikasi" }
                ]
            }
        }
    }
}
```

Struktur `isi` adalah `isi[cpl_id][bahan_kajian_id]` → array MK. CPL, BK, dan MK pada
satu jalur dijamin berasal dari program studi yang sama.

### Response Error

**401 Unauthorized** / **403 Forbidden** — seperti endpoint lain.

---

## [GET] /api/v1/siakad/obe/cpmk-prodi

> Daftar Rumusan CPMK Program Studi (CPMK-PS). Wajib permission `siakad.kurikulum.read`.
> Program studi mengikuti prodi aktif akun user.

### Query Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `kurikulum_id` | integer | ❌ | Filter kurikulum |
| `cpl_id` | integer | ❌ | Filter CPL prodi |
| `search` | string | ❌ | Cari kode/rumusan CPMK |
| `sort_by` | string | ❌ | Whitelist: `kode_cpmk`, `created_at`, `id` (default `kode_cpmk`) |
| `sort_order` | string | ❌ | `asc` / `desc` |
| `per_page` | integer | ❌ | Default 15, maks 100 |
| `page` | integer | ❌ | Halaman |

### Response Sukses

**200 OK** (dengan pagination)
```json
{
    "status": "success",
    "message": "Daftar rumusan CPMK program studi berhasil diambil",
    "data": [
        {
            "id": 1,
            "kurikulum_id": 2,
            "cpl_id": 4,
            "kode_cpmk": "CPMK-01",
            "deskripsi": "Mampu merancang arsitektur sistem cloud",
            "kurikulum": { "id": 2, "nama": "K23 Indonesia Mantap" },
            "cpl": { "id": 4, "kode_cpl": "CPL01", "deskripsi": "..." }
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

### Response Error

**401 Unauthorized** / **403 Forbidden** — seperti endpoint lain.

---

## [POST] /api/v1/siakad/obe/cpmk-prodi

> Menyimpan rumusan CPMK program studi baru. Menggunakan validasi `StoreCpmkProdiRequest`
> yang memverifikasi prodi aktif user (`canManageObeForProdi()`).

### Request Body

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `kurikulum_id` | integer | ✅ | `exists:siakad_kurikulum,id` |
| `cpl_id` | integer | ✅ | `exists:siakad_cpl,id`; wajib milik prodi yang sama dengan kurikulum |
| `kode_cpmk` | string | ✅ | Unik per kurikulum, maks 50 |
| `deskripsi` | string | ✅ | Rumusan CPMK, maks 2000 |

```json
{
    "kurikulum_id": 2,
    "cpl_id": 4,
    "kode_cpmk": "CPMK-01",
    "deskripsi": "Mampu merancang arsitektur sistem cloud"
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Rumusan CPMK program studi berhasil disimpan",
    "data": {
        "id": 1,
        "kurikulum_id": 2,
        "cpl_id": 4,
        "kode_cpmk": "CPMK-01",
        "deskripsi": "Mampu merancang arsitektur sistem cloud"
    }
}
```

### Response Error

| Kode | Keterangan |
|---|---|
| **401 / 403** | Token tidak valid atau prodi di luar scope user |
| **422** | CPL bukan milik prodi kurikulum tersebut, atau kode CPMK sudah terpakai |

---

## [PUT] /api/v1/siakad/obe/cpmk-prodi/{id}

> Memperbarui rumusan CPMK program studi. Kurikulum bersifat tetap; CPL, kode, dan
> rumusan dapat diubah.

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID rumusan CPMK prodi |

### Request Body

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `kurikulum_id` | integer | ✅ | `exists:siakad_kurikulum,id` (harus sama dengan kurikulum tersimpan) |
| `cpl_id` | integer | ✅ | `exists:siakad_cpl,id`; wajib milik prodi yang sama |
| `kode_cpmk` | string | ✅ | Kode CPMK unik per kurikulum |
| `deskripsi` | string | ✅ | Rumusan CPMK, maks 2000 |

```json
{
    "kurikulum_id": 2,
    "cpl_id": 4,
    "kode_cpmk": "CPMK-01",
    "deskripsi": "Mampu merancang arsitektur sistem cloud terdistribusi"
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Rumusan CPMK program studi berhasil diperbarui",
    "data": {
        "id": 1,
        "kurikulum_id": 2,
        "cpl_id": 4,
        "kode_cpmk": "CPMK-01",
        "deskripsi": "Mampu merancang arsitektur sistem cloud terdistribusi"
    }
}
```

### Response Error

**401 Unauthorized** / **403 Forbidden** / **404 Not Found** / **422 Unprocessable Entity**.

---

## [DELETE] /api/v1/siakad/obe/cpmk-prodi/{id}

> Menghapus rumusan CPMK program studi (soft delete). Tim Kurikulum hanya boleh
> menghapus rumusan milik prodi aktifnya.

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID rumusan CPMK prodi |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Rumusan CPMK program studi berhasil dihapus",
    "data": null
}
```

### Response Error

**401 Unauthorized** / **403 Forbidden** / **404 Not Found**.

---

## [GET] /api/v1/siakad/obe/pemetaan-cpl-cpmk-mk

> Mengambil daftar pemetaan CPL-CPMK-MK (Rumusan CPMK beserta CPL dan daftar Mata Kuliah yang mengampunya). Wajib permission `siakad.kurikulum.read`.

### Query Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `kurikulum_id` | integer | ❌ | Filter kurikulum |
| `cpl_id` | integer | ❌ | Filter CPL prodi |
| `search` | string | ❌ | Cari kata kunci pada kode/rumusan CPL atau CPMK |
| `sort_by` | string | ❌ | Whitelist: `kode_cpmk`, `cpl_id`, `created_at`, `id` (default `kode_cpmk`) |
| `sort_order` | string | ❌ | `asc` / `desc` |
| `per_page` | integer | ❌ | Default 15, maks 100 |
| `page` | integer | ❌ | Halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data pemetaan CPL-CPMK-MK berhasil dimuat",
    "data": [
        {
            "id": 1,
            "kurikulum_id": 2,
            "cpl_id": 4,
            "kode_cpmk": "CPMK011",
            "deskripsi": "Mampu mengembangkan jiwa wirausaha mandiri...",
            "kurikulum": { "id": 2, "nama": "K23 Indonesia Mantap" },
            "cpl": { "id": 4, "kode_cpl": "CPL01", "deskripsi": "Mampu mengembangkan jiwa wirausaha..." },
            "mata_kuliahs": [
                { "id": 10, "kode_mk": "PM-IK-1-3-004", "nama": "WORKSHOP CREATIVE THINKING" }
            ]
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

### Response Error

**401 Unauthorized** / **403 Forbidden**.

---

## [POST] /api/v1/siakad/obe/pemetaan-cpl-cpmk-mk/sync

> Menyimpan pemetaan daftar Mata Kuliah ke satu Rumusan CPMK Prodi. Wajib permission `siakad.kurikulum.manage` atau Tim Kurikulum prodi.

### Request Body

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `cpmk_prodi_id` | integer | ✅ | `exists:siakad_cpmk_prodi,id` |
| `mata_kuliah_ids` | array | ✅ | Array ID `exists:siakad_mata_kuliah,id` (boleh `[]` untuk melepas semua) |

```json
{
    "cpmk_prodi_id": 1,
    "mata_kuliah_ids": [10, 15]
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Pemetaan Mata Kuliah untuk CPMK CPMK011 berhasil disimpan",
    "data": { "id": 1, "kode_cpmk": "CPMK011", "mata_kuliahs": [...] }
}
```

### Response Error

**401 Unauthorized** / **403 Forbidden** / **422 Unprocessable Entity**.

---

## [GET] /api/v1/siakad/obe/rps-referensi

> Mengambil daftar master referensi RPS (Bentuk, Metode, Kriteria, Komponen). Wajib permission `siakad.kurikulum.read`.

### Query Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `tipe` | string | ❌ | Filter tipe: `bentuk`, `metode`, `kriteria`, `komponen` |
| `search` | string | ❌ | Cari nama / kode / deskripsi |
| `sort_by` | string | ❌ | Whitelist: `nama`, `kode`, `created_at`, `id` (default `nama`) |
| `sort_order` | string | ❌ | `asc` / `desc` |
| `per_page` | integer | ❌ | Default 15, maks 100 |
| `page` | integer | ❌ | Halaman |

### Response Sukses

**200 OK** (dengan pagination)
```json
{
    "status": "success",
    "message": "Data referensi RPS berhasil dimuat",
    "data": [
        {
            "id": 1,
            "tipe": "bentuk",
            "kode": "BTK-01",
            "nama": "Kuliah / Responsi",
            "deskripsi": "Bentuk pembelajaran tatap muka terjadwal",
            "is_active": true
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

### Response Error

**401 Unauthorized** / **403 Forbidden**.

---

## [POST] /api/v1/siakad/obe/rps-referensi

> Menambah data master referensi RPS baru. Wajib permission `siakad.kurikulum.manage`. Data otomatis terisolasi pada program studi aktif pengguna (`program_studi_id`).

### Request Body

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `tipe` | string | ✅ | `in:bentuk,metode,kriteria,komponen` |
| `program_studi_id` | integer | ❌ | Otomatis diisi prodi aktif user (hanya superadmin boleh mengisi manual) |
| `kode` | string | ❌ | Maks. 50 |
| `nama` | string | ✅ | Nama/label referensi, maks. 255 |
| `deskripsi` | string | ❌ | Deskripsi detail, maks. 2000 |

```json
{
    "tipe": "bentuk",
    "program_studi_id": 1,
    "kode": "BTK-01",
    "nama": "Kuliah / Responsi",
    "deskripsi": "Bentuk pembelajaran tatap muka terjadwal di kelas."
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Data referensi RPS berhasil disimpan",
    "data": {
        "id": 1,
        "tipe": "bentuk",
        "program_studi_id": 1,
        "kode": "BTK-01",
        "nama": "Kuliah / Responsi",
        "deskripsi": "Bentuk pembelajaran tatap muka terjadwal di kelas.",
        "is_active": true
    }
}
```

### Response Error

| Kode | Keterangan |
|---|---|
| **401** | Belum terautentikasi |
| **403** | Mencoba menyimpan/mengubah/menghapus data referensi atau dokumen RPS milik program studi lain |
| **422** | Validasi gagal |

---

## [PUT] /api/v1/siakad/obe/rps-referensi/{id}

> Memperbarui data master referensi RPS. Wajib permission `siakad.kurikulum.manage`.

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID referensi RPS |

### Request Body

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `kode` | string | ❌ | Maks. 50 |
| `nama` | string | ✅ | Nama/label referensi, maks. 255 |
| `deskripsi` | string | ❌ | Deskripsi detail, maks. 2000 |

```json
{
    "kode": "BTK-01B",
    "nama": "Kuliah / Responsi Tatap Muka & Daring",
    "deskripsi": "Perkuliahan hybrid sinkron"
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data referensi RPS berhasil diperbarui",
    "data": {
        "id": 1,
        "tipe": "bentuk",
        "kode": "BTK-01B",
        "nama": "Kuliah / Responsi Tatap Muka & Daring",
        "deskripsi": "Perkuliahan hybrid sinkron",
        "is_active": true
    }
}
```

### Response Error

**401 Unauthorized** / **403 Forbidden** / **404 Not Found** / **422 Unprocessable Entity**.

---

## [DELETE] /api/v1/siakad/obe/rps-referensi/{id}

> Menghapus data master referensi RPS (soft delete). Wajib permission `siakad.kurikulum.manage`.

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID referensi RPS |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data referensi RPS berhasil dihapus",
    "data": null
}
```

### Response Error

**401 Unauthorized** / **403 Forbidden** / **404 Not Found**.

---

## [PATCH] /api/v1/siakad/obe/rps/{id}/toggle-dosen-edit

> Mengubah izin edit dokumen RPS oleh Dosen Pengampu / Koordinator. Wajib permission `siakad.kurikulum.manage`.

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID dokumen RPS |

### Request Body

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `dosen_bisa_edit` | boolean | ✅ | `true` atau `false` |

```json
{
    "dosen_bisa_edit": true
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Hak akses edit dosen untuk RPS berhasil diperbarui",
    "data": {
        "id": 1,
        "dosen_bisa_edit": true
    }
}
```

### Response Error

**401 Unauthorized** / **403 Forbidden** / **404 Not Found** / **422 Unprocessable Entity**.

---

## [DELETE] /api/v1/siakad/obe/rps/{id}

> Menghapus dokumen RPS (soft delete). Tim Kurikulum hanya boleh menghapus RPS milik prodi aktifnya.

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID dokumen RPS |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Dokumen RPS berhasil dihapus",
    "data": null
}
```

### Response Error

**401 Unauthorized** / **403 Forbidden** / **404 Not Found**.

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

---

## Bank Soal (master tunggal OBE & Quiz LMS)

> Tabel `siakad_bank_soal` adalah master tunggal soal — Quiz LMS fase berikutnya hanya mereferensikan tabel ini (tanpa duplikasi/sync dua arah). Soal pilihan ganda memakai tabel anak `siakad_bank_soal_opsi` (satu opsi benar per soal); `kunci_jawaban` teks dipakai untuk isian/uraian.

### [GET] `/api/v1/siakad/obe/soal`

Filter: `search` (pertanyaan/kunci/opsi), `rps_id`, `rps_mingguan_id`, `sub_cpmk_id`, `kategori_id`, `tipe_soal` (`pilihan_ganda,isian_singkat,uraian`), `tingkat_kesulitan` (`mudah,sedang,sukar`), `sort_by` (`id,bobot,tipe_soal,tingkat_kesulitan,created_at,updated_at`), `sort_order`, `per_page` (maks. 100).

#### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Data bank soal berhasil diambil.",
    "data": [
        {
            "id": 1,
            "rps_id": 5,
            "kategori_id": 2,
            "tipe_soal": "pilihan_ganda",
            "tingkat_kesulitan": "sedang",
            "pertanyaan": "Apa kepanjangan dari OOP?",
            "bobot": "10.00",
            "kategori": { "id": 2, "nama": "Kuis Tengah Semester" },
            "opsi": [
                { "id": 11, "teks": "Object-Oriented Programming", "is_benar": true, "urutan": 1 }
            ]
        }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1 }
}
```

#### Response Error (401 / 403)
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

---

### [POST] `/api/v1/siakad/obe/soal`

Menyimpan atau memperbarui bank soal. Kirim `id` untuk update, tanpa `id` untuk create. Validasi via `StoreBankSoalRequest` (otorisasi `siakad.nilai.manage`).

#### Request Body
```json
{
    "id": null,
    "rps_id": 5,
    "rps_mingguan_id": 12,
    "sub_cpmk_id": 30,
    "kategori_id": 2,
    "tipe_soal": "pilihan_ganda",
    "tingkat_kesulitan": "sedang",
    "pertanyaan": "Apa kepanjangan dari OOP?",
    "bobot": 10,
    "kunci_jawaban": null,
    "pembahasan": "Object-Oriented Programming."
}
```

#### Response Sukses (201 Created / 200 OK)
```json
{
    "status": "success",
    "message": "Soal berhasil disimpan.",
    "data": {
        "id": 1,
        "rps_id": 5,
        "rps_mingguan_id": 12,
        "sub_cpmk_id": 30,
        "kategori_id": 2,
        "tipe_soal": "pilihan_ganda",
        "tingkat_kesulitan": "sedang",
        "pertanyaan": "Apa kepanjangan dari OOP?",
        "bobot": "10.00",
        "kunci_jawaban": null,
        "pembahasan": "Object-Oriented Programming."
    }
}
```

#### Response Error (422 Unprocessable Entity)
```json
{
    "status": "error",
    "message": "Pertanyaan tidak boleh kosong.",
    "errors": {
        "pertanyaan": ["The pertanyaan field is required."]
    }
}
```

---

### [DELETE] `/api/v1/siakad/obe/soal/{id}`

Menghapus bank soal. Jika soal sudah digunakan dalam kuis LMS, penghapusan ditolak.

#### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Soal berhasil dihapus.",
    "data": null
}
```

#### Response Error (400 Bad Request / 404 Not Found)
```json
{
    "status": "error",
    "message": "Soal ini sudah dipakai pada quiz LMS dan tidak dapat dihapus."
}
```

---

### [GET] `/api/v1/siakad/obe/soal-kategori`

Mengambil daftar kategori bank soal dengan paginasi.

#### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Data kategori bank soal berhasil diambil.",
    "data": [
        {
            "id": 1,
            "nama": "Kuis Tengah Semester",
            "mata_kuliah_id": 7,
            "soal_count": 10,
            "mata_kuliah": {
                "id": 7,
                "nama_id": "Pemrograman Web"
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
    }
}
```

#### Response Error (401 Unauthorized)
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

---

### [POST] `/api/v1/siakad/obe/soal-kategori`

Membuat kategori bank soal baru.

#### Request Body
```json
{
    "nama": "Kuis Tengah Semester",
    "mata_kuliah_id": 7,
    "deskripsi": "Kategori soal evaluasi tengah semester"
}
```

#### Response Sukses (201 Created)
```json
{
    "status": "success",
    "message": "Kategori bank soal berhasil dibuat.",
    "data": {
        "id": 1,
        "nama": "Kuis Tengah Semester",
        "mata_kuliah_id": 7,
        "deskripsi": "Kategori soal evaluasi tengah semester"
    }
}
```

#### Response Error (422 Unprocessable Entity)
```json
{
    "status": "error",
    "message": "Validation failed",
    "errors": {
        "nama": ["The nama field is required."]
    }
}
```

---

### [DELETE] `/api/v1/siakad/obe/soal-kategori/{id}`

Menghapus kategori bank soal (soal-soal di dalamnya dilepas kategorinya, tidak ikut terhapus).

#### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Kategori bank soal berhasil dihapus.",
    "data": null
}
```

#### Response Error (404 Not Found)
```json
{
    "status": "error",
    "message": "No query results for model [App\\Models\\Siakad\\BankSoalKategori] 999"
}
```

---

### [POST] `/api/v1/siakad/obe/soal/{soalId}/opsi`

Menambahkan opsi jawaban baru untuk soal pilihan ganda. Satu soal hanya boleh punya satu opsi benar — menandai opsi baru `is_benar: true` otomatis menggeser yang lama.

#### Request Body
```json
{
    "teks": "Object-Oriented Programming",
    "is_benar": true,
    "urutan": 1
}
```

#### Response Sukses (201 Created)
```json
{
    "status": "success",
    "message": "Opsi jawaban berhasil ditambahkan.",
    "data": {
        "id": 11,
        "bank_soal_id": 1,
        "teks": "Object-Oriented Programming",
        "is_benar": true,
        "urutan": 1
    }
}
```

#### Response Error (422 Unprocessable Entity)
```json
{
    "status": "error",
    "message": "Validation failed",
    "errors": {
        "teks": ["The teks field is required."]
    }
}
```

---

### [DELETE] `/api/v1/siakad/obe/soal/{soalId}/opsi/{opsiId}`

Menghapus opsi jawaban soal pilihan ganda.

#### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Opsi jawaban berhasil dihapus.",
    "data": null
}
```

#### Response Error (404 Not Found)
```json
{
    "status": "error",
    "message": "No query results for model [App\\Models\\Siakad\\BankSoalOpsi] 999"
}
```

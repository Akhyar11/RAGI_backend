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
| GET | `/api/v1/siakad/obe/cpl` | Daftar CPL (search, filter prodi/kurikulum/kategori/jenis/status, sort whitelist, pagination) | ✅ |
| POST | `/api/v1/siakad/obe/cpl` | Tambah CPL | ✅ |
| PUT | `/api/v1/siakad/obe/cpl/{id}` | Perbarui CPL | ✅ |
| DELETE | `/api/v1/siakad/obe/cpl/{id}` | Hapus CPL (soft delete) | ✅ |
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

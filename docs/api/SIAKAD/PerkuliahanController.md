# PerkuliahanController

> **Diperbarui**: 2026-11-25

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
| GET | `/api/v1/siakad/perkuliahan/krs/monitoring` | Monitoring & rekapitulasi progres KRS per prodi | ✅ |
| GET | `/api/v1/siakad/perkuliahan/krs/monitoring/mahasiswa` | Daftar mahasiswa beserta status KRS satu periode (siapa yang belum mengisi) | ✅ |
| GET | `/api/v1/siakad/perkuliahan/krs/monitoring/mahasiswa/{mahasiswaId}` | Rincian KRS mahasiswa: mata kuliah sudah dan belum diambil | ✅ |
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
| POST | `/api/v1/siakad/perkuliahan/kelas/{kelasId}/pertemuan` | Tambah pertemuan (Super Admin saja; dipanggil UI LMS) | ✅ `lms.manage-perkuliahan` |
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
            "krs_details_count": 30,
            "krs_details_aktif_count": 28,
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
    "parent_kelas_id": "integer, nullable, exists:siakad_jadwal_kelas,id — wajib untuk tipe praktik (1 level, satu periode dengan induk; MK boleh beda)",
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

> **Catatan baris KRS historis (2026-11-25):** `krs_details[]` kini dapat berasal dari impor Neo Feeder tanpa kelas.
> Pada baris tersebut `kelas_id` dan `kelas` bernilai `null`, sedangkan `mata_kuliah_id`, `mata_kuliah` (relasi `mataKuliah`),
> dan `master_kelas_id` (rombel) terisi. Baris dari KRS online tetap memiliki `kelas` dan juga menyalin `mata_kuliah_id`.
> Perhitungan IPS/IPK, SKS, prasyarat, dan transkrip memakai mata kuliah efektif (`kelas.mata_kuliah` bila ada, jika tidak `mata_kuliah`).
> Klien tidak boleh menganggap `kelas` selalu ada.


### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari mahasiswa / NIM |
| `tahun_akademik_id` | integer | ❌ | tahun akademik aktif | Filter periode |
| `id` | integer | ❌ | — | Ambil satu KRS (dipakai halaman detail); tetap tunduk pembatasan akses mahasiswa |
| `program_studi_id` | integer | ❌ | — | Filter prodi mahasiswa |
| `angkatan` | string | ❌ | — | Filter angkatan mahasiswa |
| `sks_min` / `sks_max` | integer | ❌ | — | Rentang `total_sks_diambil` |
| `status` | string | ❌ | — | `draft`, `diajukan`, `disetujui`, `dikunci`, `ditolak` |
| `sort_by` | string | ❌ | `created_at` | `created_at`, `total_sks_diambil`, `status`, `tahun_akademik_id` (nilai lain jatuh ke `created_at`) |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `25` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

Respons memuat relasi `mahasiswa.program_studi`, `tahun_akademik`, dan `krs_details` (termasuk `mata_kuliah` dan `master_kelas` untuk KRS historis hasil sync PDDIKTI). Mahasiswa non-admin hanya melihat KRS miliknya.

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": [
        { "id": 9, "mahasiswa_id": 1, "tahun_akademik_id": 1, "total_sks_diambil": 20, "status": "draft" }
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

## [GET] /api/v1/siakad/perkuliahan/krs/monitoring/mahasiswa

Daftar mahasiswa beserta status KRS pada satu periode, untuk menjawab "siapa yang belum mengambil KRS". Otorisasi `siakad.krs.read`; selain superadmin, hanya prodi yang ditugaskan (`getSiakadProdiIds`) yang boleh diakses, tanpa penugasan aktif ditolak 403. Hanya-baca, tidak menulis data.

### Request Headers

| Header | Wajib | Nilai |
|---|---|---|
| `Authorization` | ✅ | `Bearer <token>` |
| `Accept` | ✅ | `application/json` |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tahun_akademik_id` | integer | ❌ | TA aktif | Periode. Periode berjalan: populasi mahasiswa aktif; periode lampau: mahasiswa yang punya KRS pada periode itu |
| `program_studi_id` | integer | ❌ | — | Filter prodi (dibatasi penugasan bila non-superadmin) |
| `status_krs` | string | ❌ | — | `belum`, `sudah`, `draft`, `diajukan`, `disetujui`, `dikunci`. `belum` hanya bermakna pada periode berjalan |
| `angkatan` | string | ❌ | — | Filter angkatan |
| `search` | string | ❌ | — | Nama, NIM, atau nama dosen wali |
| `terkunci_keuangan` | string | ❌ | — | `ya` (KRS terkunci tunggakan keuangan) / `tidak` |
| `sks_min` / `sks_max` | integer | ❌ | — | Rentang SKS pada KRS periode (mahasiswa belum mengisi dihitung 0) |
| `sort_by` | string | ❌ | `nim` | `nim`, `nama_lengkap`, `angkatan`, `krs_sks` |
| `sort_order` | string | ❌ | `asc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Daftar mahasiswa monitoring KRS berhasil dimuat",
    "data": {
        "tahun_akademik": { "id": 5, "kode": "20261", "nama": "2026/2027 Ganjil", "is_active": true },
        "periode_berjalan": true,
        "items": [
            {
                "id": 101, "nim": "A23005", "nama_lengkap": "Faiz Azhar Ramadhani", "angkatan": 2023,
                "program_studi": { "id": 3, "nama": "Teknologi Rekayasa Otomotif", "jenjang": "D4" },
                "dosen_wali": "Dosen Wali Uji", "semester_ke": 7,
                "krs_id": null, "krs_status": null, "krs_sks": 0, "krs_jumlah_mk": 0, "terkunci_keuangan": false
            }
        ]
    },
    "meta": { "current_page": 1, "last_page": 35, "per_page": 15, "total": 524, "from": 1, "to": 15 }
}
```
`krs_status = null` berarti belum mengisi KRS.

### Response Error

**401 Unauthorized**
```json
{ "status": "error", "message": "Unauthenticated." }
```
**403 Forbidden**
```json
{ "status": "error", "message": "Anda tidak memiliki penugasan program studi aktif." }
```
**404 Not Found**
```json
{ "status": "error", "message": "Tahun akademik tidak ditemukan." }
```
**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "The selected status krs is invalid.",
    "errors": { "status_krs": ["The selected status krs is invalid."] }
}
```

---

## [GET] /api/v1/siakad/perkuliahan/krs/monitoring/mahasiswa/{mahasiswaId}

Rincian satu mahasiswa: mata kuliah yang sudah diambil pada periode dan mata kuliah kurikulum yang belum diambil. Otorisasi dan pembatasan prodi sama dengan endpoint daftar; mahasiswa di luar prodi yang ditugaskan ditolak 403. Hanya-baca.

Acuan "belum diambil" diturunkan dari kurikulum (perkiraan): kurikulum yang paling banyak dipakai pada KRS mahasiswa (cadangan: kurikulum aktif prodi yang berlaku untuk angkatannya), mata kuliah aktif dengan `semester_anjuran` ≤ semester ke-N yang belum pernah muncul di KRS mahasiswa pada periode mana pun. `jenis` = `semester_ini` bila `semester_anjuran` sama dengan semester berjalan, selain itu `tertinggal`. Semester ke-N dihitung dari angkatan dan kode periode (`TTTTS`: 1 ganjil, 2 genap). Mata kuliah setara berkode berbeda antarkurikulum dapat tampil sebagai tertinggal.

### Request Headers

| Header | Wajib | Nilai |
|---|---|---|
| `Authorization` | ✅ | `Bearer <token>` |
| `Accept` | ✅ | `application/json` |

### Path & Query Parameters

| Parameter | Lokasi | Type | Required | Default | Deskripsi |
|---|---|---|---|---|---|
| `mahasiswaId` | path | integer | ✅ | — | ID mahasiswa |
| `tahun_akademik_id` | query | integer | ❌ | TA aktif | Periode |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Rincian monitoring KRS mahasiswa berhasil dimuat",
    "data": {
        "tahun_akademik": { "id": 5, "kode": "20261", "nama": "2026/2027 Ganjil", "is_active": true },
        "mahasiswa": { "id": 101, "nim": "A23005", "nama_lengkap": "Faiz Azhar Ramadhani", "angkatan": 2023,
                       "program_studi": { "id": 3, "nama": "Teknologi Rekayasa Otomotif", "jenjang": "D4" }, "dosen_wali": "Dosen Wali Uji" },
        "semester_ke": 7,
        "krs": null,
        "sudah_diambil": [],
        "belum_diambil": [
            { "id": 900, "kode_mk": "TRO-PT-7-6-011", "nama": "PROYEK AKHIR", "total_sks": 6,
              "semester_anjuran": 7, "kategori": "Wajib", "jenis": "semester_ini" }
        ],
        "kurikulum_id": 30
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
{ "status": "error", "message": "Mahasiswa di luar program studi yang ditugaskan." }
```
**404 Not Found**
```json
{ "status": "error", "message": "Mahasiswa tidak ditemukan." }
```
**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "The selected tahun akademik id is invalid.",
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

## [GET] /api/v1/siakad/perkuliahan/krs/available-classes

Mengambil kelas aktif pada tahun akademik terpilih. Mahasiswa hanya dapat meminta kelas untuk dirinya sendiri; pengelola program studi dapat meminta kelas mahasiswa prodinya. Filter `program_studi_id` tanpa konteks mahasiswa hanya tersedia untuk pengelola akademik global.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `mahasiswa_id` | integer | ❌ | mahasiswa login | Mahasiswa yang kelasnya dicari; untuk staf harus berada dalam prodi yang dikelola |
| `program_studi_id` | integer | ❌ | prodi mahasiswa | Filter program studi; tanpa `mahasiswa_id` hanya admin global |
| `tahun_akademik_id` | integer | ❌ | tahun akademik aktif | Periode kelas |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": [
        {
            "id": 12,
            "kode_kelas": "IF101-A",
            "nama_kelas": "IF-1A",
            "mata_kuliah": { "id": 45, "kode_mk": "IF101", "nama": "Dasar Pemrograman" },
            "sisa_kuota": 8,
            "is_converted": false,
            "is_enrolled": false,
            "is_full": false,
            "can_take": true
        }
    ]
}
```

Mata kuliah dengan detail konversi final berstatus `disetujui` dan rincian `diakui` ditandai `is_converted: true` serta `can_take: false`.

---

## [POST] /api/v1/siakad/perkuliahan/krs/add-class

Menambahkan kelas ke KRS mahasiswa. Mahasiswa hanya dapat menambah ke KRS miliknya; staf harus memiliki akses pengelola untuk prodi mahasiswa tersebut. Server memeriksa ulang konversi final—kelas untuk mata kuliah yang sudah diakui ditolak meskipun dikirim langsung tanpa melalui daftar kelas tersedia.

### Request Body

```json
{
    "kelas_id": 12,
    "tahun_akademik_id": 1
}
```

| Field | Type | Required | Deskripsi |
|---|---|---|---|
| `kelas_id` | integer | ✅ | ID kelas aktif |
| `mahasiswa_id` | integer | ❌ | Diisi staf; mahasiswa login tidak dapat memilih mahasiswa lain |
| `tahun_akademik_id` | integer | ❌ | Default tahun akademik aktif |

### Response Error

**422 Unprocessable Entity** — kelas mata kuliah sudah diakui melalui konversi:
```json
{
    "status": "error",
    "message": "Mata kuliah ini sudah diakui melalui konversi nilai dan tidak dapat diambil kembali.",
    "errors": {
        "kelas_id": ["Mata kuliah sudah tercatat sebagai hasil konversi."]
    }
}
```

Rincian konversi berstatus `ditolak` atau usulan yang belum mencapai status final `disetujui` tidak memblokir pengambilan mata kuliah.

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

## [GET] /api/v1/siakad/perkuliahan/krs/monitoring

Monitoring dan rekapitulasi progres pengisian KRS mahasiswa per program studi dengan dukungan filter terintegrasi dan server-side pagination. Nilai `summary` dihitung dari hasil data yang telah difilter dan `prodi` dipaginasi.

**Perubahan 2026-11-25:** rekap dihitung dari SEMUA mahasiswa yang memiliki KRS pada periode (bukan hanya yang berstatus aktif saat ini), sehingga periode lampau hasil sync PDDIKTI terbaca benar. Field baru per prodi: `mahasiswa_ber_krs`, `jumlah_krs`, `total_sks`, `rata_sks`, `jumlah_mk`, `dikunci`. Pembanding mahasiswa aktif (`total_mahasiswa_aktif`, `belum_krs`, `persentase_krs`, `persentase_disetujui`) hanya terisi untuk periode berjalan dan bernilai `null` untuk periode lampau; `summary.periode_berjalan` menandai keduanya. Filter `status_krs` dan `progres_krs` hanya berlaku pada periode berjalan.

### Request Headers

| Header | Wajib | Nilai |
|---|---|---|
| `Authorization` | ✅ | `Bearer <token>` |
| `Accept` | ✅ | `application/json` |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tahun_akademik_id` | integer | ❌ | TA aktif | Filter tahun akademik |
| `program_studi_id` | integer | ❌ | — | Filter spesifik program studi (scoped jika non-superadmin) |
| `status_krs` | string | ❌ | — | `lengkap` (100% mengisi) / `ada_belum` (ada belum mengisi) |
| `status_keuangan` | string | ❌ | — | `ada_terkunci` (tunggakan SIKEU) / `lunas` (bebas tunggakan) |
| `progres_krs` | string | ❌ | — | `tinggi` (≥ 80%) / `sedang` (50% - 79%) / `rendah` (< 50%) |
| `sort_by` | string | ❌ | `nama_prodi` | Urutkan berdasarkan: `nama_prodi`, `kode_prodi`, `total_mahasiswa_aktif`, `sudah_krs`, `mahasiswa_ber_krs`, `jumlah_krs`, `total_sks`, `rata_sks`, `jumlah_mk`, `disetujui`, `belum_krs`, `terkunci_keuangan`, `persentase_krs` |
| `sort_order` | string | ❌ | `asc` | Arah urutan: `asc` / `desc` |
| `page` | integer | ❌ | `1` | Halaman |
| `limit` / `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data monitoring KRS berhasil dimuat",
    "data": {
        "tahun_akademik": {
            "id": 1,
            "kode": "20261",
            "nama": "Semester Gasal 2026/2027",
            "is_active": true
        },
        "summary": {
            "total_mahasiswa_aktif": 120,
            "total_sudah_krs": 105,
            "total_disetujui": 90,
            "total_diajukan": 10,
            "total_draft": 5,
            "total_belum_krs": 15,
            "total_terkunci_keuangan": 8,
            "periode_berjalan": true,
            "total_krs": 105,
            "total_dikunci": 0,
            "total_sks": 1890,
            "rata_sks": 18.0,
            "persentase_krs": 87.5,
            "persentase_disetujui": 75.0
        },
        "prodi": [
            {
                "prodi_id": 1,
                "kode_prodi": "TI",
                "nama_prodi": "Teknik Informatika",
                "jenjang": "S1",
                "fakultas": "Fakultas Teknik",
                "mahasiswa_ber_krs": 55,
                "jumlah_krs": 55,
                "total_sks": 990,
                "rata_sks": 18.0,
                "jumlah_mk": 24,
                "dikunci": 0,
                "total_mahasiswa_aktif": 60,
                "sudah_krs": 55,
                "draft": 2,
                "diajukan": 5,
                "disetujui": 48,
                "belum_krs": 5,
                "terkunci_keuangan": 3,
                "persentase_krs": 91.7,
                "persentase_disetujui": 80.0
            }
        ],
        "meta": {
            "current_page": 1,
            "per_page": 15,
            "total": 1,
            "last_page": 1
        }
    },
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1
    }
}
```

### Response Error

**400 Bad Request**
```json
{
    "status": "error",
    "message": "Permintaan tidak valid."
}
```

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
{
    "status": "error",
    "message": "Tahun akademik tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "The given data was invalid.",
    "errors": {
        "sort_by": ["The selected sort by is invalid."],
        "sort_order": ["The selected sort order is invalid."]
    }
}
```

---

### Catatan Tambahan

> - Soft delete + restore berlaku pada KRS dan kelas (data transaksional akademik).
> - Aksi tulis nilai/KRS/kelas tercatat pada audit log SIAKAD.
> - Password/token tidak pernah dikembalikan pada response.
> - Peserta untuk daftar presensi bersumber dari KRS berstatus `disetujui` atau `diajukan`; membuat pertemuan tidak otomatis mencatat status hadir.
> - `POST /kelas/{kelasId}/pertemuan` kini dilindungi Gate `lms.manage-perkuliahan`: hanya Super Admin yang boleh menambah/mengubah pertemuan. Dosen pengampu dan pengelola mendapat `403`. Endpoint baca `GET /kelas/{kelasId}/pertemuan` tetap terbuka sesuai `siakad.kelas.read`.
> - Absensi yang belum disimpan dikembalikan dengan `status: null` dan `is_submitted: false`. Status kosong bukan kehadiran.

## Presensi Pertemuan

### [GET] `/api/v1/siakad/perkuliahan/pertemuan/{pertemuanId}/absensi`

Mengambil roster peserta kelas dari KRS bersama data presensi yang sudah tersimpan.

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari nama atau NIM mahasiswa |
| `per_page` | integer | ❌ | `25` | Jumlah baris per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

```json
{
    "status": "success",
    "message": "Daftar absensi mahasiswa berhasil dimuat.",
    "data": {
        "pertemuan": { "id": 18, "kelas_id": 5, "pertemuan_ke": 3 },
        "absensi": [
            {
                "id": null,
                "pertemuan_id": 18,
                "mahasiswa_id": 27,
                "status": null,
                "catatan": null,
                "is_submitted": false,
                "mahasiswa": { "id": 27, "nim": "2026001001", "nama_lengkap": "Mahasiswa" }
            }
        ]
    },
    "meta": { "current_page": 1, "per_page": 25, "total": 1, "last_page": 1, "from": 1, "to": 1 }
}
```

### [POST] `/api/v1/siakad/perkuliahan/pertemuan/{pertemuanId}/absensi`

Menyimpan status setiap mahasiswa yang dikirim; setiap status harus merupakan referensi `status_absensi` yang valid.

```json
{
    "absensi": [
        { "mahasiswa_id": 27, "status": "hadir", "catatan": null }
    ]
}
```

```json
{
    "status": "success",
    "message": "Data absensi mahasiswa berhasil disimpan."
}
```

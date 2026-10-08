# MahasiswaController

> **Modul**: SIAKAD / **Base URL**: `/api/v1/siakad/mahasiswa` / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-09-25
> **Diperbarui**: 2026-10-01 — NIM prefix per prodi (`prefix_nim`)
> **Diperbarui**: 2026-10-07 — pemisah `kelas` mahasiswa + plotting PA per kelas (`assign-pa-kelas`)

Data mahasiswa, pembuatan NIM, sinkronisasi SPMB/Feeder, konversi transfer, dan penugasan Pembimbing Akademik. Validasi `program_studi_id` mengacu ke `siakad_program_studi`.

## Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ untuk POST/PUT/PATCH |

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/siakad/mahasiswa/profil` | Profil mahasiswa login | ✅ |
| PUT | `/api/v1/siakad/mahasiswa/profil` | Perbarui profil mahasiswa | ✅ |
| POST | `/api/v1/siakad/mahasiswa/profil/sync-feeder` | Kirim profil ke Neo Feeder | ✅ |
| GET | `/api/v1/siakad/mahasiswa` | Daftar mahasiswa berpaginasi | ✅ |
| POST | `/api/v1/siakad/mahasiswa` | Tambah mahasiswa | ✅ |
| POST | `/api/v1/siakad/mahasiswa/generate-nim` | Generate NIM mahasiswa | ✅ |
| POST | `/api/v1/siakad/mahasiswa/generate-missing-nims` | Generate NIM massal | ✅ |
| POST | `/api/v1/siakad/mahasiswa/import-nim` | Import hasil pemetaan NIM dari file CSV | ✅ |
| POST | `/api/v1/siakad/mahasiswa/sync-from-spmb` | Sinkronisasi dari pendaftar SPMB | ✅ |
| GET | `/api/v1/siakad/mahasiswa/konversi` | Daftar konversi transfer | ✅ |
| GET | `/api/v1/siakad/mahasiswa/konversi/{id}` | Detail konversi transfer + rincian MK | ✅ |
| POST | `/api/v1/siakad/mahasiswa/konversi` | Tambah konversi transfer | ✅ |
| PUT | `/api/v1/siakad/mahasiswa/konversi/{id}` | Perbarui usulan konversi (rincian diganti penuh) | ✅ |
| PATCH | `/api/v1/siakad/mahasiswa/konversi/{id}/status` | Ubah status konversi | ✅ |
| DELETE | `/api/v1/siakad/mahasiswa/konversi/{id}` | Hapus konversi | ✅ |
| POST | `/api/v1/siakad/mahasiswa/bulk-assign-pa` | Penugasan PA massal | ✅ |
| POST | `/api/v1/siakad/mahasiswa/assign-pa-kelas` | Penugasan PA per kelas | ✅ |
| POST | `/api/v1/siakad/mahasiswa/auto-distribute-pa` | Distribusi PA merata | ✅ |
| GET | `/api/v1/siakad/mahasiswa/{id}` | Detail mahasiswa | ✅ |
| PUT | `/api/v1/siakad/mahasiswa/{id}` | Perbarui mahasiswa | ✅ |
| DELETE | `/api/v1/siakad/mahasiswa/{id}` | Hapus mahasiswa (soft delete) | ✅ |

---

## [GET] /api/v1/siakad/mahasiswa

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari `nim` / `nama_lengkap` |
| `program_studi_id` | integer | ❌ | — | Filter program studi |
| `angkatan` | integer | ❌ | — | Filter angkatan |
| `kelas` | string | ❌ | — | Filter/search kelas mahasiswa (format `25A` = 2 digit angkatan + huruf; search memakai LIKE) |
| `sort_by` | string | ❌ | `created_at` | `created_at`, `updated_at`, `nama_lengkap`, `nim`, `id`, `kelas` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": [
        { "id": 1, "nim": "20260001", "nama_lengkap": "Budi Santoso", "program_studi_id": 7, "angkatan": 2026, "status": "aktif" }
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

## [POST] /api/v1/siakad/mahasiswa

### Request Body

```json
{
    "nama_lengkap": "Budi Santoso",
    "nik": "3201010101010001",
    "program_studi_id": 7,
    "angkatan": 2026,
    "jenis_kelamin": "L",
    "tanggal_lahir": "2008-01-15",
    "kelas": "25A"
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Mahasiswa berhasil ditambahkan.",
    "data": { "id": 1, "nim": "20260001", "nama_lengkap": "Budi Santoso", "program_studi_id": 7 }
}
```

### Response Error

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": { "program_studi_id": ["The selected program studi id is invalid."] }
}
```

---

## [GET] /api/v1/siakad/mahasiswa/{id}

**200 OK**
```json
{ "status": "success", "data": { "id": 1, "nim": "20260001", "nama_lengkap": "Budi Santoso" } }
```

**404 Not Found**
```json
{ "status": "error", "message": "No query results for model [App\\Models\\Siakad\\Mahasiswa] 99." }
```

---

## [GET] /api/v1/siakad/mahasiswa/konversi/{id}

> Detail satu usulan konversi transfer beserta rincian penyetaraan MK (`details.mata_kuliah_diakui`).
> Dosen murni hanya dapat melihat usulan mahasiswa bimbingannya (`dosen_wali_id`); selain itu 403.

#### Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ |
| `Accept` | `application/json` | ✅ |

**200 OK**
```json
{
    "status": "success",
    "message": "Detail konversi transfer berhasil dimuat",
    "data": {
        "id": 1,
        "no_transaksi": "KNV-2026-001",
        "mahasiswa_id": 5,
        "kampus_asal": "Universitas Nusantara",
        "prodi_asal": "Teknik Komputer",
        "status": "diajukan",
        "catatan": null,
        "mahasiswa": { "id": 5, "nim": "20260001", "nama_lengkap": "Budi Santoso" },
        "details": [
            {
                "id": 11,
                "kode_mk_asal": "CS101",
                "nama_mk_asal": "Dasar Pemrograman",
                "sks_asal": 3,
                "nilai_huruf_asal": "A",
                "status": "diakui",
                "mata_kuliah_diakui": { "id": 45, "kode_mk": "IF101", "nama": "Pengantar Informatika" }
            }
        ]
    }
}
```

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
    "message": "Anda tidak memiliki akses ke usulan konversi mahasiswa ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Konversi transfer tidak ditemukan."
}
```

---

## [PUT] /api/v1/siakad/mahasiswa/konversi/{id}

> Perbarui usulan konversi (dipakai halaman edit). Rincian `details` diganti penuh:
> baris lama dihapus lalu dibuat ulang dari payload. Aturan kunci status `disetujui`
> dan penentuan status otomatis sama seperti endpoint POST.

#### Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "kampus_asal": "Universitas Nusantara",
    "prodi_asal": "Teknik Komputer",
    "catatan": "Revisi mapping MK",
    "status": "diajukan",
    "details": [
        {
            "mata_kuliah_diakui_id": 45,
            "kode_mk_asal": "CS101",
            "nama_mk_asal": "Dasar Pemrograman",
            "sks_asal": 3,
            "nilai_huruf_asal": "A"
        }
    ]
}
```

**200 OK**
```json
{
    "status": "success",
    "message": "Konversi transfer nilai mahasiswa berhasil disimpan",
    "data": {
        "id": 1
    }
}
```

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
    "message": "Konversi sudah disetujui dan tidak dapat diubah."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Konversi transfer tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "The given data was invalid.",
    "errors": {
        "kampus_asal": [
            "Perguruan tinggi asal wajib diisi."
        ],
        "details": [
            "Daftar mata kuliah penyetaraan minimal 1 baris."
        ],
        "details.0.mata_kuliah_diakui_id": [
            "Mata kuliah kurikulum lokal wajib dipilih."
        ]
    }
}
```

---

---

## [POST] /api/v1/siakad/mahasiswa/assign-pa-kelas

> Menetapkan satu Dosen PA untuk seluruh mahasiswa aktif pada kelas tertentu
> (cth `25A`), opsional dibatasi per program studi. Hanya untuk
> superadmin/admin/kaprodi/wakil_prodi (selain itu 403). Perubahan dicatat ke
> `audit_logs` (modul `SIAKAD`, action `update`, tabel `siakad_mahasiswa`).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "kelas": "25A",
    "program_studi_id": 7,
    "dosen_wali_id": 3,
    "hanya_belum_punya_pa": true
}
```

| Field | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `kelas` | string | ✅ | — | Kode kelas format `^[0-9]{2}[A-Z]{1,3}$` (cth `25A`; 422 bila salah format) |
| `program_studi_id` | integer | ❌ | — | Batas prodi, `exists:siakad_program_studi,id` |
| `dosen_wali_id` | integer | ✅ | — | Dosen PA tujuan, `exists:siakad_dosen,id` |
| `hanya_belum_punya_pa` | boolean | ❌ | `true` | `true` = hanya mahasiswa tanpa PA; `false` = timpa semua di kelas |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Berhasil menetapkan Dr. Andi sebagai Dosen PA untuk 30 mahasiswa kelas 25A.",
    "data": {
        "updated_count": 30,
        "dosen_wali": { "id": 3, "nama_lengkap": "Dr. Andi" },
        "kelas": "25A"
    }
}
```

### Response Error

**403 Forbidden**
```json
{ "status": "error", "message": "Plotting PA hanya untuk BAAK/Kaprodi." }
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "The given data was invalid.",
    "errors": { "kelas": ["The kelas field is required."] }
}
```

---

## [POST] /api/v1/siakad/mahasiswa/auto-distribute-pa

> Mendistribusikan mahasiswa aktif yang belum memiliki Dosen PA secara merata
> (round-robin) ke daftar dosen terpilih. Mendukung pembatasan prodi,
> angkatan, dan kelas (`kelas` format `^[0-9]{2}[A-Z]{1,3}$`, cth `25A`).
> Hanya untuk superadmin/admin/kaprodi/wakil_prodi (selain itu 403).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "dosen_ids": [3, 5],
    "program_studi_id": 7,
    "angkatan": 2025,
    "kelas": "25A"
}
```

| Field | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `dosen_ids` | array | ✅ | — | Daftar id dosen PA, tiap item `exists:siakad_dosen,id`, min 1 |
| `program_studi_id` | integer | ❌ | — | Batas prodi, `exists:siakad_program_studi,id` |
| `angkatan` | integer | ❌ | — | Batas tahun angkatan |
| `kelas` | string | ❌ | — | Batas kelas format `^[0-9]{2}[A-Z]{1,3}$` (cth `25A`; 422 bila salah format) |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Berhasil mendistribusikan 30 mahasiswa secara merata kepada 2 Dosen PA terpilih.",
    "data": {
        "assigned_count": 30,
        "dosen_count": 2
    }
}
```

### Response Error

**403 Forbidden**
```json
{ "status": "error", "message": "Plotting PA hanya untuk BAAK/Kaprodi." }
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Tidak ditemukan mahasiswa aktif yang belum memiliki Dosen PA pada kriteria ini."
}
```

---

## [POST] /api/v1/siakad/mahasiswa/import-nim

Deskripsi: Mengunggah berkas CSV pemetaan NIM mahasiswa untuk memperbarui NIM secara massal. Mendukung auto-detection delimiter (koma `,` dan titik koma `;` untuk Excel Indonesia) serta pembersihan UTF-8 BOM otomatis.

### Headers
- `Authorization: Bearer <access_token>`
- `Accept: application/json`

### Request Body (`multipart/form-data`)
| Field | Type | Required | Deskripsi |
|---|---|---|---|
| `file` | file | ✅ | Berkas CSV/TXT pemetaan NIM (maks. 5MB) |

Format kolom CSV:
`ID, Nama, Prodi, NIM Baru` (atau dipisahkan titik koma `;`)

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Berhasil memperbarui NIM untuk 25 mahasiswa.",
    "data": {
        "updated_count": 25,
        "errors": []
    }
}
```

### Response Error (422 Unprocessable Entity)
```json
{
    "status": "error",
    "message": "Gagal membaca file CSV."
}
```

---

### Catatan Tambahan

> - Format NIM otomatis mengikuti `siakad_program_studi.prefix_nim`:
>   - Jika prodi punya `prefix_nim` (mis. Otomotif = `A`): `{PREFIX}{2-digit tahun}{3-digit urut}`, cth `A25001`, `A25002`.
>   - Jika kosong: fallback standar `{2-digit tahun}{2-digit prodiId}{4-digit urut}`, cth `25010001`.
>   - Atur via `PUT /api/v1/siakad/akademik/prodi/{id}` dengan body `{"prefix_nim": "A"}` (huruf/angka, maks. 10, unik).
>   - Berlaku untuk `POST /generate-nim`, `POST /generate-missing-nims`, dan konversi otomatis SPMB (`sync-from-spmb` / event daftar-ulang lunas).
>   - Untuk format bebas sekali pakai tetap bisa via `custom_nim` (satuan) atau kolom `NIM_BARU` pada Export/Import CSV massal.
> - Perubahan data mahasiswa dicatat oleh observer audit (`MahasiswaObserver`, modul `SIAKAD`).
> - Field `kelas` (maks. 10, cth `25A`) dinormalisasi uppercase-trim saat simpan
>   (`store`/`update`) dan saat filter (`index` `?kelas=`, `auto-distribute-pa`).
>   Konversi SPMB mengisi `kelas` hanya bila data pendaftaran memiliki info
>   kelas yang relevan; saat ini skema pendaftaran belum memilikinya sehingga
>   dibiarkan null (tidak dikarang).
> - Soft delete + restore berlaku pada data mahasiswa.
> - Password/token tidak pernah dikembalikan pada response.

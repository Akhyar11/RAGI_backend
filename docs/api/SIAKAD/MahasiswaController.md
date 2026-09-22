# MahasiswaController

> **Modul**: SIAKAD / **Base URL**: `/api/v1/siakad/mahasiswa` / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-09-25

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
| POST | `/api/v1/siakad/mahasiswa/sync-from-spmb` | Sinkronisasi dari pendaftar SPMB | ✅ |
| GET | `/api/v1/siakad/mahasiswa/konversi` | Daftar konversi transfer | ✅ |
| POST | `/api/v1/siakad/mahasiswa/konversi` | Tambah konversi transfer | ✅ |
| PATCH | `/api/v1/siakad/mahasiswa/konversi/{id}/status` | Ubah status konversi | ✅ |
| DELETE | `/api/v1/siakad/mahasiswa/konversi/{id}` | Hapus konversi | ✅ |
| POST | `/api/v1/siakad/mahasiswa/bulk-assign-pa` | Penugasan PA massal | ✅ |
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
| `sort_by` | string | ❌ | `created_at` | `created_at`, `updated_at`, `nama_lengkap`, `nim`, `id` |
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
    "tanggal_lahir": "2008-01-15"
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

### Catatan Tambahan

> - Perubahan data mahasiswa dicatat oleh observer audit (`MahasiswaObserver`, modul `SIAKAD`).
> - Soft delete + restore berlaku pada data mahasiswa.
> - Password/token tidak pernah dikembalikan pada response.

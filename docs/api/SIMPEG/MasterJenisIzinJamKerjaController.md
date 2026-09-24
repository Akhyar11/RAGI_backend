# MasterJenisIzinJamKerjaController

> **Modul**: SIMPEG / **Base URL**: `/api/simpeg/master/jenis-izin-jam-kerja` / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-09-22

Dokumentasi API CRUD data master jenis izin jam kerja di SIMPEG.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/simpeg/master/jenis-izin-jam-kerja` | Daftar master jenis izin jam kerja berpaginasi | ✅ User / Admin SIMPEG |
| POST | `/api/simpeg/master/jenis-izin-jam-kerja` | Menambah master jenis izin jam kerja | ✅ Admin SIMPEG |
| GET | `/api/simpeg/master/jenis-izin-jam-kerja/{id}` | Detail master jenis izin jam kerja | ✅ User / Admin SIMPEG |
| PUT | `/api/simpeg/master/jenis-izin-jam-kerja/{id}` | Memperbarui master jenis izin jam kerja | ✅ Admin SIMPEG |
| DELETE | `/api/simpeg/master/jenis-izin-jam-kerja/{id}` | Menghapus master jenis izin jam kerja | ✅ Admin SIMPEG |

---

## Headers Standar

| Header | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ⚠️ (POST / PUT) |

---

## 1. GET /api/simpeg/master/jenis-izin-jam-kerja

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | - | Pencarian nama, kode, atau deskripsi |
| `tipe_potongan` | string | ❌ | - | Filter tipe potongan (`tidak_ada`, `potong_gaji`, `potong_tunjangan`, `potong_jam_kerja`) |
| `is_active` | boolean | ❌ | - | Filter status aktif (`true` / `false` / `1` / `0`) |
| `sort_by` | string | ❌ | `created_at` | Whitelist kolom (`created_at`, `updated_at`, `id`, `nama`, `kode`, `tipe_potongan`, `urutan`) |
| `sort_order` | string | ❌ | `desc` | Arah urutan (`asc` / `desc`) |
| `per_page` | integer | ❌ | `15` | Data per halaman (default 15, maks 100) |
| `page` | integer | ❌ | `1` | Nomor halaman paginasi (default 1) |

### Response 200 OK
```json
{
  "status": "success",
  "message": "Data retrieved successfully",
  "data": [
    {
      "id": 1,
      "kode": "IZ-MED",
      "nama": "Izin Berobat",
      "tipe_potongan": "tidak_ada",
      "maks_jam_per_hari": 4,
      "is_active": true,
      "urutan": 1,
      "deskripsi": "Izin pemeriksaan medis",
      "created_at": "2026-09-22T08:00:00.000000Z",
      "updated_at": "2026-09-22T08:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 1,
    "last_page": 1,
    "from": 1,
    "to": 1
  },
  "filters": {
    "search": null,
    "sort_by": "created_at",
    "sort_order": "desc"
  }
}
```

---

## 2. POST /api/simpeg/master/jenis-izin-jam-kerja

### Request Body
```json
{
  "nama": "Izin Keperluan Keluarga",
  "kode": "IZ-KEL",
  "tipe_potongan": "potong_jam_kerja",
  "maks_jam_per_hari": 3,
  "is_active": true,
  "urutan": 2,
  "deskripsi": "Izin mendesak keluarga"
}
```

### Response 201 Created
```json
{
  "status": "success",
  "message": "Master jenis izin jam kerja berhasil ditambahkan",
  "data": {
    "id": 2,
    "nama": "Izin Keperluan Keluarga",
    "kode": "IZ-KEL",
    "tipe_potongan": "potong_jam_kerja",
    "maks_jam_per_hari": 3,
    "is_active": true,
    "urutan": 2,
    "deskripsi": "Izin mendesak keluarga",
    "created_at": "2026-09-22T10:00:00.000000Z",
    "updated_at": "2026-09-22T10:00:00.000000Z"
  }
}
```

---

## 3. GET /api/simpeg/master/jenis-izin-jam-kerja/{id}

### Response 200 OK
```json
{
  "status": "success",
  "message": "Data retrieved successfully",
  "data": {
    "id": 1,
    "kode": "IZ-MED",
    "nama": "Izin Berobat",
    "tipe_potongan": "tidak_ada",
    "maks_jam_per_hari": 4,
    "is_active": true,
    "urutan": 1,
    "deskripsi": "Izin pemeriksaan medis",
    "created_at": "2026-09-22T08:00:00.000000Z",
    "updated_at": "2026-09-22T08:00:00.000000Z"
  }
}
```

---

## 4. PUT /api/simpeg/master/jenis-izin-jam-kerja/{id}

### Request Body
```json
{
  "nama": "Izin Berobat Diperbarui",
  "kode": "IZ-MED",
  "tipe_potongan": "tidak_ada",
  "maks_jam_per_hari": 5,
  "is_active": true,
  "urutan": 1,
  "deskripsi": "Izin berobat dokter spesialis"
}
```

### Response 200 OK
```json
{
  "status": "success",
  "message": "Master jenis izin jam kerja berhasil diperbarui",
  "data": {
    "id": 1,
    "kode": "IZ-MED",
    "nama": "Izin Berobat Diperbarui",
    "tipe_potongan": "tidak_ada",
    "maks_jam_per_hari": 5,
    "is_active": true,
    "urutan": 1,
    "deskripsi": "Izin berobat dokter spesialis",
    "created_at": "2026-09-22T08:00:00.000000Z",
    "updated_at": "2026-09-22T10:30:00.000000Z"
  }
}
```

---

## 5. DELETE /api/simpeg/master/jenis-izin-jam-kerja/{id}

### Response 200 OK
```json
{
  "status": "success",
  "message": "Master jenis izin jam kerja berhasil dihapus",
  "data": {
    "id": 2,
    "deleted_at": "2026-09-22T11:00:00.000000Z"
  }
}
```

---

## Standar Response Error

### 401 Unauthorized
```json
{
  "status": "error",
  "message": "Unauthenticated."
}
```

### 403 Forbidden
```json
{
  "status": "error",
  "message": "Anda tidak memiliki hak akses untuk melihat master jenis izin jam kerja."
}
```

### 404 Not Found
```json
{
  "status": "error",
  "message": "Master jenis izin jam kerja tidak ditemukan"
}
```

### 422 Unprocessable Content
```json
{
  "status": "error",
  "message": "Validasi gagal",
  "errors": {
    "nama": [
      "Nama jenis izin wajib diisi."
    ],
    "id": [
      "Tidak dapat menghapus jenis izin jam kerja karena masih digunakan pada data permohonan izin pegawai."
    ]
  }
}
```

---

## Catatan Arsitektur & Keamanan
- **Integritas Relasi / Soft Delete**: Sebelum penghapusan data, dilakukan verifikasi `withCount('izinJamKerja')`. Jika data digunakan pada relasi permohonan izin, sistem menolak dengan HTTP 422.
- **Audit Logging**: Operasi CUD dicatat via `AuditLogService::record` (modul `SIMPEG`).
- **Proteksi Data**: Tidak ada password / kredensial pengguna yang dikembalikan pada respons JSON.

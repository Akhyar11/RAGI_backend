# MasterJenisTesController

> **Modul**: SIMPEG / **Base URL**: `/api/simpeg/master/jenis-tes` / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-09-22

Dokumentasi API CRUD data master jenis tes kompetensi di SIMPEG.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/simpeg/master/jenis-tes` | Daftar master jenis tes berpaginasi | ✅ User / Admin SIMPEG |
| POST | `/api/simpeg/master/jenis-tes` | Menambah master jenis tes | ✅ Admin SIMPEG |
| GET | `/api/simpeg/master/jenis-tes/{id}` | Detail master jenis tes | ✅ User / Admin SIMPEG |
| PUT | `/api/simpeg/master/jenis-tes/{id}` | Memperbarui master jenis tes | ✅ Admin SIMPEG |
| DELETE | `/api/simpeg/master/jenis-tes/{id}` | Menghapus master jenis tes | ✅ Admin SIMPEG |

---

## Headers Standar

| Header | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ⚠️ (POST / PUT) |

---

## 1. GET /api/simpeg/master/jenis-tes

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | - | Pencarian nama, kode, kategori, atau deskripsi |
| `kategori` | string | ❌ | - | Filter kategori tes (`bahasa`, `potensi_akademik`, `psikotes`, `lainnya`) |
| `is_active` | boolean | ❌ | - | Filter status aktif (`true` / `false` / `1` / `0`) |
| `sort_by` | string | ❌ | `created_at` | Whitelist kolom (`created_at`, `updated_at`, `id`, `nama`, `kode`, `kategori`, `skor_min`, `skor_max`) |
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
      "kode": "TOEFL-ITP",
      "nama": "TOEFL ITP",
      "kategori": "bahasa",
      "skor_min": 310,
      "skor_max": 677,
      "deskripsi": "Test of English as a Foreign Language",
      "is_active": true,
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

## 2. POST /api/simpeg/master/jenis-tes

### Request Body
```json
{
  "nama": "IELTS Academic",
  "kode": "IELTS",
  "kategori": "bahasa",
  "skor_min": 0,
  "skor_max": 9,
  "deskripsi": "International English Language Testing System",
  "is_active": true
}
```

### Response 201 Created
```json
{
  "status": "success",
  "message": "Master jenis tes berhasil ditambahkan",
  "data": {
    "id": 2,
    "nama": "IELTS Academic",
    "kode": "IELTS",
    "kategori": "bahasa",
    "skor_min": 0,
    "skor_max": 9,
    "deskripsi": "International English Language Testing System",
    "is_active": true,
    "created_at": "2026-09-22T09:00:00.000000Z",
    "updated_at": "2026-09-22T09:00:00.000000Z"
  }
}
```

---

## 3. GET /api/simpeg/master/jenis-tes/{id}

### Response 200 OK
```json
{
  "status": "success",
  "message": "Data retrieved successfully",
  "data": {
    "id": 1,
    "kode": "TOEFL-ITP",
    "nama": "TOEFL ITP",
    "kategori": "bahasa",
    "skor_min": 310,
    "skor_max": 677,
    "deskripsi": "Test of English as a Foreign Language",
    "is_active": true,
    "created_at": "2026-09-22T08:00:00.000000Z",
    "updated_at": "2026-09-22T08:00:00.000000Z"
  }
}
```

---

## 4. PUT /api/simpeg/master/jenis-tes/{id}

### Request Body
```json
{
  "nama": "TOEFL ITP Resmi ETS",
  "kode": "TOEFL-ITP",
  "kategori": "bahasa",
  "skor_min": 310,
  "skor_max": 677,
  "deskripsi": "TOEFL ITP resmi ETS",
  "is_active": true
}
```

### Response 200 OK
```json
{
  "status": "success",
  "message": "Master jenis tes berhasil diperbarui",
  "data": {
    "id": 1,
    "kode": "TOEFL-ITP",
    "nama": "TOEFL ITP Resmi ETS",
    "kategori": "bahasa",
    "skor_min": 310,
    "skor_max": 677,
    "deskripsi": "TOEFL ITP resmi ETS",
    "is_active": true,
    "created_at": "2026-09-22T08:00:00.000000Z",
    "updated_at": "2026-09-22T10:00:00.000000Z"
  }
}
```

---

## 5. DELETE /api/simpeg/master/jenis-tes/{id}

### Response 200 OK
```json
{
  "status": "success",
  "message": "Master jenis tes berhasil dihapus",
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
  "message": "Anda tidak memiliki hak akses untuk melihat master jenis tes."
}
```

### 404 Not Found
```json
{
  "status": "error",
  "message": "Master jenis tes tidak ditemukan"
}
```

### 422 Unprocessable Content
```json
{
  "status": "error",
  "message": "Validasi gagal",
  "errors": {
    "nama": [
      "Nama jenis tes wajib diisi."
    ],
    "id": [
      "Data jenis tes masih digunakan pada data riwayat tes pegawai."
    ]
  }
}
```

---

## Catatan Arsitektur & Keamanan
- **Integritas Relasi / Soft Delete**: Sebelum penghapusan data, dilakukan verifikasi `withCount('riwayatTes')`. Jika data digunakan pada relasi riwayat tes pegawai, sistem menolak dengan HTTP 422.
- **Audit Logging**: Operasi CUD dicatat via `AuditLogService::record` (modul `SIMPEG`).
- **Proteksi Data**: Tidak ada password / kredensial pengguna yang dikembalikan pada respons JSON.

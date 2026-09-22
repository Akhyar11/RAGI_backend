# MasterTingkatKegiatanController

> **Modul**: SIMPEG / **Base URL**: `/api/simpeg/master/tingkat-kegiatan` / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-09-22

Dokumentasi API CRUD data master tingkat jangkauan kegiatan pelatihan di SIMPEG.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/simpeg/master/tingkat-kegiatan` | Daftar master tingkat kegiatan berpaginasi | ✅ User / Admin SIMPEG |
| POST | `/api/simpeg/master/tingkat-kegiatan` | Menambah master tingkat kegiatan | ✅ Admin SIMPEG |
| GET | `/api/simpeg/master/tingkat-kegiatan/{id}` | Detail master tingkat kegiatan | ✅ User / Admin SIMPEG |
| PUT | `/api/simpeg/master/tingkat-kegiatan/{id}` | Memperbarui master tingkat kegiatan | ✅ Admin SIMPEG |
| DELETE | `/api/simpeg/master/tingkat-kegiatan/{id}` | Menghapus master tingkat kegiatan | ✅ Admin SIMPEG |

---

## Headers Standar

| Header | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ⚠️ (POST / PUT) |

---

## 1. GET /api/simpeg/master/tingkat-kegiatan

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | - | Pencarian nama atau deskripsi tingkat |
| `is_active` | boolean | ❌ | - | Filter status aktif (`true` / `false` / `1` / `0`) |
| `sort_by` | string | ❌ | `created_at` | Whitelist kolom (`created_at`, `updated_at`, `id`, `nama`) |
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
      "nama": "Nasional",
      "deskripsi": "Kegiatan berskala nasional",
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

## 2. POST /api/simpeg/master/tingkat-kegiatan

### Request Body
```json
{
  "nama": "Internasional",
  "deskripsi": "Kegiatan berskala global",
  "is_active": true
}
```

### Response 201 Created
```json
{
  "status": "success",
  "message": "Master tingkat kegiatan berhasil ditambahkan",
  "data": {
    "id": 2,
    "nama": "Internasional",
    "deskripsi": "Kegiatan berskala global",
    "is_active": true,
    "created_at": "2026-09-22T09:00:00.000000Z",
    "updated_at": "2026-09-22T09:00:00.000000Z"
  }
}
```

---

## 3. GET /api/simpeg/master/tingkat-kegiatan/{id}

### Response 200 OK
```json
{
  "status": "success",
  "message": "Data retrieved successfully",
  "data": {
    "id": 1,
    "nama": "Nasional",
    "deskripsi": "Kegiatan berskala nasional",
    "is_active": true,
    "created_at": "2026-09-22T08:00:00.000000Z",
    "updated_at": "2026-09-22T08:00:00.000000Z"
  }
}
```

---

## 4. PUT /api/simpeg/master/tingkat-kegiatan/{id}

### Request Body
```json
{
  "nama": "Nasional Terakreditasi",
  "deskripsi": "Kegiatan nasional terakreditasi Dikti",
  "is_active": true
}
```

### Response 200 OK
```json
{
  "status": "success",
  "message": "Master tingkat kegiatan berhasil diperbarui",
  "data": {
    "id": 1,
    "nama": "Nasional Terakreditasi",
    "deskripsi": "Kegiatan nasional terakreditasi Dikti",
    "is_active": true,
    "created_at": "2026-09-22T08:00:00.000000Z",
    "updated_at": "2026-09-22T10:00:00.000000Z"
  }
}
```

---

## 5. DELETE /api/simpeg/master/tingkat-kegiatan/{id}

### Response 200 OK
```json
{
  "status": "success",
  "message": "Master tingkat kegiatan berhasil dihapus",
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
  "message": "Anda tidak memiliki hak akses untuk melihat master tingkat kegiatan."
}
```

### 404 Not Found
```json
{
  "status": "error",
  "message": "Master tingkat kegiatan tidak ditemukan"
}
```

### 422 Unprocessable Content
```json
{
  "status": "error",
  "message": "Validasi gagal",
  "errors": {
    "nama": [
      "Nama tingkat kegiatan wajib diisi."
    ],
    "id": [
      "Data tingkat kegiatan masih digunakan pada data riwayat pelatihan pegawai."
    ]
  }
}
```

---

## Catatan Arsitektur & Keamanan
- **Integritas Relasi / Soft Delete**: Sebelum penghapusan data, dilakukan verifikasi `withCount('riwayatPelatihan')`. Jika data digunakan pada relasi riwayat pelatihan pegawai, sistem menolak dengan HTTP 422.
- **Audit Logging**: Operasi CUD dicatat via `AuditLogService::record` (modul `SIMPEG`).
- **Proteksi Data**: Tidak ada password / kredensial pengguna yang dikembalikan pada respons JSON.

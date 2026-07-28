---
name: api-documentation
description: Standar pembuatan dokumentasi API di folder docs/ dengan format per-controller menggunakan Markdown. Aktifkan skill ini setiap kali membuat controller baru atau memodifikasi endpoint yang sudah ada.
---

# Standar Dokumentasi API

Setiap Controller yang dibuat di proyek ini **WAJIB** memiliki file dokumentasi di folder `docs/api/`. Dokumentasi ditulis dalam format **Markdown** dan terorganisir per Controller.

---

## 1. Struktur Direktori Docs

```
docs/
├── README.md                    ← Indeks semua dokumentasi API
└── api/
    ├── IAM/
    │   ├── AuthController.md
    │   └── UserController.md
    ├── SPMB/
    │   ├── PendaftaranController.md
    │   └── HasilSeleksiController.md
    └── SIAKAD/
        ├── KrsController.md
        └── NilaiController.md
```

Kelompokkan dokumentasi berdasarkan **modul** yang sama dengan struktur Controller.

---

## 2. Template File Dokumentasi Controller

Gunakan template berikut untuk **setiap** file dokumentasi controller:

````markdown
# [Nama Controller]

> **Modul**: [Nama Modul]  
> **Base URL**: `/api/[prefix]`  
> **Autentikasi**: Bearer Token (Sanctum) — kecuali dinyatakan lain  
> **Dibuat**: [Tanggal]  
> **Diperbarui**: [Tanggal]

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/users` | Daftar semua user | ✅ Admin |
| POST | `/api/users` | Buat user baru | ✅ Admin |
| GET | `/api/users/{id}` | Detail user | ✅ Admin |
| PUT | `/api/users/{id}` | Update user | ✅ Admin |
| DELETE | `/api/users/{id}` | Hapus user (soft delete) | ✅ Admin |

---

## [METHOD] /api/endpoint

> Deskripsi singkat apa yang dilakukan endpoint ini.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | Hanya POST/PUT |

### Query Parameters (untuk GET dengan filter)

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari berdasarkan nama/email |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan |
| `sort_order` | string | ❌ | `desc` | Arah urutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Request Body (untuk POST/PUT)

```json
{
    "username": "string, required, unique",
    "email": "string, required, format email",
    "password": "string, required (min: 8), only for store",
    "phone": "string, nullable",
    "user_type": "enum: mahasiswa|dosen|tendik|admin|calon_mhs"
}
```

### Response Sukses

**200 OK / 201 Created**
```json
{
    "status": "success",
    "message": "User created successfully",
    "data": {
        "id": 1,
        "username": "budi.santoso",
        "email": "budi@kampus.ac.id",
        "phone": "081234567890",
        "user_type": "mahasiswa",
        "is_active": true,
        "is_verified": false,
        "last_login_at": null,
        "created_at": "2026-07-28T14:00:00.000000Z",
        "updated_at": "2026-07-28T14:00:00.000000Z"
    }
}
```

**Response dengan Pagination (untuk GET list)**
```json
{
    "status": "success",
    "message": "Data retrieved successfully",
    "data": [...],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 100,
        "last_page": 7,
        "from": 1,
        "to": 15
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "User tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "email": ["Email sudah digunakan."],
        "username": ["Username wajib diisi."]
    }
}
```

### Catatan Tambahan

> Tambahkan catatan khusus di sini, misal:
> - Endpoint ini akan otomatis melakukan soft-delete (data tidak benar-benar terhapus).
> - Field `password` tidak akan dikembalikan dalam response apapun.
````

---

## 3. File `docs/README.md` — Indeks Utama

File indeks **WAJIB** diperbarui setiap kali ada controller baru:

````markdown
# Dokumentasi API — Integrated Sistem Backend

> **Base URL**: `http://localhost:8000/api`  
> **Format**: JSON  
> **Autentikasi**: Bearer Token (Laravel Sanctum)

## Modul IAM & Auth Center

| Controller | Deskripsi | Dokumen |
|---|---|---|
| AuthController | Login, register, logout, refresh token | [docs/api/IAM/AuthController.md](api/IAM/AuthController.md) |
| UserController | CRUD manajemen pengguna | [docs/api/IAM/UserController.md](api/IAM/UserController.md) |

## Modul SPMB

| Controller | Deskripsi | Dokumen |
|---|---|---|
| — | Belum diimplementasikan | — |
````

---

## 4. Aturan Wajib

1. **Setiap Controller WAJIB punya satu file `.md`** di folder `docs/api/{Modul}/`.
2. **File docs WAJIB dibuat/diperbarui** saat Controller baru dibuat atau endpoint dimodifikasi.
3. **Semua contoh response** di dokumentasi harus menunjukkan struktur nyata dari API, bukan placeholder fiktif.
4. **`docs/README.md`** sebagai indeks utama **WAJIB** selalu diperbarui dengan link ke file dokumentasi baru.
5. **Tandai endpoint** yang tidak memerlukan autentikasi dengan: `Auth: ❌ Publik`.
6. Gunakan **emoji status** yang konsisten di tabel:
   - `✅` = Diperlukan / Tersedia
   - `❌` = Tidak diperlukan / Tidak tersedia
   - `⚠️` = Kondisional / Perlu perhatian

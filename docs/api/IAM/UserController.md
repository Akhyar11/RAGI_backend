# UserController

> **Modul**: IAM & Auth Center  
> **Base URL**: `/api/users`  
> **Autentikasi**: Bearer Token (Passport) — Semua endpoint  
> **Otorisasi**: Hanya `user_type = admin`  
> **Dibuat**: 2026-07-28  
> **Diperbarui**: 2026-07-28

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth | Role |
|---|---|---|---|---|
| GET | `/api/users` | Daftar semua pengguna | ✅ | Admin |
| POST | `/api/users` | Buat pengguna baru | ✅ | Admin |
| GET | `/api/users/{id}` | Detail pengguna | ✅ | Admin |
| PUT | `/api/users/{id}` | Perbarui data pengguna | ✅ | Admin |
| DELETE | `/api/users/{id}` | Hapus pengguna (soft delete) | ✅ | Admin |

---

## GET /api/users

> Mengembalikan daftar semua pengguna dengan pagination.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari berdasarkan username atau email |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan: `username`, `email`, `user_type`, `created_at` |
| `sort_order` | string | ❌ | `desc` | Arah urutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |
| `user_type` | string | ❌ | — | Filter berdasarkan tipe: `mahasiswa`, `dosen`, `tendik`, `admin`, `calon_mhs` |

### Response Sukses

**200 OK**
```json
{
    "current_page": 1,
    "data": [
        {
            "id": 1,
            "username": "budi.santoso",
            "email": "budi@kampus.ac.id",
            "phone": "081234567890",
            "user_type": "mahasiswa",
            "is_active": true,
            "is_verified": true,
            "last_login_at": "2026-07-28T14:05:00.000000Z",
            "created_at": "2026-07-28T14:00:00.000000Z",
            "updated_at": "2026-07-28T14:00:00.000000Z"
        }
    ],
    "first_page_url": "http://localhost:8000/api/users?page=1",
    "last_page": 7,
    "per_page": 15,
    "total": 100
}
```

### Response Error

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Unauthorized action. Only admins can access this resource."
}
```

---

## POST /api/users

> Membuat pengguna baru. Hanya dapat dilakukan oleh Admin.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "username": "siti.rahayu",
    "email": "siti@kampus.ac.id",
    "password": "rahasia123",
    "password_confirmation": "rahasia123",
    "phone": "082345678901",
    "user_type": "dosen",
    "is_active": true,
    "is_verified": true
}
```

| Field | Type | Required | Validasi |
|---|---|---|---|
| `username` | string | ✅ | Unik |
| `email` | string | ✅ | Format email valid, unik |
| `password` | string | ✅ | Minimum 8 karakter |
| `password_confirmation` | string | ✅ | Harus sama dengan `password` |
| `phone` | string | ❌ | — |
| `user_type` | string | ✅ | Enum: `mahasiswa`, `dosen`, `tendik`, `admin`, `calon_mhs` |
| `is_active` | boolean | ❌ | Default: `true` |
| `is_verified` | boolean | ❌ | Default: `false` |

### Response Sukses

**201 Created**
```json
{
    "message": "User created successfully",
    "data": {
        "id": 42,
        "username": "siti.rahayu",
        "email": "siti@kampus.ac.id",
        "phone": "082345678901",
        "user_type": "dosen",
        "is_active": true,
        "is_verified": true,
        "last_login_at": null,
        "created_at": "2026-07-28T15:00:00.000000Z",
        "updated_at": "2026-07-28T15:00:00.000000Z"
    }
}
```

---

## GET /api/users/{id}

> Mengembalikan detail satu pengguna berdasarkan ID.

### Path Parameters

| Parameter | Type | Deskripsi |
|---|---|---|
| `id` | integer | ID pengguna |

### Response Sukses

**200 OK**
```json
{
    "data": {
        "id": 42,
        "username": "siti.rahayu",
        "email": "siti@kampus.ac.id",
        "phone": "082345678901",
        "user_type": "dosen",
        "is_active": true,
        "is_verified": true,
        "last_login_at": null,
        "created_at": "2026-07-28T15:00:00.000000Z",
        "updated_at": "2026-07-28T15:00:00.000000Z"
    }
}
```

### Response Error

**404 Not Found**
```json
{
    "status": "error",
    "message": "User tidak ditemukan."
}
```

---

## PUT /api/users/{id}

> Memperbarui data pengguna. Semua field bersifat opsional (partial update).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "username": "siti.rahayu.updated",
    "user_type": "tendik",
    "is_active": false
}
```

| Field | Type | Required | Validasi |
|---|---|---|---|
| `username` | string | ❌ | Unik kecuali milik user sendiri |
| `email` | string | ❌ | Format email, unik kecuali milik user sendiri |
| `password` | string | ❌ | Minimum 8 karakter |
| `password_confirmation` | string | ⚠️ | Wajib jika `password` diisi |
| `phone` | string | ❌ | — |
| `user_type` | string | ❌ | Enum valid |
| `is_active` | boolean | ❌ | — |
| `is_verified` | boolean | ❌ | — |

### Response Sukses

**200 OK**
```json
{
    "message": "User updated successfully",
    "data": {
        "id": 42,
        "username": "siti.rahayu.updated",
        "user_type": "tendik",
        "is_active": false,
        "updated_at": "2026-07-28T16:00:00.000000Z"
    }
}
```

---

## DELETE /api/users/{id}

> Melakukan **soft delete** pada pengguna. Data tidak benar-benar dihapus dari database, hanya ditandai `deleted_at`.

### Path Parameters

| Parameter | Type | Deskripsi |
|---|---|---|
| `id` | integer | ID pengguna yang akan dihapus |

### Response Sukses

**200 OK**
```json
{
    "message": "User deleted successfully"
}
```

> ⚠️ Pengguna yang sudah dihapus tidak akan muncul di endpoint `GET /api/users` dan tidak bisa login. Data tetap tersimpan di database dengan kolom `deleted_at` berisi timestamp penghapusan.

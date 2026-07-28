# AuthController

> **Modul**: IAM & Auth Center  
> **Base URL**: `/api/auth`  
> **Autentikasi**: Bearer Token (Sanctum) — kecuali dinyatakan lain  
> **Dibuat**: 2026-07-28  
> **Diperbarui**: 2026-07-28

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| POST | `/api/auth/register` | Mendaftarkan akun baru | ❌ Publik |
| POST | `/api/auth/login` | Login dan mendapatkan token | ❌ Publik |
| GET | `/api/auth/me` | Melihat profil pengguna aktif | ✅ Required |
| POST | `/api/auth/logout` | Menghapus token aktif (logout) | ✅ Required |
| POST | `/api/auth/logout-all` | Logout dari semua perangkat & app | ✅ Required |
| POST | `/api/auth/change-password` | Ganti password + paksa login ulang | ✅ Required |

---

## POST /api/auth/register

> Mendaftarkan akun pengguna baru ke sistem. Setelah berhasil, secara otomatis mengembalikan Bearer Token untuk langsung digunakan.

### Headers

| Key | Value | Required |
|---|---|---|
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "username": "budi.santoso",
    "email": "budi@kampus.ac.id",
    "password": "rahasia123",
    "password_confirmation": "rahasia123",
    "phone": "081234567890",
    "user_type": "mahasiswa"
}
```

| Field | Type | Required | Validasi |
|---|---|---|---|
| `username` | string | ✅ | Unik di tabel users |
| `email` | string | ✅ | Format email valid, unik di tabel users |
| `password` | string | ✅ | Minimum 8 karakter |
| `password_confirmation` | string | ✅ | Harus sama dengan `password` |
| `phone` | string | ❌ | — |
| `user_type` | string | ✅ | Enum: `mahasiswa`, `dosen`, `tendik`, `admin`, `calon_mhs` |

### Response Sukses

**201 Created**
```json
{
    "message": "User registered successfully",
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
    },
    "access_token": "1|aBcDeFgHiJkLmNoPqRsTuVwXyZ...",
    "token_type": "Bearer"
}
```

### Response Error

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "email": ["Email sudah digunakan."],
        "username": ["Username sudah digunakan."]
    }
}
```

---

## POST /api/auth/login

> Melakukan autentikasi pengguna dan mengembalikan Bearer Token. Token ini digunakan pada semua endpoint yang memerlukan autentikasi.

### Headers

| Key | Value | Required |
|---|---|---|
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "email": "budi@kampus.ac.id",
    "password": "rahasia123"
}
```

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `email` | string | ✅ | Email terdaftar |
| `password` | string | ✅ | Password akun |

### Response Sukses

**200 OK**
```json
{
    "message": "Login successful",
    "data": {
        "id": 1,
        "username": "budi.santoso",
        "email": "budi@kampus.ac.id",
        "user_type": "mahasiswa",
        "is_active": true,
        "last_login_at": "2026-07-28T14:05:00.000000Z"
    },
    "access_token": "2|aBcDeFgHiJkLmNoPqRsTuVwXyZ...",
    "token_type": "Bearer"
}
```

### Response Error

**422 Unprocessable Entity** — Kredensial salah
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": ["Kredensial yang diberikan salah."]
    }
}
```

> ⚠️ Jika akun `is_active = false`, login akan ditolak dengan pesan *"Akun Anda tidak aktif."*

---

## GET /api/auth/me

> Mengembalikan data profil pengguna yang sedang login berdasarkan token aktif.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses

**200 OK**
```json
{
    "data": {
        "id": 1,
        "username": "budi.santoso",
        "email": "budi@kampus.ac.id",
        "phone": "081234567890",
        "user_type": "mahasiswa",
        "is_active": true,
        "is_verified": false,
        "last_login_at": "2026-07-28T14:05:00.000000Z",
        "created_at": "2026-07-28T14:00:00.000000Z",
        "updated_at": "2026-07-28T14:05:00.000000Z"
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

---

## POST /api/auth/logout

> Menghapus token aktif milik pengguna (invalidate current token). Setelah logout, token yang digunakan tidak bisa dipakai lagi.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses

**200 OK**
```json
{
    "message": "Successfully logged out"
}
```

> 💡 Endpoint ini hanya menghapus token yang **sedang digunakan**. Token lain (dari perangkat/sesi lain) tidak terpengaruh.

---

## POST /api/auth/logout-all

> Menghapus **seluruh** Sanctum token dan SSO token milik user yang sedang login.
> Digunakan saat user ingin keluar dari semua perangkat sekaligus — misalnya saat akun dicurigai diakses orang lain.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Berhasil logout dari semua perangkat dan aplikasi."
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

> ⚠️ Setelah endpoint ini dipanggil, **semua token Sanctum dan SSO** milik user dihapus. User harus login ulang di semua perangkat dan aplikasi.

---

## POST /api/auth/change-password

> Mengganti password pengguna yang sedang login. Setelah berhasil, **seluruh token aktif** (Sanctum + SSO di semua aplikasi) otomatis dihapus sebagai langkah keamanan.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "current_password": "passwordlama123",
    "password": "passwordbaru456",
    "password_confirmation": "passwordbaru456"
}
```

| Field | Type | Required | Validasi |
|---|---|---|---|
| `current_password` | string | ✅ | Password yang sedang digunakan saat ini |
| `password` | string | ✅ | Password baru, min 8 karakter |
| `password_confirmation` | string | ✅ | Harus sama dengan `password` |

> ⚠️ `password` baru **tidak boleh sama** dengan `current_password`.

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Password berhasil diperbarui. Silakan login kembali di semua perangkat."
}
```

### Response Error

**422 Unprocessable Entity** — Password saat ini salah
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "current_password": ["Password saat ini tidak sesuai."]
    }
}
```

**422 Unprocessable Entity** — Password baru sama dengan password lama
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "password": ["The password field and current password must be different."]
    }
}
```

> 🔒 **Security:** Setelah ganti password berhasil, semua sesi aktif di seluruh perangkat dan aplikasi SSO langsung tidak valid. User **wajib login ulang** menggunakan password baru.

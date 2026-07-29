# AuthController

> **Modul**: IAM & Auth Center  
> **Base URL**: `/api/auth`  
> **Autentikasi**: Bearer Token (Passport) — kecuali dinyatakan lain  
> **Dibuat**: 2026-07-28  
> **Diperbarui**: 2026-07-28

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| POST | `/api/auth/register` | Mendaftarkan pengguna baru (Testing/Calon Mhs) | ❌ Publik |
| POST | `/api/auth/verify-email` | Verifikasi token dari email | ❌ Publik |
| POST | `/api/auth/login` | Autentikasi dengan email & password | ❌ Publik |
| POST | `/api/auth/mfa/login-verify`| Verifikasi TOTP untuk login tahap 2 | ❌ Publik |
| POST | `/api/auth/refresh` | Perbarui access token dengan refresh token | ❌ Publik |
| GET | `/api/auth/me` | Mendapatkan data pengguna yang sedang login | ✅ Passport |
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

### Response Sukses (201 Created)

```json
{
    "message": "User registered successfully. Silakan periksa email Anda untuk memverifikasi akun.",
    "data": {
        "username": "budi_calon",
        "email": "budi_calon@gmail.com",
        "user_type": "calon_mhs",
        "is_active": true,
        "is_verified": false,
        "id": 1,
        "email_verified_at": null,
        "created_at": "2026-07-28T04:23:36.000000Z",
        "updated_at": "2026-07-28T04:23:36.000000Z"
    },
    "access_token": "1|eyJ0eXA...",
    "token_type": "Bearer"
}
```

> **Catatan:** Setelah register berhasil, sistem akan mengantrekan pengiriman email ke background job (queue). Email berisi link verifikasi dengan token unik.

---

## POST /api/auth/verify-email

> Memverifikasi akun pengguna melalui token unik yang dikirimkan ke email mereka.

### Headers

| Key | Value | Required |
|---|---|---|
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "token": "token_unik_dari_email_..."
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Email berhasil diverifikasi."
}
```

### Response Error (400 Bad Request) - Token Salah/Expired

```json
{
    "status": "error",
    "message": "Token verifikasi tidak valid atau telah kedaluwarsa."
}
```

---

## POST /api/auth/login

> Endpoint utama untuk masuk ke sistem. Jika pengguna **tidak** mengaktifkan 2FA, mereka akan langsung mendapatkan `access_token`. Jika pengguna **telah mengaktifkan** 2FA, sistem akan meminta verifikasi langkah kedua (TOTP) dengan merespons status `requires_2fa: true`.

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

### Response Sukses (200 OK) - Tanpa 2FA

```json
{
    "status": "success",
    "message": "Login successful",
    "requires_2fa": false,
    "data": {
        "id": 1,
        "username": "admin",
        "email": "admin@kampus.ac.id",
        "user_type": "admin",
        "is_active": 1,
        "is_verified": 1,
        "last_login_at": "2026-07-28T04:25:10.000000Z"
    },
    "access_token": "2|eyJ0eXA...",
    "token_type": "Bearer"
}
```

### Response Sukses (200 OK) - Membutuhkan 2FA

```json
{
    "status": "success",
    "requires_2fa": true,
    "temp_token": "token_sementara_yg_random_...",
    "message": "Silakan masukkan kode TOTP dari aplikasi Authenticator Anda."
}
```

> **Catatan 2FA:** Jika Anda menerima respons `requires_2fa: true`, Anda **belum login secara penuh**. Frontend harus meminta kode 6 digit dari user dan memanggil endpoint `/api/auth/mfa/login-verify` dengan melampirkan `temp_token` ini.

### Response Error (422 Unprocessable Entity) - Validasi Gagal

```json
{
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "email": ["Kredensial yang diberikan salah."]
    }
}
```

---

## POST /api/auth/mfa/login-verify

> Melengkapi proses login dua langkah (2-Step Verification). Endpoint ini akan menukarkan `temp_token` dan `totp_code` menjadi `access_token` asli.

### Headers

| Key | Value | Required |
|---|---|---|
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "temp_token": "token_sementara_dari_response_login",
    "totp_code": "123456"
}
```

### Response Sukses (200 OK)

Mengembalikan payload yang persis sama dengan respons *Login sukses tanpa 2FA* (berisi `access_token`).

### Response Error

**400 Bad Request - Sesi Kedaluwarsa**
```json
{
    "status": "error",
    "message": "Sesi login telah kedaluwarsa. Silakan login ulang dengan password."
}
```

**422 Unprocessable Entity - TOTP Salah**
```json
{
    "status": "error",
    "message": "Kode TOTP tidak valid."
}
```

> ⚠️ Jika akun `is_active = false`, login akan ditolak dengan pesan *"Akun Anda tidak aktif."*

---

## POST /api/auth/refresh

> Digunakan untuk mendapatkan `access_token` baru dengan menggunakan `refresh_token` yang masih valid tanpa perlu login ulang (terutama untuk aplikasi Frontend pihak pertama / SPA / Mobile).

### Request Body

```json
{
    "refresh_token": "string, required"
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Token berhasil diperbarui",
    "data": {
        "access_token": "eyJ0eXAiOiJKV...",
        "refresh_token": "def50200...",
        "client_app": "spmb",
        "access_expires_at": "2026-07-29T16:00:00.000000Z",
        "refresh_expires_at": "2026-08-12T14:00:00.000000Z"
    }
}
```

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

> Menghapus **seluruh** Passport token dan SSO token milik user yang sedang login.
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

> ⚠️ Setelah endpoint ini dipanggil, **semua token Passport dan SSO** milik user dihapus. User harus login ulang di semua perangkat dan aplikasi.

---

## POST /api/auth/change-password

> Mengganti password pengguna yang sedang login. Setelah berhasil, **seluruh token aktif** (Passport + SSO di semua aplikasi) otomatis dihapus sebagai langkah keamanan.

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

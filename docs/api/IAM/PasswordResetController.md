# PasswordResetController

> **Modul**: IAM & Auth Center  
> **Base URL**: `/api/auth`  
> **Autentikasi**: ❌ Semua endpoint publik (tidak memerlukan token)  
> **Dibuat**: 2026-07-28  
> **Diperbarui**: 2026-07-28

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| POST | `/api/auth/forgot-password` | Kirim token reset ke email | ❌ Publik |
| POST | `/api/auth/reset-password` | Reset password dengan token | ❌ Publik |

> **Alur Reset Password:**
> 1. User kirim email ke `POST /auth/forgot-password`
> 2. Sistem generate token (berlaku 60 menit) → kirim ke email user
> 3. User klik link di email → dapat `token` dari URL
> 4. User kirim `email + token + password baru` ke `POST /auth/reset-password`
> 5. Sistem validasi token → update password → tandai token sebagai terpakai

---

## POST /api/auth/forgot-password

> Meminta pengiriman token reset password ke alamat email yang terdaftar.
> Untuk keamanan (mencegah **email enumeration attack**), endpoint selalu mengembalikan
> response sukses meskipun email tidak terdaftar.

### Headers

| Key | Value | Required |
|---|---|---|
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "email": "budi@kampus.ac.id"
}
```

| Field | Type | Required | Validasi |
|---|---|---|---|
| `email` | string | ✅ | Format email valid |

### Response Sukses

**200 OK** — Email ditemukan (environment lokal, token dikembalikan untuk kemudahan testing)
```json
{
    "status": "success",
    "message": "Link reset password telah dikirim ke email Anda.",
    "_dev_token": "aB3xKqL9mPzY2wVnRt7cFdHjMoQsUwYz..."
}
```

> ⚠️ Field `_dev_token` **hanya muncul di environment `local` dan `testing`**. Di production, token dikirim via email dan tidak dikembalikan di response.

**200 OK** — Email tidak ditemukan (response identik untuk keamanan)
```json
{
    "status": "success",
    "message": "Jika email terdaftar, link reset password akan dikirimkan."
}
```

### Response Error

**422 Unprocessable Entity** — Format email tidak valid
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "email": ["The email field must be a valid email address."]
    }
}
```

### Catatan Penting

> - Token reset berlaku selama **60 menit** sejak dibuat.
> - Token bersifat **single-use** — setelah dipakai untuk reset, token tidak dapat digunakan lagi.
> - Setiap permintaan baru akan **menghapus token lama** yang belum dipakai.
> - Akun dengan `is_active = false` tidak akan menerima email reset.

---

## POST /api/auth/reset-password

> Memproses penggantian password menggunakan token yang diterima dari email.
> Token yang sudah dipakai atau sudah expired akan ditolak.

### Headers

| Key | Value | Required |
|---|---|---|
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "email": "budi@kampus.ac.id",
    "token": "aB3xKqL9mPzY2wVnRt7cFdHjMoQsUwYz...",
    "password": "passwordbaru123",
    "password_confirmation": "passwordbaru123"
}
```

| Field | Type | Required | Validasi |
|---|---|---|---|
| `email` | string | ✅ | Format email valid |
| `token` | string | ✅ | Token yang diterima dari email |
| `password` | string | ✅ | Minimum 8 karakter |
| `password_confirmation` | string | ✅ | Harus sama dengan `password` |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Password berhasil diperbarui. Silakan login kembali."
}
```

### Response Error

**422 Unprocessable Entity** — Token tidak valid / sudah dipakai / expired
```json
{
    "status": "error",
    "message": "Token tidak valid, sudah digunakan, atau telah kedaluwarsa."
}
```

**422 Unprocessable Entity** — Validasi field gagal
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "password": ["The password field confirmation does not match."]
    }
}
```

### Catatan Penting

> - Setelah reset password berhasil, semua **token Sanctum yang aktif tidak otomatis dihapus**. User perlu login ulang secara manual untuk mendapatkan token baru.
> - Password lama **tidak dapat** digunakan untuk login setelah proses reset berhasil.
> - Token reset disimpan dalam bentuk **hash** di database — plain-text token hanya ada di email user dan tidak bisa diambil kembali dari sistem.

# MfaController

> **Modul**: IAM & Auth Center  
> **Base URL**: `/api/auth/mfa`  
> **Autentikasi**: Bearer Token (Passport)  
> **Dibuat**: 2026-07-29

Controller ini menangani logika terkait Autentikasi Dua Faktor (2FA / MFA) menggunakan metode TOTP (Time-Based One-Time Password) seperti Google Authenticator.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| POST | `/api/auth/mfa/setup` | Generate Secret Key & QR Code 2FA | ✅ Passport |
| POST | `/api/auth/mfa/verify` | Memverifikasi & mengaktifkan 2FA saat pertama kali | ✅ Passport |
| POST | `/api/auth/mfa/disable` | Menonaktifkan 2FA dengan password | ✅ Passport |

---

## POST /api/auth/mfa/setup

> Endpoint ini menghasilkan *Secret Key*, QR Code (format SVG), dan 8 buah *recovery codes*. 
> Endpoint ini hanya bisa dipanggil jika user **belum** mengaktifkan 2FA. Jika dipanggil ulang sebelum *verify*, endpoint akan mengembalikan secret dan kode yang sama.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {access_token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "data": {
        "secret": "A1B2C3D4E5F6G7H8...",
        "qr_code_svg": "PHN2ZyB4bWxuc... (base64 string)",
        "recovery_codes": [
            "a1b2c3d4e5",
            "f6g7h8i9j0",
            ...
        ]
    }
}
```

> **Catatan:**
> - `secret`: Teks rahasia yang bisa diinput manual di aplikasi Authenticator.
> - `qr_code_svg`: Base64 *encoded string* dari file SVG gambar QR code. Aplikasi frontend harus men-decode-nya (misalnya lewat `atob()`) atau merendernya di dalam tag `<img>` dengan format `data:image/svg+xml;base64,...`.
> - `recovery_codes`: Kode-kode yang harus disimpan user jika mereka kehilangan HP.

### Response Error (400 Bad Request) - Jika 2FA sudah aktif
```json
{
    "status": "error",
    "message": "2FA sudah aktif pada akun ini."
}
```

---

## POST /api/auth/mfa/verify

> Memverifikasi kode TOTP 6 digit dari aplikasi Authenticator untuk **meresmikan/mengaktifkan** fitur 2FA pada akun user (`two_factor_confirmed_at`).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {access_token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "totp_code": "123456"
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "2FA berhasil diverifikasi dan diaktifkan."
}
```

### Response Error

**422 Unprocessable Entity - Kode salah**
```json
{
    "status": "error",
    "message": "Kode TOTP tidak valid."
}
```

**400 Bad Request - Setup belum dilakukan**
```json
{
    "status": "error",
    "message": "Silakan panggil endpoint setup terlebih dahulu."
}
```

---

## POST /api/auth/mfa/disable

> Mematikan fitur 2FA. Memerlukan user untuk memasukkan password login mereka saat ini demi alasan keamanan.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {access_token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "password": "password_saya_saat_ini"
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "2FA berhasil dinonaktifkan."
}
```

### Response Error (422 Unprocessable Entity) - Password Salah

```json
{
    "status": "error",
    "message": "Password salah."
}
```

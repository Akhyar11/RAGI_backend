# SsoController

> **Modul**: IAM & Auth Center  
> **Base URL**: `/api/sso`  
> **Autentikasi**: Bervariasi per endpoint — lihat kolom Auth di tabel  
> **Dibuat**: 2026-07-28  
> **Diperbarui**: 2026-07-28

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| POST | `/api/sso/token` | Generate SSO token untuk client app | ✅ Sanctum |
| POST | `/api/sso/verify` | Verifikasi validitas access token | ❌ Publik (server-to-server) |
| POST | `/api/sso/refresh` | Tukar refresh token → token baru | ❌ Publik |
| POST | `/api/sso/revoke` | Cabut token SSO (logout dari app) | ✅ Sanctum |

> **Alur umum SSO:**
> 1. User login di IAM → dapat Sanctum token
> 2. User/App minta SSO token via `POST /sso/token`
> 3. IAM redirect ke `client_app` dengan `access_token` di query string
> 4. `client_app` verifikasi token via `POST /sso/verify`
> 5. Saat `access_token` expired, `client_app` tukar via `POST /sso/refresh`

---

## POST /api/sso/token

> Generate pasangan `access_token` dan `refresh_token` SSO untuk user yang sedang login, ditujukan ke `client_app` tertentu.
> Token lama untuk kombinasi `user + client_app` yang sama akan **dihapus otomatis**.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {sanctum_token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "client_app": "siakad"
}
```

| Field | Type | Required | Nilai yang Diizinkan |
|---|---|---|---|
| `client_app` | string | ✅ | `spmb`, `siakad`, `sikeu`, `simpeg`, `lms`, `sinapra`, `upm`, `kerjasama` |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "SSO token generated successfully",
    "data": {
        "access_token": "aB3xKqL9mPzY2wVnRt7...",
        "refresh_token": "hG5cJoE8dNuW1sXfIk4...",
        "client_app": "siakad",
        "access_expires_at": "2026-07-28T14:15:00.000000Z",
        "refresh_expires_at": "2026-08-27T14:00:00.000000Z"
    }
}
```

> ⚠️ `access_token` berlaku **15 menit**, `refresh_token` berlaku **30 hari**.

### Response Error

**401 Unauthorized** — Sanctum token tidak valid
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**422 Unprocessable Entity** — `client_app` tidak valid
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "client_app": ["The selected client app is invalid."]
    }
}
```

---

## POST /api/sso/verify

> Memverifikasi `access_token` SSO dan mengembalikan data user pemilik token.
> Endpoint ini **tidak memerlukan autentikasi Sanctum** — dirancang untuk dipanggil oleh server aplikasi klien (server-to-server).

### Headers

| Key | Value | Required |
|---|---|---|
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "access_token": "aB3xKqL9mPzY2wVnRt7...",
    "client_app": "siakad"
}
```

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `access_token` | string | ✅ | Token yang diterima dari redirect IAM |
| `client_app` | string | ✅ | Nama aplikasi yang memverifikasi (harus sama dengan saat generate) |

### Response Sukses

**200 OK** — Token valid
```json
{
    "status": "success",
    "message": "Token valid",
    "data": {
        "valid": true,
        "expires_at": "2026-07-28T14:15:00.000000Z",
        "user": {
            "id": 42,
            "username": "budi.santoso",
            "email": "budi@kampus.ac.id",
            "user_type": "mahasiswa",
            "is_active": true
        }
    }
}
```

### Response Error

**401 Unauthorized** — Token tidak valid atau sudah expired
```json
{
    "status": "error",
    "message": "Token tidak valid atau sudah kedaluwarsa."
}
```

> ⚠️ Verifikasi token juga memastikan `client_app` cocok. Token dari `client_app=spmb` tidak bisa diverifikasi dengan `client_app=siakad`.

---

## POST /api/sso/refresh

> Menukar `refresh_token` yang masih valid dengan pasangan token baru (`access_token` + `refresh_token`).
> Token lama akan **dihapus** dan digantikan sepenuhnya.

### Headers

| Key | Value | Required |
|---|---|---|
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "refresh_token": "hG5cJoE8dNuW1sXfIk4..."
}
```

| Field | Type | Required |
|---|---|---|
| `refresh_token` | string | ✅ |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Token berhasil diperbarui",
    "data": {
        "access_token": "nEw3xKqL9mPzY2wVnRt...",
        "refresh_token": "nEwG5cJoE8dNuW1sXfI...",
        "client_app": "siakad",
        "access_expires_at": "2026-07-28T15:00:00.000000Z",
        "refresh_expires_at": "2026-08-27T14:30:00.000000Z"
    }
}
```

### Response Error

**401 Unauthorized** — Refresh token tidak valid atau expired
```json
{
    "status": "error",
    "message": "Refresh token tidak valid atau sudah kedaluwarsa."
}
```

---

## POST /api/sso/revoke

> Mencabut SSO token milik user yang sedang login — bisa untuk satu `client_app` tertentu, atau semua aplikasi sekaligus.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {sanctum_token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "client_app": "siakad"
}
```

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `client_app` | string | ❌ | Jika **diisi**, hanya cabut token untuk app tersebut. Jika **kosong**, cabut **semua** token SSO milik user. |

### Response Sukses

**200 OK** — Revoke untuk satu app
```json
{
    "status": "success",
    "message": "Berhasil logout dari aplikasi 'siakad'. 1 token dicabut."
}
```

**200 OK** — Revoke semua app
```json
{
    "status": "success",
    "message": "Berhasil logout dari semua aplikasi. 4 token dicabut."
}
```

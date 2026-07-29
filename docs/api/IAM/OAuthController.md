# OAuthController — SSO & OAuth2 Flow

> **Modul**: IAM & Auth Center  
> **Framework OAuth2**: Laravel Passport v13  
> **Dibuat**: 2026-07-29  
> **Diperbarui**: 2026-07-29

---

## Gambaran Umum

IAM berfungsi sebagai **OAuth2 Authorization Server** menggunakan Laravel Passport. Setiap aplikasi dalam ekosistem kampus (SIAKAD, SPMB, SIKEU, dll.) berperan sebagai **OAuth2 Client** yang menggunakan **Authorization Code Flow** untuk mengautentikasi pengguna.

```
[Aplikasi Klien]              [IAM — OAuth2 Server]
       │                              │
       │  GET /oauth/authorize        │
       │  ?client_id=...              │
       │  &redirect_uri=...           │
       │  &response_type=code         │
       │  &state=...                  │
       │─────────────────────────────►│
       │                              │
       │         (belum ada sesi)     │
       │◄── redirect ke /sso/login ───│
       │                              │
       │  [User input email+password] │
       │                              │
       │  POST /sso/login             │
       │─────────────────────────────►│
       │                              │── Auth::login() ──►
       │                              │◄── sesi web dibuat ─
       │                              │
       │◄── redirect ?code=AUTH_CODE ─│
       │                              │
       │  POST /oauth/token           │
       │  { code, client_secret, ... }│
       │─────────────────────────────►│
       │                              │
       │◄── { access_token, ... } ────│
       │                              │
       │  GET /api/auth/user          │
       │  Authorization: Bearer ...   │
       │─────────────────────────────►│
       │                              │
       │◄── { data: { user } } ───────│
```

---

## Daftar Endpoint

### Endpoint Kustom (OAuthController)

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/sso/login` | Tampilkan halaman login SSO | ❌ Publik |
| POST | `/sso/login` | Proses autentikasi user | ❌ Publik |
| GET | `/api/auth/user` | Ambil data user dari access token | ✅ Bearer (Passport) |

### Endpoint Passport (Otomatis)

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/oauth/authorize` | Halaman persetujuan OAuth2 | ⚠️ Sesi Web |
| POST | `/oauth/token` | Tukar authorization code → token | ❌ Publik (client credentials) |
| POST | `/oauth/token/refresh` | Perbarui access token dengan refresh token | ❌ Publik |

---

## Aplikasi Klien Terdaftar

| `client_app` | Nama Aplikasi | Redirect URI |
|---|---|---|
| `spmb` | Sistem Penerimaan Mahasiswa Baru | `https://spmb.kampus.ac.id/auth/callback` |
| `siakad` | Sistem Informasi Akademik | `https://siakad.kampus.ac.id/auth/callback` |
| `sikeu` | Sistem Informasi Keuangan | `https://sikeu.kampus.ac.id/auth/callback` |
| `simpeg` | Sistem Informasi Kepegawaian | `https://simpeg.kampus.ac.id/auth/callback` |
| `lms` | Learning Management System | `https://lms.kampus.ac.id/auth/callback` |
| `sinapra` | Sistem Informasi Anggaran & Prasarana | `https://sinapra.kampus.ac.id/auth/callback` |
| `upm` | Unit Penjaminan Mutu | `https://upm.kampus.ac.id/auth/callback` |

> `client_id` dan `client_secret` masing-masing aplikasi tersedia di tabel `oauth_clients` database. Simpan `client_secret` dengan aman — hanya digunakan di server, **jangan pernah expose ke browser**.

---

## Panduan Integrasi untuk Aplikasi Klien

### Langkah 1 — Redirect ke IAM

Saat user mengakses halaman yang membutuhkan autentikasi, redirect ke:

```
GET https://sso.kampus.ac.id/oauth/authorize
    ?client_id={CLIENT_ID}
    &redirect_uri=https://siakad.kampus.ac.id/auth/callback
    &response_type=code
    &scope=
    &state={RANDOM_STRING_CSRF}
```

| Parameter | Required | Keterangan |
|---|---|---|
| `client_id` | ✅ | UUID client dari tabel `oauth_clients` |
| `redirect_uri` | ✅ | Harus terdaftar di whitelist |
| `response_type` | ✅ | Selalu `code` |
| `scope` | ❌ | Kosongkan (semua scope tersedia) |
| `state` | ✅ | Random string untuk proteksi CSRF, simpan di sesi |

### Langkah 2 — Terima Authorization Code

Setelah user login, IAM redirect ke `redirect_uri`:

```
https://siakad.kampus.ac.id/auth/callback
    ?code=AUTH_CODE_DISINI
    &state=RANDOM_STRING_YANG_SAMA
```

> ⚠️ Validasi `state` di sisi klien sebelum lanjut — jika tidak cocok, tolak request (potensi CSRF).

### Langkah 3 — Tukar Code → Token

```http
POST https://sso.kampus.ac.id/oauth/token
Content-Type: application/json

{
    "grant_type": "authorization_code",
    "client_id": "{CLIENT_ID}",
    "client_secret": "{CLIENT_SECRET}",
    "redirect_uri": "https://siakad.kampus.ac.id/auth/callback",
    "code": "{AUTH_CODE}"
}
```

**Response:**
```json
{
    "token_type": "Bearer",
    "expires_in": 86400,
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGci...",
    "refresh_token": "def50200abc..."
}
```

| Field | Keterangan |
|---|---|
| `expires_in` | Durasi token dalam detik (86400 = 1 hari) |
| `access_token` | JWT token untuk request ke resource server |
| `refresh_token` | Token untuk memperbarui access token yang expired |

### Langkah 4 — Ambil Data User

```http
GET https://sso.kampus.ac.id/api/auth/user
Authorization: Bearer {ACCESS_TOKEN}
Accept: application/json
```

**Response:**
```json
{
    "status": "success",
    "data": {
        "id": 42,
        "username": "budi.santoso",
        "email": "budi@kampus.ac.id",
        "phone": "081234567890",
        "user_type": "mahasiswa",
        "is_active": true,
        "is_verified": true,
        "last_login_at": "2026-07-29T09:00:00.000000Z"
    }
}
```

---

## Referensi Endpoint Detail

### GET /sso/login

> Menampilkan halaman login SSO kustom. Dipanggil secara otomatis oleh Passport saat user mengakses `/oauth/authorize` tanpa sesi web aktif. Dapat juga diakses langsung via browser.

**Query Parameters (diteruskan dari `/oauth/authorize`):**

| Parameter | Keterangan |
|---|---|
| `client_id` | Client yang meminta akses |
| `redirect_uri` | URI tujuan setelah login |
| `response_type` | Tipe response OAuth2 |
| `state` | Token CSRF |

**Response:** Halaman HTML form login (Blade view)

---

### POST /sso/login

> Memproses autentikasi user. Jika berhasil, membuat sesi web Laravel dan redirect ke `/oauth/authorize` untuk melanjutkan Authorization Code Flow.

**Rate Limiting:** Maks 5 percobaan per menit per IP

**Headers:**
```
Content-Type: application/x-www-form-urlencoded
```

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `email` | string | ✅ | Email terdaftar |
| `password` | string | ✅ | Password akun |
| `_token` | string | ✅ | CSRF token Laravel |
| `_query` | string | ✅ | Query string dari OAuth authorize (disisipkan otomatis oleh form) |

**Response Sukses:**
```
HTTP 302 Found
Location: /oauth/authorize?client_id=...&redirect_uri=...
```

**Response Error:**
```
HTTP 302 Found
Location: /sso/login?...
```
*(Redirect balik ke halaman login dengan pesan error di sesi)*

> ⚠️ Endpoint ini **bukan JSON** — menggunakan form submission HTML standar dan browser redirect. Jangan panggil via `fetch()` atau `axios`.

---

### GET /api/auth/user

> Mengembalikan data profil user berdasarkan Bearer access token Passport. Endpoint ini berfungsi sebagai **resource server** yang dipanggil aplikasi klien setelah mendapat access token.

**Headers:**

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {access_token}` | ✅ |
| `Accept` | `application/json` | ✅ |

**Response Sukses:**

**200 OK**
```json
{
    "status": "success",
    "data": {
        "id": 42,
        "username": "budi.santoso",
        "email": "budi@kampus.ac.id",
        "phone": "081234567890",
        "user_type": "mahasiswa",
        "is_active": true,
        "is_verified": true,
        "last_login_at": "2026-07-29T09:00:00.000000Z"
    }
}
```

**Response Error:**

**401 Unauthorized** — Token tidak valid / expired
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

---

### POST /oauth/token

> Endpoint standar Passport untuk menukar authorization code dengan access token, atau memperbarui token menggunakan refresh token.

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Request Body — Authorization Code Grant:**
```json
{
    "grant_type": "authorization_code",
    "client_id": "019fabb1-9807-7074-92dd-cc157c1ed644",
    "client_secret": "SECRET_DARI_DATABASE",
    "redirect_uri": "https://siakad.kampus.ac.id/auth/callback",
    "code": "AUTH_CODE"
}
```

**Request Body — Refresh Token Grant:**
```json
{
    "grant_type": "refresh_token",
    "refresh_token": "def50200abc...",
    "client_id": "019fabb1-9807-7074-92dd-cc157c1ed644",
    "client_secret": "SECRET_DARI_DATABASE",
    "scope": ""
}
```

**Response Sukses:**
```json
{
    "token_type": "Bearer",
    "expires_in": 86400,
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGci...",
    "refresh_token": "def50200abc..."
}
```

**Response Error — Code sudah dipakai / expired:**
```json
{
    "error": "invalid_grant",
    "error_description": "The provided authorization grant is invalid...",
    "hint": "Authorization code has expired"
}
```

> 💡 Authorization code hanya berlaku **satu kali** dan kedaluwarsa dalam beberapa menit. Segera tukar setelah diterima.

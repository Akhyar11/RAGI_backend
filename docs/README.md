# Dokumentasi API — Integrated Sistem Backend

> **Base URL**: `http://localhost:8000`  
> **Format Response**: `application/json`  
> **OAuth2 Server**: Laravel Passport v13  
> **Versi**: 2.0.0 — SSO Ready

---

## 🔐 Modul IAM & Auth Center

| Controller | Deskripsi | Endpoint | Dokumen |
|---|---|---|---|
| `OAuthController` | SSO OAuth2 flow, halaman login, resource server | `GET/POST /sso/login`, `GET /api/auth/user`, `GET /oauth/authorize`, `POST /oauth/token` | [OAuthController.md](api/IAM/OAuthController.md) |
| `AuthController` | Register, login API, logout, change password | `/api/auth/*` | [AuthController.md](api/IAM/AuthController.md) |
| `PasswordResetController` | Lupa & reset password | `/api/auth/forgot-password`, `/api/auth/reset-password` | [PasswordResetController.md](api/IAM/PasswordResetController.md) |
| `SsoController` | SSO token kustom (kompatibilitas API/mobile) | `/api/sso/*` | [SsoController.md](api/IAM/SsoController.md) |
| `UserController` | CRUD manajemen pengguna (Admin) | `/api/users` | [UserController.md](api/IAM/UserController.md) |

---

## 📝 Modul SPMB

| Controller | Deskripsi | Dokumen |
|---|---|---|
| — | *Belum diimplementasikan* | — |

---

## 🚀 Server & Deployment

| Topik | Deskripsi | Dokumen |
|---|---|---|
| Konfigurasi Queue (Supervisor) | Cara menjalankan *background jobs* 24/7 di VPS/HestiaCP | [SERVER_SETUP.md](SERVER_SETUP.md) |

---

## 🎓 Modul SIAKAD

| Controller | Deskripsi | Dokumen |
|---|---|---|
| — | *Belum diimplementasikan* | — |

---

## 💰 Modul SIKEU

| Controller | Deskripsi | Dokumen |
|---|---|---|
| — | *Belum diimplementasikan* | — |

---

## 👥 Modul SIMPEG

| Controller | Deskripsi | Dokumen |
|---|---|---|
| — | *Belum diimplementasikan* | — |

---

## 📚 Modul LMS

| Controller | Deskripsi | Dokumen |
|---|---|---|
| — | *Belum diimplementasikan* | — |

---

## 📋 Referensi Cepat — Semua Endpoint IAM

### Authentication (Passport — OAuth2 Browser Flow)

| Method | Endpoint | Auth | Keterangan |
|---|---|---|---|
| GET | `/oauth/authorize` | Sesi Web | Mulai Authorization Code Flow |
| POST | `/oauth/token` | Client Credentials | Tukar code → access token |
| POST | `/oauth/token/refresh` | Client Credentials | Perbarui expired token |
| GET | `/sso/login` | ❌ Publik | Halaman login SSO (HTML) |
| POST | `/sso/login` | ❌ Publik | Proses login SSO (redirect) |
| GET | `/api/auth/user` | ✅ Bearer Passport | Data user dari resource server |

### Authentication (API — untuk Mobile/Non-Browser)

| Method | Endpoint | Auth | Keterangan |
|---|---|---|---|
| POST | `/api/auth/register` | ❌ Publik | Daftar akun baru |
| POST | `/api/auth/login` | ❌ Publik | Login → dapat token |
| GET | `/api/auth/me` | ✅ Bearer | Profil user aktif |
| POST | `/api/auth/logout` | ✅ Bearer | Logout perangkat ini |
| POST | `/api/auth/logout-all` | ✅ Bearer | Logout semua perangkat |
| POST | `/api/auth/change-password` | ✅ Bearer | Ganti password |
| POST | `/api/auth/forgot-password` | ❌ Publik | Kirim link reset |
| POST | `/api/auth/reset-password` | ❌ Publik | Reset password |

### SSO Token Kustom (Kompatibilitas Mundur — API/Mobile)

| Method | Endpoint | Auth | Keterangan |
|---|---|---|---|
| POST | `/api/sso/token` | ✅ Bearer | Generate SSO token |
| POST | `/api/sso/verify` | ❌ Publik | Verifikasi token (server-to-server) |
| POST | `/api/sso/refresh` | ❌ Publik | Perbarui token |
| POST | `/api/sso/revoke` | ✅ Bearer | Cabut token |

### User Management

| Method | Endpoint | Auth | Keterangan |
|---|---|---|---|
| GET | `/api/users` | ✅ Admin | Daftar semua user + filter + pagination |
| POST | `/api/users` | ✅ Admin | Buat user baru |
| GET | `/api/users/{id}` | ✅ Admin | Detail satu user |
| PUT | `/api/users/{id}` | ✅ Admin | Update user |
| DELETE | `/api/users/{id}` | ✅ Admin | Hapus user (soft delete) |

---

## 🔄 Alur SSO Lengkap (Browser)

```
[User buka siakad.kampus.ac.id]
          │
          ▼ (belum login)
[SIAKAD redirect ke IAM]
GET /oauth/authorize?client_id=...&redirect_uri=...&state=...
          │
          ▼
[IAM tampilkan /sso/login]
          │
          ▼ (user input email + password)
POST /sso/login
          │
          ▼ (login berhasil, sesi web dibuat)
[Passport generate authorization code]
Redirect → siakad.kampus.ac.id/auth/callback?code=...&state=...
          │
          ▼ (SIAKAD verifikasi state, tukar code)
POST /oauth/token { grant_type: "authorization_code", code, client_secret }
          │
          ▼
{ access_token, refresh_token, expires_in }
          │
          ▼ (SIAKAD ambil data user)
GET /api/auth/user
Authorization: Bearer {access_token}
          │
          ▼
{ data: { id, username, email, user_type, ... } }
          │
          ▼
✅ User masuk SIAKAD tanpa login ulang
```

---

## 🏗️ Konvensi Umum

### Headers Wajib
```
Accept: application/json
Content-Type: application/json          (POST/PUT)
Authorization: Bearer {access_token}    (endpoint terproteksi)
```

### Format Response API (JSON)

**Sukses:**
```json
{
    "status": "success",
    "message": "Pesan deskriptif",
    "data": { ... }
}
```

**Error:**
```json
{
    "status": "error",
    "message": "Pesan error",
    "errors": { "field": ["detail"] }
}
```

### HTTP Status Code

| Kode | Kondisi |
|---|---|
| `200` | Request berhasil |
| `201` | Resource berhasil dibuat |
| `401` | Token tidak valid / expired |
| `403` | Tidak memiliki izin |
| `404` | Resource tidak ditemukan |
| `422` | Validasi data gagal |
| `429` | Rate limit terlampaui |
| `500` | Error server internal |

### Rate Limiting

| Endpoint | Limit |
|---|---|
| `POST /api/auth/login` | 5x / menit per IP |
| `POST /sso/login` | 5x / menit per IP |
| `POST /api/auth/forgot-password` | 3x / 5 menit per IP |
| `POST /api/sso/verify` | 60x / menit per IP |
| API umum | 60x / menit per user/IP |

### Token Expiry

| Token | Durasi |
|---|---|
| Passport access token | 1 hari |
| Passport refresh token | 30 hari |
| SSO token kustom (access) | 15 menit |
| SSO token kustom (refresh) | 30 hari |
| Password reset token | 60 menit |

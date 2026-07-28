# Dokumentasi API — Integrated Sistem Backend

> **Base URL**: `http://localhost:8000/api`  
> **Format**: `application/json`  
> **Autentikasi**: Bearer Token (Laravel Sanctum)  
> **Versi**: 1.0.0

---

## 🔐 Modul IAM & Auth Center

| Controller | Deskripsi | Endpoint Utama | Dokumen |
|---|---|---|---|
| `AuthController` | Register, login, logout, profil | `/api/auth/*` | [AuthController.md](api/IAM/AuthController.md) |
| `UserController` | CRUD manajemen pengguna (Admin) | `/api/users` | [UserController.md](api/IAM/UserController.md) |
| `SsoController` | Manajemen SSO token antar aplikasi | `/api/sso/*` | [SsoController.md](api/IAM/SsoController.md) |
| `PasswordResetController` | Lupa & reset password | `/api/auth/forgot-password`, `/api/auth/reset-password` | [PasswordResetController.md](api/IAM/PasswordResetController.md) |

---

## 📝 Modul SPMB

| Controller | Deskripsi | Dokumen |
|---|---|---|
| — | *Belum diimplementasikan* | — |

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

## 📋 Konvensi Umum

### Headers Wajib
```
Accept: application/json
Content-Type: application/json          (untuk POST/PUT)
Authorization: Bearer {token}           (untuk endpoint terproteksi)
```

### Format Tanggal
Semua tanggal menggunakan **ISO 8601**: `2026-07-28T14:00:00.000000Z`

### Format Response Standar

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
    "errors": { ... }
}
```

### HTTP Status Code

| Kode | Kondisi |
|---|---|
| `200` | Request berhasil |
| `201` | Resource berhasil dibuat |
| `401` | Tidak terautentikasi / token expired |
| `403` | Tidak memiliki izin |
| `404` | Resource tidak ditemukan |
| `422` | Validasi data gagal |
| `500` | Error server internal |

---

## 🔄 Alur Autentikasi SSO

```
[User] → POST /api/auth/login → Dapat Sanctum Token
           │
           ▼
[User/App] → POST /api/sso/token { client_app: "siakad" } → Dapat SSO Token
           │
           ▼
[App SIAKAD] → POST /api/sso/verify { access_token, client_app } → Validasi
           │
           ▼
[Saat expired] → POST /api/sso/refresh { refresh_token } → Token baru
           │
           ▼
[Logout] → POST /api/sso/revoke { client_app? } → Token dicabut
```

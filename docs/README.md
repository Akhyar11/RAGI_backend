# Dokumentasi API — Integrated Sistem Backend

> **Base URL**: `http://localhost:8000`  
> **Format Response**: `application/json`  
> **OAuth2 Server**: Laravel Passport v13  
> **Versi**: 2.0.0 — SSO Ready

---

## 🔐 Modul IAM & Auth Center

| Controller | Deskripsi | Dokumen |
|---|---|---|
| AuthController | Login, register, logout, MFA, verifikasi | [docs/api/IAM/AuthController.md](api/IAM/AuthController.md) |
| UserController | CRUD pengguna, toggle status | [docs/api/IAM/UserController.md](api/IAM/UserController.md) |
| RoleController | CRUD peran (Role) pengguna | [docs/api/IAM/RoleController.md](api/IAM/RoleController.md) |
| PermissionController | CRUD hak akses sistem | [docs/api/IAM/PermissionController.md](api/IAM/PermissionController.md) |
| RoleAssignmentController | Pemetaan relasi User-Role dan Role-Permission | [docs/api/IAM/RoleAssignmentController.md](api/IAM/RoleAssignmentController.md) |
| UserSessionController | Pemantauan & pencabutan sesi pengguna | [docs/api/IAM/UserSessionController.md](api/IAM/UserSessionController.md) |
| AuditLogController | Rekaman log sistem (Akuntabilitas) | [docs/api/IAM/AuditLogController.md](api/IAM/AuditLogController.md) |

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
| SikeuMasterController | Master Tarif UKT, Tarif SPMB, Jalur Kelas, Jenis Biaya, & Beasiswa | [docs/api/Sikeu/SikeuMasterController.md](api/Sikeu/SikeuMasterController.md) |
| ExternalTagihanController | Penerbitan Tagihan Eksternal & Riwayat Pembayaran | [docs/api/Sikeu/ExternalTagihanController.md](api/Sikeu/ExternalTagihanController.md) |
| SpmBSikeuCallbackController | Webhook Callback Integrasi Pelunasan Biaya SPMB | [docs/api/Sikeu/SpmBSikeuCallbackController.md](api/Sikeu/SpmBSikeuCallbackController.md) |
| SpmbIntegration | Rangkuman Integrasi Tarif & Callback SPMB | [docs/api/Sikeu/SpmbIntegration.md](api/Sikeu/SpmbIntegration.md) |
| MahasiswaTagihanController | Portal Tagihan Mahasiswa Mandiri & Invoice | [docs/api/Sikeu/MahasiswaTagihanController.md](api/Sikeu/MahasiswaTagihanController.md) |
| DispensasiTagihanController | Permohonan Dispensasi Tagihan & Cetak Bukti | [docs/api/Sikeu/DispensasiTagihanController.md](api/Sikeu/DispensasiTagihanController.md) |
| TagihanApprovalController | Approval Pimpinan untuk Tagihan & Dispensasi | [docs/api/Sikeu/TagihanApprovalController.md](api/Sikeu/TagihanApprovalController.md) |
| UnitKasController | Master Unit Kas & Saldo Operasional | [docs/api/Sikeu/UnitKasController.md](api/Sikeu/UnitKasController.md) |
| PengajuanKasController | Pengajuan & Persetujuan Pencairan Kas Unit | [docs/api/Sikeu/PengajuanKasController.md](api/Sikeu/PengajuanKasController.md) |
| PemasukanKampusController | Pencatatan Pemasukan Hibah, Donatur, & Kerjasama | [docs/api/Sikeu/PemasukanKampusController.md](api/Sikeu/PemasukanKampusController.md) |
| AkuntansiController | Chart of Accounts (COA), Jurnal Umum, & Buku Besar | [docs/api/Sikeu/AkuntansiController.md](api/Sikeu/AkuntansiController.md) |
| PaymentGatewayConfigController | Pengaturan Provider Payment Gateway (Midtrans/Xendit) | [docs/api/Sikeu/PaymentGatewayConfigController.md](api/Sikeu/PaymentGatewayConfigController.md) |

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

## 🏢 Modul SINAPRA (Sarana, Prasarana, & Aset)

| Controller | Deskripsi | Dokumen |
|---|---|---|
| GedungRuanganController | Manajemen data master Gedung & Ruangan serta cek ketersediaan | [docs/api/SINAPRA/GedungRuanganController.md](api/SINAPRA/GedungRuanganController.md) |
| AsetController | Inventaris barang/aset, kategori, & kalkulasi penyusutan nilai buku | [docs/api/SINAPRA/AsetController.md](api/SINAPRA/AsetController.md) |
| PeminjamanController | Permohonan & persetujuan peminjaman ruangan & aset | [docs/api/SINAPRA/PeminjamanController.md](api/SINAPRA/PeminjamanController.md) |
| MaintenanceController | Tiket pelaporan & pelacakan perawatan/perbaikan barang & ruang | [docs/api/SINAPRA/MaintenanceController.md](api/SINAPRA/MaintenanceController.md) |
| PengadaanController | Pengajuan usulan pengadaan barang baru & rincian detail | [docs/api/SINAPRA/PengadaanController.md](api/SINAPRA/PengadaanController.md) |

---

## 🔬 Modul SIPPM

| Controller | Deskripsi | Dokumen |
|---|---|---|
| StandarIku5ProdiController | CRUD Nilai Target IKU 5 per Program Studi | [docs/api/Sippm/StandarIku5ProdiController.md](api/Sippm/StandarIku5ProdiController.md) |

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
| POST | `/api/auth/verify-email` | ❌ Publik | Verifikasi token dari email |
| POST | `/api/auth/login` | ❌ Publik | Login → dapat token |
| POST | `/api/auth/mfa/login-verify` | ❌ Publik | Verifikasi TOTP (login tahap 2) |
| GET | `/api/auth/me` | ✅ Bearer | Profil user aktif |
| POST | `/api/auth/logout` | ✅ Bearer | Logout perangkat ini |
| POST | `/api/auth/logout-all` | ✅ Bearer | Logout semua perangkat |
| POST | `/api/auth/change-password` | ✅ Bearer | Ganti password |
| POST | `/api/auth/forgot-password` | ❌ Publik | Kirim link reset |
| POST | `/api/auth/reset-password` | ❌ Publik | Reset password |
| POST | `/api/auth/mfa/setup` | ✅ Bearer | Generate Secret 2FA |
| POST | `/api/auth/mfa/verify` | ✅ Bearer | Aktifkan 2FA pertama kali |
| POST | `/api/auth/mfa/disable` | ✅ Bearer | Matikan 2FA (butuh password) |

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

### Role & Permission Management (RBAC)

| Method | Endpoint | Auth | Keterangan |
|---|---|---|---|
| GET | `/api/roles` | ✅ `roles.read` | Daftar semua role |
| POST | `/api/roles` | ✅ `roles.create`| Buat role baru & assign permission |
| GET | `/api/roles/{id}` | ✅ `roles.read` | Detail role |
| PUT | `/api/roles/{id}` | ✅ `roles.update`| Update role |
| DELETE | `/api/roles/{id}` | ✅ `roles.delete`| Hapus role |
| GET | `/api/permissions`| ✅ `roles.read` | Daftar semua permission |

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

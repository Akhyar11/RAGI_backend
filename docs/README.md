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

## ⚙️ Modul System (Referensi & Konfigurasi)

| Controller | Deskripsi | Dokumen |
|---|---|---|
| MasterReferensiController | Manajemen data item referensi umum, kode, modul, dan status aktif | [docs/api/System/MasterReferensiController.md](api/System/MasterReferensiController.md) |
| MasterTipeReferensiController | Manajemen kategori master tipe referensi sistem | [docs/api/System/MasterTipeReferensiController.md](api/System/MasterTipeReferensiController.md) |

---

## 📝 Modul SPMB

| Controller | Deskripsi | Dokumen |
|---|---|---|
| MasterSpmbController | Master referensi SPMB (Tahun Akademik, Jalur, Gelombang, Opsi, Rincian Beban Pendaftaran) | [docs/api/SPMB/MasterSpmbController.md](api/SPMB/MasterSpmbController.md) |
| SpmbKuotaProdiController | Manajemen kuota penerimaan calon mahasiswa baru per program studi | [docs/api/SPMB/SpmbKuotaProdiController.md](api/SPMB/SpmbKuotaProdiController.md) |
| MasterBiayaSpmbController | Master biaya SPMB per gelombang & program studi | [docs/api/SPMB/MasterBiayaSpmbController.md](api/SPMB/MasterBiayaSpmbController.md) |
| TarifUktSpmbController (DIHAPUS) | Pengganti: MasterBiayaSpmbController (beban pendaftaran/daftar ulang) | [docs/api/SPMB/TarifUktSpmbController.md](api/SPMB/TarifUktSpmbController.md) |
| CalonMahasiswaController | Alur pendaftaran calon mahasiswa (biodata, berkas, tagihan, VA) | [docs/api/SPMB/CalonMahasiswaController.md](api/SPMB/CalonMahasiswaController.md) |
| ReferralController | Kode referral mahasiswa baru: validasi, statistik mandiri & laporan | [docs/api/SPMB/ReferralController.md](api/SPMB/ReferralController.md) |
| DaftarUlangController | Tagihan & konfirmasi daftar ulang calon mahasiswa lulus | [docs/api/SPMB/DaftarUlangController.md](api/SPMB/DaftarUlangController.md) |
| LaporanSpmbController | Statistik & export laporan pendaftaran SPMB | [docs/api/SPMB/LaporanSpmbController.md](api/SPMB/LaporanSpmbController.md) |

---

## 🚀 Server & Deployment

| Topik | Deskripsi | Dokumen |
|---|---|---|
| Konfigurasi Queue (Supervisor) | Cara menjalankan *background jobs* 24/7 di VPS/HestiaCP | [SERVER_SETUP.md](SERVER_SETUP.md) |

---

## 🎓 Modul SIAKAD

| Controller | Deskripsi | Dokumen |
|---|---|---|
| AkademikController | Master akademik + opsi referensi dropdown (jenjang, akreditasi, tipe MK, mode penilaian) | [docs/api/SIAKAD/AkademikController.md](api/SIAKAD/AkademikController.md) |
| FeederSyncController | Sinkronisasi Neo Feeder PDDikti (Dosen, Penugasan, Ajar, Mahasiswa, Kelas) | [docs/api/SIAKAD/FeederSyncController.md](api/SIAKAD/FeederSyncController.md) |
| AkademikController | Master akademik (Tahun Akademik, Fakultas, Prodi, Kurikulum, Mata Kuliah, Dosen) | [docs/api/SIAKAD/AkademikController.md](api/SIAKAD/AkademikController.md) |
| MahasiswaController | Data mahasiswa, NIM, konversi transfer, dan penugasan PA | [docs/api/SIAKAD/MahasiswaController.md](api/SIAKAD/MahasiswaController.md) |
| ObeController | Kurikulum OBE (CPL/CPMK, Profil Lulusan, Bahan Kajian, RPS, Nilai) | [docs/api/SIAKAD/ObeController.md](api/SIAKAD/ObeController.md) |
| PerkuliahanController | Kelas, KRS, nilai, transkrip, pertemuan & absensi | [docs/api/SIAKAD/PerkuliahanController.md](api/SIAKAD/PerkuliahanController.md) |
| PaController | Pembimbing Akademik: Rekap bimbingan, catatan jurnal mahasiswa, aktivitas per kelas (SIMPA), & cetak PDF | [docs/api/SIAKAD/PaController.md](api/SIAKAD/PaController.md) |

---

## 💰 Modul SIKEU

| Controller | Deskripsi | Dokumen |
|---|---|---|
| SikeuMasterController | Master Tarif UKT, Tarif SPMB, Jalur Kelas, Jenis Biaya, & Beasiswa | [docs/api/SIKEU/SikeuMasterController.md](api/SIKEU/SikeuMasterController.md) |
| ExternalTagihanController | Penerbitan Tagihan Eksternal & Riwayat Pembayaran | [docs/api/SIKEU/ExternalTagihanController.md](api/SIKEU/ExternalTagihanController.md) |
| SpmBSikeuCallbackController | Webhook Callback Integrasi Pelunasan Biaya SPMB | [docs/api/SIKEU/SpmBSikeuCallbackController.md](api/SIKEU/SpmBSikeuCallbackController.md) |
| SpmbIntegration | Rangkuman Integrasi Tarif & Callback SPMB | [docs/api/SIKEU/SpmbIntegration.md](api/SIKEU/SpmbIntegration.md) |
| MahasiswaTagihanController | Portal Tagihan Mahasiswa Mandiri & Invoice | [docs/api/SIKEU/MahasiswaTagihanController.md](api/SIKEU/MahasiswaTagihanController.md) |
| PiutangMahasiswaController | Rekapitulasi Piutang Mahasiswa & Export Excel | [docs/api/SIKEU/PiutangMahasiswaController.md](api/SIKEU/PiutangMahasiswaController.md) |
| DispensasiTagihanController | Permohonan Dispensasi Tagihan & Cetak Bukti | [docs/api/SIKEU/DispensasiTagihanController.md](api/SIKEU/DispensasiTagihanController.md) |
| TagihanApprovalController | Approval Pimpinan untuk Tagihan & Dispensasi | [docs/api/SIKEU/TagihanApprovalController.md](api/SIKEU/TagihanApprovalController.md) |
| UnitKasController | Master Unit Kas & Saldo Operasional | [docs/api/SIKEU/UnitKasController.md](api/SIKEU/UnitKasController.md) |
| PengajuanKasController | Pengajuan pencairan kas operasional unit, panjar dinas, persetujuan & penolakan | [docs/api/SIKEU/PengajuanKasController.md](api/SIKEU/PengajuanKasController.md) |
| PemasukanKampusController | Pencatatan Pemasukan Hibah, Donatur, & Kerjasama | [docs/api/SIKEU/PemasukanKampusController.md](api/SIKEU/PemasukanKampusController.md) |
| AkuntansiController | Chart of Accounts (COA), Jurnal Umum, & Buku Besar | [docs/api/SIKEU/AkuntansiController.md](api/SIKEU/AkuntansiController.md) |
| PaymentGatewayConfigController | Pengaturan Provider Payment Gateway (Midtrans/Xendit) | [docs/api/SIKEU/PaymentGatewayConfigController.md](api/SIKEU/PaymentGatewayConfigController.md) |
| SettingTarifController | Konfigurasi Tarif Biaya per Angkatan, Prodi, & Semester | [docs/api/SIKEU/SettingTarifController.md](api/SIKEU/SettingTarifController.md) |
| PembayaranKasirController | Pembayaran Kasir/Loket Tunai & Non-Tunai, Koreksi Transaksi, & Tagihan Masal | [docs/api/SIKEU/PembayaranKasirController.md](api/SIKEU/PembayaranKasirController.md) |
| PembayaranMahasiswaTarifController | Tarif per angkatan & prodi, tagihan individu/massal (guard duplikat semester), & proteksi hapus per angkatan | [docs/api/SIKEU/PembayaranMahasiswaTarifController.md](api/SIKEU/PembayaranMahasiswaTarifController.md) |
| KasKecilController | Kas Kecil (Petty Cash): unit per fakultas, transaksi keluar, pengajuan/top-up & persetujuan dengan jurnal otomatis | [docs/api/SIKEU/KasKecilController.md](api/SIKEU/KasKecilController.md) |

---

## 👥 Modul SIMPEG

| Controller | Deskripsi | Dokumen |
|---|---|---|
| UnitKerjaController | Manajemen struktur organisasi, SOTK kampus, dan unit kerja induk-anak | [docs/api/SIMPEG/UnitKerjaController.md](api/SIMPEG/UnitKerjaController.md) |
| JabatanController | Formasi jabatan struktural, fungsional, dan teknis pada unit kerja | [docs/api/SIMPEG/JabatanController.md](api/SIMPEG/JabatanController.md) |
| JabatanFungsionalController | Master jenjang jabatan fungsional akademik dosen (termasuk Tenaga Pengajar) dan angka kredit | [docs/api/SIMPEG/JabatanFungsionalController.md](api/SIMPEG/JabatanFungsionalController.md) |
| RiwayatController | Riwayat penugasan jabatan struktural/fungsional dan riwayat pendidikan pegawai | [docs/api/SIMPEG/RiwayatController.md](api/SIMPEG/RiwayatController.md) |
| PegawaiController | Manajemen master data pegawai, multi-role SSO, import massal, & reset biometrik | [docs/api/SIMPEG/PegawaiController.md](api/SIMPEG/PegawaiController.md) |
| PresensiController | Presensi biometrik mobile (Android Flutter), validasi wajah Python port 8001, shift, geofence, & integrasi | [docs/api/SIMPEG/PresensiController.md](api/SIMPEG/PresensiController.md) |
| PresensiMasterSettingController | Master pengaturan presensi: parameter sistem, lokasi kantor, multi-tipe shift, & kalender libur | [docs/api/SIMPEG/PresensiMasterSettingController.md](api/SIMPEG/PresensiMasterSettingController.md) |
| AttendanceDataApi | Spesifikasi API integrasi data absensi & rekapitulasi kehadiran | [docs/api/SIMPEG/ATTENDANCE_DATA_API.md](api/SIMPEG/ATTENDANCE_DATA_API.md) |
| AttendanceApi | Spesifikasi teknis integrasi & sinkronisasi SIMPEG | [docs/api/SIMPEG/ATTENDANCE_API.md](api/SIMPEG/ATTENDANCE_API.md) |
| KompetensiController | Kompetensi dosen: Sertifikasi dosen, riwayat tes kemampuan, & pelatihan/diklat | [docs/api/SIMPEG/KompetensiController.md](api/SIMPEG/KompetensiController.md) |
| SuratTugasController | Surat tugas dinas luar, armada/driver, tim rombongan, persetujuan, auto-presensi, & LPJ | [docs/api/SIMPEG/SuratTugasController.md](api/SIMPEG/SuratTugasController.md) |
| IzinJamKerjaController | Izin parsial jam kerja (keluar kantor, datang terlambat, pulang awal) & integrasi presensi | [docs/api/SIMPEG/IzinJamKerjaController.md](api/SIMPEG/IzinJamKerjaController.md) |
| SkPegawaiController | Arsip & pelaporan SK mandiri dosen/tendik serta verifikasi dokumen SDM | [docs/api/SIMPEG/SkPegawaiController.md](api/SIMPEG/SkPegawaiController.md) |
| PenilaianKinerjaController | Sasaran Kinerja Pegawai (SKP) butir-per-butir & evaluasi capaian BKD | [docs/api/SIMPEG/PenilaianKinerjaController.md](api/SIMPEG/PenilaianKinerjaController.md) |
| TridharmaDossierController | Agregasi portofolio Tridharma terpadu (SIAKAD, SIPPM, SIMPEG) | [docs/api/SIMPEG/TridharmaDossierController.md](api/SIMPEG/TridharmaDossierController.md) |
| PayrollController | Penggajian fleksibel, master komponen insentif, honor SKS, PPh 21, & posting kas SIKEU | [docs/api/SIMPEG/PayrollController.md](api/SIMPEG/PayrollController.md) |
| PegawaiKomponenGajiController | Kustomisasi konfigurasi komponen gaji spesifik pegawai | [docs/api/SIMPEG/PegawaiKomponenGajiController.md](api/SIMPEG/PegawaiKomponenGajiController.md) |
| UsulanJafungController | Usulan kenaikan jabatan fungsional dosen, angka kredit & verifikasi SK | [docs/api/SIMPEG/UsulanJafungController.md](api/SIMPEG/UsulanJafungController.md) |
| CutiController | Pengajuan cuti pegawai terintegrasi, validasi durasi, approval & notifikasi | [docs/api/SIMPEG/CutiController.md](api/SIMPEG/CutiController.md) |
| DokumenController | E-File arsip dokumen kepegawaian, secure view & dynamic watermark | [docs/api/SIMPEG/DokumenController.md](api/SIMPEG/DokumenController.md) |
| MasterJenisSertifikasiController | Master referensi jenis sertifikasi profesi & keahlian dosen/tendik | [docs/api/SIMPEG/MasterJenisSertifikasiController.md](api/SIMPEG/MasterJenisSertifikasiController.md) |
| MasterJenisTesController | Master referensi jenis tes kompetensi resmi (TOEFL, TPA, dsb.) | [docs/api/SIMPEG/MasterJenisTesController.md](api/SIMPEG/MasterJenisTesController.md) |
| MasterJenisPelatihanController | Master klasifikasi pelatihan, diklat, dan bimbingan teknis pegawai | [docs/api/SIMPEG/MasterJenisPelatihanController.md](api/SIMPEG/MasterJenisPelatihanController.md) |
| MasterPeranPelatihanController | Master peran kepesertaan kegiatan (Peserta, Pemateri, Moderator, Panitia) | [docs/api/SIMPEG/MasterPeranPelatihanController.md](api/SIMPEG/MasterPeranPelatihanController.md) |
| MasterTingkatKegiatanController | Master tingkat jangkauan kegiatan (Lokal, Wilayah, Nasional, Internasional) | [docs/api/SIMPEG/MasterTingkatKegiatanController.md](api/SIMPEG/MasterTingkatKegiatanController.md) |
| MasterJenisIzinJamKerjaController | Master jenis dispensasi izin jam kerja, durasi toleransi & aturan potong | [docs/api/SIMPEG/MasterJenisIzinJamKerjaController.md](api/SIMPEG/MasterJenisIzinJamKerjaController.md) |
| MasterKategoriSkController | Master kategori nomor dan jenis Surat Keputusan (SK) pegawai | [docs/api/SIMPEG/MasterKategoriSkController.md](api/SIMPEG/MasterKategoriSkController.md) |
| MasterGolonganPangkatController | Master referensi jenjang golongan & pangkat kepegawaian (I/a s.d. IV/e) | [docs/api/SIMPEG/MasterGolonganPangkatController.md](api/SIMPEG/MasterGolonganPangkatController.md) |

---

## 📚 Modul LMS

| Controller | Deskripsi | Dokumen |
|---|---|---|
| — | *Belum diimplementasikan* | — |

---

## 🏢 Modul SINAPRA (Sarana, Prasarana, & Aset)

| Controller | Deskripsi | Dokumen |
|---|---|---|
| GedungRuanganController | Manajemen master Gedung, Ruangan (relasi tipe ruangan), penugasan laboran lab, & ketersediaan jam | [docs/api/SINAPRA/GedungRuanganController.md](api/SINAPRA/GedungRuanganController.md) |
| AsetController | Inventaris barang/aset, kategori, scoping laboran lab, & kalkulasi penyusutan nilai buku | [docs/api/SINAPRA/AsetController.md](api/SINAPRA/AsetController.md) |
| PeminjamanController | Alur persetujuan berjenjang peminjaman ruangan & aset (Laboran Lab & Admin SINAPRA) | [docs/api/SINAPRA/PeminjamanController.md](api/SINAPRA/PeminjamanController.md) |
| MaintenanceController | Tiket pelaporan, scoping kerusakan aset lab binaan, & pelacakan perbaikan sarpras | [docs/api/SINAPRA/MaintenanceController.md](api/SINAPRA/MaintenanceController.md) |
| PengadaanController | Usulan pengadaan barang baru, rincian item kebutuhan, & persetujuan status | [docs/api/SINAPRA/PengadaanController.md](api/SINAPRA/PengadaanController.md) |
| LaboratoriumController | Manajemen operasional lab: stok BHP, surat bebas tanggungan lab, & kalibrasi alat | [docs/api/SINAPRA/LaboratoriumController.md](api/SINAPRA/LaboratoriumController.md) |
| AuditMutasiDisposalController | Audit stock opname fisik, mutasi aset antar-ruang, & BAP pemutihan/penghapusan aset | [docs/api/SINAPRA/AuditMutasiDisposalController.md](api/SINAPRA/AuditMutasiDisposalController.md) |
| KalenderRuanganController | Kalender visual & timeline ketersediaan ruangan terpadu (SINAPRA + SIAKAD) | [docs/api/SINAPRA/KalenderRuanganController.md](api/SINAPRA/KalenderRuanganController.md) |
| MasterTipeRuanganController | Master data tipe ruangan kampus (kelas, laboratorium, dll.) | [docs/api/SINAPRA/MasterTipeRuanganController.md](api/SINAPRA/MasterTipeRuanganController.md) |
| MasterSatuanController | Master data satuan barang dan aset (unit, pcs, rim, dll.) | [docs/api/SINAPRA/MasterSatuanController.md](api/SINAPRA/MasterSatuanController.md) |
| MasterVendorController | Master data vendor dan rekanan pengadaan/kalibrasi alat kampus | [docs/api/SINAPRA/MasterVendorController.md](api/SINAPRA/MasterVendorController.md) |
| MasterKategoriBhpController | Master data kategori bahan habis pakai (BHP) laboratorium | [docs/api/SINAPRA/MasterKategoriBhpController.md](api/SINAPRA/MasterKategoriBhpController.md) |

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

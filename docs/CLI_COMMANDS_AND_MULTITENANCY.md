# Dokumentasi Command Instalasi & Audit Multi-Tenancy Ekosistem Kampus

Dokumen ini berisi panduan penggunaan Artisan Command sekali jalan untuk instalasi modul dan hasil audit arsitektur Multi-Tenancy.

---

## 1. CLI Commands Instalasi Sekali Jalan (Artisan Commands)

Tersedia 3 jenis Artisan Command untuk mempermudah eksekusi migrasi & seeding tanpa risiko kehilangan data existing (tanpa `migrate:fresh`):

### A. Command Instalasi Dasar / Standard (`php artisan install:{modul}`)
Menjalankan `php artisan migrate` (aman data existing) + Seeding Base IAM & Master Data spesifik modul.

```bash
# Instalasi Modul SIKEU
php artisan install:sikeu

# Instalasi Modul SIMPEG
php artisan install:simpeg

# Instalasi Modul SIPPM
php artisan install:sippm

# Instalasi Seluruh Modul Sistem (IAM + SIMPEG + SIPPM + SIKEU)
php artisan install:all
```

---

### B. Command Instalasi Testing / Sample Data (`php artisan install-dummy:{modul}`)
Menjalankan `install:{modul}` + memasukkan data sampel percobaan/dummy (Tagihan, Dispensasi, Pemasukan, Pengeluaran, Gaji Pegawai, & Proposal Riset).

```bash
# Instalasi SIKEU lengkap dengan data Dummy Testing
php artisan install-dummy:sikeu

# Instalasi SIMPEG dengan data Dummy Pegawai & Presensi
php artisan install-dummy:simpeg

# Instalasi SIPPM dengan data Dummy Proposal & Reviewer
php artisan install-dummy:sippm

# Instalasi Seluruh Modul dengan Data Dummy
php artisan install-dummy:all
```

---

### C. Command Instalasi Produksi (`php artisan install-prod:{modul}`)
Menjalankan `install:{modul}` murni data produksi (hanya COA, Master Jenis Biaya, Role/Permission, Skema, Unit Kerjanya saja, tanpa data dummy).

```bash
# Instalasi SIKEU Siap Pakai Produksi
php artisan install-prod:sikeu
ƒ
# Instalasi SIMPEG Siap Pakai Produksi
php artisan install-prod:simpeg

# Instalasi SIPPM Siap Pakai Produksi
php artisan install-prod:sippm

# Instalasi Seluruh Modul Siap Pakai Produksi
php artisan install-prod:all
```

---

## 2. Audit Arsitektur Multi-Tenancy

### 🔍 Hasil Audit:

| Komponen | Status Multi-Tenancy | Penjelasan Teknis |
|---|---|---|
| **Backend (Laravel API)** | ❌ **Single-Tenant Multi-Module** | Database saat ini menggunakan **1 central DB schema** untuk 1 Perguruan Tinggi. Belum ada `tenant_id` column di tabel core atau package multi-tenant (seperti `stancl/tenancy`). |
| **Frontend (Next.js 15)** | 🟡 **Multi-Domain / Subdomain App Portal** | Memiliki pendeteksian subdomain (`spmb.domain.com`, `sikeu.domain.com`, `simpeg.domain.com`) untuk merender menu modul secara kontekstual, namun masih menginduk ke 1 institusi kampus. |

### 💡 Rekomendasi Pengembangan Multi-Tenancy (Jika Ingin Mendukung Banyak Kampus):
1. **Backend**: Tambahkan `tenant_id` pada tabel IAM `users`, `pegawai`, `tagihan_mahasiswa`, `jurnal_umum`, dan tambahkan Global Scope Middleware `TenantMiddleware`.
2. **Frontend**: Manfaatkan header `X-Tenant-ID` atau subdomain parser (`tenantA.kampus.ac.id`) yang diteruskan ke API backend.

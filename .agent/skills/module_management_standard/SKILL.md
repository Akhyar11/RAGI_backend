---
name: module-management-standard
description: Standar pengelolaan dan penambahan modul aplikasi (Master Modul) di ekosistem kampus terintegrasi.
---

# Standar Manajemen Modul (Master Modul)

Ekosistem kampus ini menggunakan arsitektur **Master Modul** terpusat. Hal ini berarti penambahan, pengeditan, atau penghapusan modul aplikasi kampus (seperti SSO, SPMB, SIAKAD, dll) **DILARANG** dilakukan melalui *hardcode* di *frontend* (misal: variabel `SYSTEM_MODULES`), melainkan harus dikelola langsung dari *database* via *dashboard* Admin (halaman Master Modul).

## Prinsip Dasar Modul

1. **Modul Induk (Core):** Modul **SSO** (kode: `sso`) adalah modul inti (core) dan secara otomatis aktif. Modul ini tidak boleh dihapus atau dinonaktifkan dari Master Modul.
2. **Keterkaitan (Relasi):** Modul-modul lain dihubungkan berdasarkan kolom `code` (slug) pada tabel `modules` (misal: `siakad`). Menu dan Permission diikat pada nilai `code` modul tersebut.
3. **Data Source of Truth:** *Frontend* tidak boleh lagi menyimpan daftar modul secara statis. Seluruh *dropdown* atau *filter* modul (di Menu, Role, Permission, Dashboard) harus mengambil data secara dinamis (via API GET `/admin/modules`).

## Alur Penambahan Modul Baru

Jika Anda diminta untuk menambahkan modul aplikasi baru (misal: `OBE`):
1. **Jangan tambahkan secara statis ke file `constants.ts`.**
2. Arahkan *Superadmin* untuk membuka **Master Modul** di sidebar *Dashboard*.
3. Admin menambahkan nama modul ("OBE") dan kode modul (`obe`).
4. Setelah modul terdaftar, barulah Admin dapat menambahkan **Menu** dan **Permission** yang dikaitkan ke modul `obe` tersebut di halaman "Master Menu" dan "Permissions".
5. Akses untuk modul baru bisa dipetakan ke *Role* di halaman "Role ↔ Akses Menu".

## Referensi API Modul

CRUD modul ditangani oleh `ModuleController` di *Backend*. Berikut adalah endpoint-nya (memerlukan hak akses `admin`):
- `GET /api/admin/modules`
- `POST /api/admin/modules` (Payload: `name`, `code`, `description`, `is_active`)
- `PUT /api/admin/modules/{id}`
- `DELETE /api/admin/modules/{id}` (SSO tidak dapat dihapus)
- `PUT /api/admin/modules/{id}/toggle` (SSO tidak dapat dinonaktifkan)

## Referensi Frontend Service

Pemanggilan modul dilakukan menggunakan `module.service.ts`:
```typescript
const fetchModules = async () => {
  const data = await moduleService.getAllModules();
  // Set ke state untuk digunakan di dropdown/filter
};
```

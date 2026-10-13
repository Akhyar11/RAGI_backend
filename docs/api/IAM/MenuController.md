# MenuController

> **Modul**: IAM & SSO (Identity & Access Management)  
> **Base URL**: `/api/menus` & `/api/admin/menus`  
> **Autentikasi**: Bearer Token (Laravel Passport / Sanctum)  
> **Dibuat**: 2026-10-13  
> **Diperbarui**: 2026-10-13

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/menus/my-menus?module={code}` | Menu dinamis user login per modul (sumber sidebar) | ✅ User login |
| GET | `/api/admin/menus?module={code}` | Daftar menu root + children untuk Master Menu | ✅ Admin |
| POST | `/api/admin/menus` | Buat menu / grup baru | ✅ Admin |
| PUT | `/api/admin/menus/{menu}` | Update menu / pindah grup | ✅ Admin |
| DELETE | `/api/admin/menus/{menu}` | Hapus menu (cascade ke children via FK) | ✅ Admin |
| PUT | `/api/admin/menus/{menu}/toggle` | Toggle `is_active` | ✅ Admin |
| GET | `/api/admin/role-menus/{roleId}` | Daftar menu ter-mapping ke role | ✅ Admin |
| POST | `/api/admin/role-menus/{roleId}` | Plotting role-menu | ✅ Admin |

---

## 1. Sumber Data: Database (bukan hardcode)

| Lapisan | File | Peran |
|---|---|---|
| Tabel | `backend/database/migrations/2026_07_30_140413_create_menus_table.php` | `core_menus(id, parent_id→core_menus, name, url, icon, module→core_modules.code, permission_id→core_permissions, order_index, is_active)` |
| Pivot | `2026_07_30_153415_create_menu_role_table.php` | `core_menu_role(menu_id, role_id)` |
| Model | `backend/app/Models/Menu.php` | `parent()`, `children()->orderBy(order_index)`, `permission()`, `roles()` |
| Service | `backend/app/Services/MenuService.php::getMyMenus()` | Filter per user + module |
| Seed (awal saja) | `backend/database/seeders/IAM/menu_definitions.php` via `MenuCatalog.php` + `MenuSeeder.php` | Source of truth awal, `updateOrCreate(module+url)` + `syncWithoutDetaching()` role |
| Render | `frontend/components/layout/Sidebar.tsx`, `frontend/services/menu.service.ts`, `frontend/types/menu.ts` | Fetch `getMyMenus(mod)`, fallback hardcode hanya saat API gagal |

## 2. Aturan Pengelompokan (Grouping)

| Kondisi | Arti | Contoh |
|---|---|---|
| `parent_id IS NULL` | Level atas → 1 ikon di rail | `MANAJEMEN KEPEGAWAIAN` |
| `url LIKE '#%'` | Grup/header, non-klik, tidak navigasi | `#kepegawaian_simpeg`, `#fasilitas_sinapra`, `#akun_keamanan` |
| `parent_id = id grup` | Submenu → list teks di panel | `Data Pegawai` di bawah `MANAJEMEN KEPEGAWAIAN` |
| `url = '/...'` nyata | Daun/link navigasi | `/simpeg/pegawai`, `/sinapra/aset` |
| `order_index ASC` | Urutan rail maupun urutan submenu | `1,2,3...` |
| `module` | Partisi per modul (`sso,siakad,sikeu,simpeg,spmb,sinapra,sippm,arsip,lms`) | `?module=simpeg` hanya kembalikan grup simpeg |
| `is_active=false` | Disembunyikan di semua user | Toggle di `/admin/menus` |

Frontend (`Sidebar.tsx:883-934,1092-1130`): `railKeyOf()` = `g:{id}` untuk grup, `id` untuk daun. Panel menampilkan `children` grup terpilih saja (two-level/Opsi C, tanpa ikon di panel).

## 3. Aturan Ikon vs Submenu

1. **Ikon hanya untuk level atas (rail).** Diambil dari kolom `icon` (string `FaUsers`, `FaBuilding`, ...), dipetakan di `getIconComponent():72-160` ke `lucide-react`. Submenu di panel sengaja teks saja.
2. **Ikon rail dijamin beda** via `buildRailIconMap():181-204`: ikon bawaan dipakai bila belum dipakai grup lain dalam modul aktif; bila duplikat / tak dikenal / fallback `LayoutDashboard`, diganti berurutan dari `RAIL_ICON_POOL:167-174`.
3. **Ikon tak terdaftar = fallback.** `FaCalendarAlt`, `FaTh`, `FaThList`, `FaFileInvoiceDollar`, `FaCashRegister`, `FaPercent` belum ada di `iconMap` → jatuh ke `LayoutDashboard` lalu diganti pool. Tambahkan mapping bila ingin ikon eksak.
4. **Hindari duplikat parent-anak.** Contoh bermasalah saat ini: `FASILITAS FaBuilding` = anak `Gedung FaBuilding`; `LAYANAN FaClipboardCheck` = anak `Audit FaClipboardCheck`; `MANAJEMEN KEPEGAWAIAN FaUsers` = anak `Data Pegawai FaUsers`. Akibatnya rail grup otomatis diganti ikon acak dari pool.

## 4. Aturan Visibilitas (`MenuService.php:60-206`)

Root/anak lolos bila salah satu terpenuhi (kecuali superadmin lolos semua):

1. Punya `permission.slug` yang dimiliki user (dan lolos `onlyMappedRoles`: bila menu di-mapping ke role tertentu, hanya role itu yang lihat).
2. `permission_id NULL` + ter-mapping ke role user via `core_menu_role`.
3. Grup (`url #...`) yang punya minimal 1 anak berizin. Grup kosong dibuang (`filter` baris 189-195).
4. Baseline SSO selalu digabung: `/dashboard`, grup `#akun_keamanan` + anak `/profile`, `/profile/sessions`, `/profile/mfa`.

Khusus: `simpeg` mandiri (`Sidebar.tsx:792-793`) — dosen/tendik non-admin tidak pakai DB melainkan `getSimpegMandiriMenus()` (flat, tanpa grup). `sikeu` mahasiswa & `simpeg` admin mengikuti cabang masing-masing.

## 5. Kasus SINAPRA & SIMPEG

Struktur baku saat ini (dari `menu_definitions.php`):

* SINAPRA: `Dashboard Sarpras` (daun) + `FASILITAS & PEMINJAMAN (#fasilitas_sinapra, 5 anak)` + `OPERASIONAL SARPRAS (#operasional_sinapra, 3 anak)` + `MASTER DATA (#master_sinapra, 7 anak)`.
* SIMPEG: `Dashboard SIMPEG` (daun) + `MANAJEMEN KEPEGAWAIAN (#kepegawaian_simpeg, 2 anak)` + `LAYANAN & KINERJA (#layanan_simpeg, 8 anak)` + `MASTER DATA SDM (#master_simpeg, 10 anak)`.

Jika terlihat "kurang sesuai", penyebabnya data (bukan logic): salah `parent_id`, ikon duplikat/tak ter-map di atas, atau `order_index`/`permission` belum tepat. Perbaiki di halaman `Master Menu (/admin/menus)` atau via seeder + `php artisan menu:sync-permissions`.

---

## GET /api/menus/my-menus

> Sumber utama sidebar. Difilter per `module`, permission, dan plotting role-menu.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `module` | string | ❌ | `""` (semua) | Kode modul: `sso,siakad,sikeu,simpeg,spmb,sinapra,sippm,arsip,lms` |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Menus retrieved successfully",
    "data": [
        {
            "id": 503,
            "parent_id": null,
            "name": "MANAJEMEN KEPEGAWAIAN",
            "url": "#kepegawaian_simpeg",
            "icon": "FaUsers",
            "module": "simpeg",
            "permission_id": null,
            "order_index": 2,
            "is_active": true,
            "permission": null,
            "children": [
                {
                    "id": 510,
                    "parent_id": 503,
                    "name": "Data Pegawai",
                    "url": "/simpeg/pegawai",
                    "icon": "FaUsers",
                    "module": "simpeg",
                    "permission_id": 42,
                    "order_index": 1,
                    "is_active": true,
                    "permission": { "id": 42, "slug": "simpeg.pegawai.read" }
                }
            ]
        }
    ]
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

---

## GET /api/admin/menus

> Daftar root + children per modul untuk halaman Master Menu.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `module` | string | ❌ | `sso` | Kode modul di `core_modules.code` |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "All menus retrieved successfully",
    "data": [
        {
            "id": 502,
            "parent_id": null,
            "name": "MASTER DATA SDM",
            "url": "#master_simpeg",
            "icon": "FaDatabase",
            "module": "simpeg",
            "permission_id": null,
            "order_index": 4,
            "is_active": true,
            "permission": null,
            "children": [
                {
                    "id": 5021,
                    "parent_id": 502,
                    "name": "Unit Kerja",
                    "url": "/simpeg/unit-kerja",
                    "icon": "FaSitemap",
                    "module": "simpeg",
                    "permission_id": 55,
                    "order_index": 1,
                    "is_active": true
                }
            ]
        }
    ]
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki izin untuk mengakses resource ini."
}
```

---

## POST /api/admin/menus

> Buat daun atau grup baru. Untuk grup isi `url` dengan `#nama_section` dan `parent_id: null`.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "name": "MASTER DATA SDM",
    "url": "#master_simpeg",
    "icon": "FaDatabase",
    "module": "simpeg",
    "parent_id": null,
    "permission_id": null,
    "order_index": 4,
    "is_active": true
}
```

Validasi (`MenuController.php:66-75`): `name,url,module` required; `module exists:core_modules,code`; `parent_id exists:core_menus,id`; `permission_id exists:core_permissions,id`.

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Menu created successfully",
    "data": {
        "id": 502,
        "name": "MASTER DATA SDM",
        "url": "#master_simpeg",
        "icon": "FaDatabase",
        "module": "simpeg",
        "parent_id": null,
        "order_index": 4,
        "is_active": true
    }
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Validation Error",
    "errors": {
        "module": ["The selected module is invalid."]
    }
}
```

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki izin untuk mengakses resource ini."
}
```

---

## PUT /api/admin/menus/{menu}

> Update menu / pindah grup (ubah `parent_id`) / ubah urutan (`order_index`) / ganti ikon. Body sama dengan POST.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "name": "MASTER DATA SDM",
    "url": "#master_simpeg",
    "icon": "FaDatabase",
    "module": "simpeg",
    "parent_id": null,
    "permission_id": null,
    "order_index": 4,
    "is_active": true
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Menu updated successfully",
    "data": {
        "id": 502,
        "name": "MASTER DATA SDM",
        "url": "#master_simpeg",
        "icon": "FaDatabase",
        "module": "simpeg",
        "order_index": 4,
        "is_active": true
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki izin untuk mengakses resource ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Menu tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Validation Error",
    "errors": {
        "module": ["The selected module is invalid."]
    }
}
```

---

## DELETE /api/admin/menus/{menu}

> Hapus menu. Children ikut terhapus via FK cascade.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Menu deleted successfully"
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki izin untuk mengakses resource ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Menu tidak ditemukan."
}
```

---

## PUT /api/admin/menus/{menu}/toggle

> Toggle `is_active` tanpa menghapus data.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Menu status updated successfully",
    "data": {
        "id": 502,
        "is_active": false
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki izin untuk mengakses resource ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Menu tidak ditemukan."
}
```

---

## GET /api/admin/role-menus/{roleId}

> Lihat menu yang ter-mapping ke role (`RoleMenuController@getRoleMenus`).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": [
        {
            "id": 502,
            "name": "MASTER DATA SDM",
            "url": "#master_simpeg",
            "icon": "FaDatabase",
            "module": "simpeg",
            "order_index": 4,
            "is_active": true
        }
    ]
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki izin untuk mengakses resource ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Role tidak ditemukan."
}
```

---

## POST /api/admin/role-menus/{roleId}

> Simpan plotting menu ke role (`RoleMenuController@assignMenusToRole`). Relasi di-sync penuh (`sync`).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "menu_ids": [502, 503, 504]
}
```

Validasi: `menu_ids` wajib array (`present|array`); tiap item `exists:core_menus,id`.

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Menus assigned successfully to role"
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki izin untuk mengakses resource ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Role tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "menu_ids": ["The menu ids field must be an array."],
        "menu_ids.0": ["The selected menu ids.0 is invalid."]
    }
}
```

---

## Role-Menu Plotting

> Menu tanpa mapping apa pun bersifat publik-permission (lolos via permission). Sekali di-mapping ke role tertentu, hanya role itu yang bisa melihat (`onlyMappedRoles`).

### Catatan Tambahan

> - Grup memakai konvensi `url: #..._section` / `#..._simpeg` / `#..._sinapra` agar dikenali frontend sebagai header non-klik.
> - Ikon grup dalam satu modul wajib unik; ikon anak bebas (tidak tampil di panel Opsi C).
> - Setelah ubah seed, jalankan `php artisan db:seed --class=MenuSeeder` dan `php artisan menu:sync-permissions`.
> - Frontend fallback (`*_FALLBACK_MENUS` di `Sidebar.tsx:206-677`) hanya tampil saat API gagal; array kosong dari API dihormati (tidak fallback).

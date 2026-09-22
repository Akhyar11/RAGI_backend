---
name: seeder-standard
description: Standar pembuatan database seeder untuk data awal (master data, role, permission, dan akun admin) di proyek ini.
---

# Standar Database Seeder

Seeder digunakan untuk mengisi data awal yang **deterministik** (selalu sama setiap kali dijalankan). Ikuti standar berikut agar `php artisan db:seed` bisa dijalankan berulang kali tanpa error.

---

## 1. Struktur Direktori Seeder

```
database/seeders/
├── DatabaseSeeder.php          ← Koordinator utama
├── IAM/
│   ├── RoleSeeder.php
│   ├── PermissionSeeder.php
│   └── AdminUserSeeder.php
├── SIAKAD/
│   ├── FakultasSeeder.php
│   └── ProgramStudiSeeder.php
└── SPMB/
    └── JalurMasukSeeder.php
```

---

## 2. Pola Wajib: `updateOrCreate`

Semua seeder **WAJIB** menggunakan `updateOrCreate` (atau `firstOrCreate`) agar aman dijalankan berulang kali:

```php
// ✅ BENAR — aman dijalankan berulang kali
Role::updateOrCreate(
    ['slug' => 'super-admin'],          // Kunci pencarian
    ['name' => 'Super Admin', 'is_active' => true]  // Nilai yang diisi/diupdate
);

// ❌ SALAH — akan error jika data sudah ada
Role::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
```

---

## 3. DatabaseSeeder sebagai Koordinator

`DatabaseSeeder.php` hanya boleh memanggil seeder lain, terurut sesuai dependency:

```php
<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // IAM harus pertama (tabel users, roles, permissions ada di sini)
        $this->call([
            \Database\Seeders\IAM\RoleSeeder::class,
            \Database\Seeders\IAM\PermissionSeeder::class,
            \Database\Seeders\IAM\AdminUserSeeder::class,

            // Data master kampus
            \Database\Seeders\SIAKAD\FakultasSeeder::class,
            \Database\Seeders\SIAKAD\ProgramStudiSeeder::class,

            // Data SPMB
            \Database\Seeders\SPMB\JalurMasukSeeder::class,
        ]);
    }
}
```

---

## 4. Seeder Wajib yang Harus Ada (IAM)

### RoleSeeder — Data role default sistem:
```php
$roles = [
    ['name' => 'Super Admin',     'slug' => 'super-admin'],
    ['name' => 'Admin IAM',       'slug' => 'admin-iam'],
    ['name' => 'Dosen',           'slug' => 'dosen'],
    ['name' => 'Dosen Wali',      'slug' => 'dosen-wali'],
    ['name' => 'Mahasiswa',       'slug' => 'mahasiswa'],
    ['name' => 'Admin SPMB',      'slug' => 'admin-spmb'],
    ['name' => 'Admin SIAKAD',    'slug' => 'admin-siakad'],
    ['name' => 'Admin SIKEU',     'slug' => 'admin-sikeu'],
];
```

### AdminUserSeeder — Akun Super Admin default:
```php
$admin = User::updateOrCreate(
    ['email' => env('SUPER_ADMIN_EMAIL', 'superadmin@kampus.ac.id')],
    [
        'username'    => 'superadmin',
        'password'    => Hash::make(env('SUPER_ADMIN_PASSWORD', 'password')),
        'is_active'   => true,
        'is_verified' => true,
    ]
);

$superAdminRole = Role::where('slug', 'super-admin')->first();
if ($superAdminRole && !$admin->roles->contains($superAdminRole->id)) {
    $admin->roles()->attach($superAdminRole->id);
}
```

> ⚠️ **PENTING**: Password admin **WAJIB** dibaca dari `.env`, bukan di-hardcode.

---

## 5. Standar Pengorganisasian Menu Seeder (`MenuSeeder.php`)

Agar navigasi sistem di frontend tertata rapi dan mudah digunakan oleh pengguna:

1. **Struktur Hirarki & Section Header:**
   - Modul tidak boleh menumpuk daftar menu secara flat di satu parent raksasa.
   - Wajib dikelompokkan ke dalam kategori/section terorganisir (contoh: `MANAJEMEN PENGGUNA`, `ROLE & HAK AKSES`, `DATA REFERENSI`, `LOG & AUDIT`).
   - Nama section header **WAJIB UPPERCASE** dengan `url` berupa anchor `#` (contoh: `#users_section`, `#roles_section`).
   - Section header wajib memiliki array `children` berisi sub-menu.

2. **Validitas Menu Leaf & URL Unik:**
   - Sub-menu leaf **WAJIB** memiliki rute URL yang valid (diawali `/`, contoh: `/admin/users`).
   - **DILARANG** duplikasi URL rute dalam modul yang sama.
   - Nama menu ditulis dengan format Title Case yang jelas dan deskriptif.

3. **Standar Ikon Semantik (Bukan Generic Dump):**
   - Setiap nama `icon` (seperti `FaHome`, `FaUsers`, `FaKey`, `FaShieldAlt`, `FaDatabase`, `FaLayers`, dsb.) **WAJIB** terdaftar di pemetaan ikon frontend (`Sidebar.tsx` `iconMap`).
   - **DILARANG** membabi buta menggunakan ikon generik `FaList` atau `FaFileAlt` untuk menu dengan fungsi spesifik (seperti Role, User, Pengaturan, dsb.).

4. **Order Index Berurutan & Modul Konsisten:**
   - Setiap menu dan sub-menu wajib memiliki `order_index` numerik positif berurutan (1, 2, 3...) tanpa bentrok dalam parent yang sama.
   - Nilai field `module` pada sub-menu wajib sama dengan parent section.

```php
// ✅ BENAR — Terorganisasi rapi per section
[
    'name' => 'MANAJEMEN PENGGUNA',
    'url' => '#users_section',
    'icon' => 'FaUsers',
    'module' => 'sso',
    'order_index' => 2,
    'children' => [
        ['name' => 'Pengguna Portal', 'url' => '/admin/users', 'icon' => 'FaUsers', 'module' => 'sso', 'permission_slug' => 'iam.users.read', 'order_index' => 1],
        ['name' => 'Plotting User Role', 'url' => '/admin/user-roles', 'icon' => 'FaUserCheck', 'module' => 'sso', 'permission_slug' => 'iam.user_roles.manage', 'order_index' => 2],
    ]
],
```

---

## 6. Menjalankan Seeder

```bash
# Jalankan semua seeder
php artisan db:seed

# Jalankan seeder tertentu saja
php artisan db:seed --class="Database\\Seeders\\IAM\\RoleSeeder"

# Reset database dan seed ulang dari nol
php artisan migrate:fresh --seed
```

---

## 7. Aturan Tambahan

- **JANGAN** gunakan `factory()` di dalam Seeder untuk data master (gunakan factory hanya untuk data dummy testing).
- Data dari Seeder harus **idempoten** — dijalankan 10 kali hasilnya sama.
- Seeder untuk data yang berhubungan dengan lingkungan (email admin, password) **WAJIB** dibaca dari `.env`.

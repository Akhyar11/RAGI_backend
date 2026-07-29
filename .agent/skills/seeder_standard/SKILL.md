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
        'username'  => 'superadmin',
        'password'  => Hash::make(env('SUPER_ADMIN_PASSWORD', 'password')),
        'user_type' => 'admin',
        'is_active' => true,
        'is_verified' => true,
    ]
);
```

> ⚠️ **PENTING**: Password admin **WAJIB** dibaca dari `.env`, bukan di-hardcode.

---

## 5. Menjalankan Seeder

```bash
# Jalankan semua seeder
php artisan db:seed

# Jalankan seeder tertentu saja
php artisan db:seed --class="Database\\Seeders\\IAM\\RoleSeeder"

# Reset database dan seed ulang dari nol
php artisan migrate:fresh --seed
```

---

## 6. Aturan Tambahan

- **JANGAN** gunakan `factory()` di dalam Seeder untuk data master (gunakan factory hanya untuk data dummy testing).
- Data dari Seeder harus **idempoten** — dijalankan 10 kali hasilnya sama.
- Seeder untuk data yang berhubungan dengan lingkungan (email admin, password) **WAJIB** dibaca dari `.env`.

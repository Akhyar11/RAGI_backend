---
name: rbac-authorization
description: Standar implementasi Role-Based Access Control (RBAC) menggunakan Laravel Gates dan Policies untuk melindungi endpoint API.
---

# Standar RBAC Authorization

Setelah sistem Role dan Permission tersedia di database, gunakan mekanisme berikut untuk memeriksa otorisasi di setiap endpoint.

---

## 1. Alur Pemeriksaan Izin

```
Request → Middleware (auth:sanctum) → Policy/Gate Check → Controller
```

Jangan pernah meletakkan logika `if ($user->user_type === 'admin')` secara manual di Controller. Gunakan Gate atau Policy.

---

## 2. Mendefinisikan Gate (untuk Permission Granular)

Daftarkan semua Gate di `app/Providers/AppServiceProvider.php`:

```php
use Illuminate\Support\Facades\Gate;
use App\Models\User;

public function boot(): void
{
    Gate::before(function (User $user, string $ability) {
        // Super admin bypass semua permission
        if ($user->user_type === 'admin' && $user->roles->contains('slug', 'super-admin')) {
            return true;
        }
    });

    Gate::define('manage-users', function (User $user) {
        return $user->roles()
            ->whereHas('permissions', fn($q) => $q->where('slug', 'users.manage'))
            ->exists();
    });
}
```

---

## 3. Menggunakan Policy (untuk Resource spesifik)

Buat Policy untuk setiap model utama:
```bash
php artisan make:policy UserPolicy --model=User
```

Struktur Policy:
```php
<?php
namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasPermission('users.update');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasPermission('users.delete');
    }
}
```

---

## 4. Helper `hasPermission` di Model User

Tambahkan method ini di `app/Models/User.php`:

```php
public function hasPermission(string $permissionSlug): bool
{
    return $this->roles()
        ->whereHas('permissions', fn($q) => $q->where('slug', $permissionSlug))
        ->exists();
}

public function hasRole(string $roleSlug): bool
{
    return $this->roles()->where('slug', $roleSlug)->exists();
}

public function roles()
{
    return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
        ->withPivot(['valid_from', 'valid_until'])
        ->wherePivot('valid_until', '>=', now()->toDateString())
        ->orWherePivotNull('valid_until');
}
```

---

## 5. Cara Pakai di Controller

```php
// Menggunakan Policy (direkomendasikan untuk resource)
public function index()
{
    $this->authorize('viewAny', User::class);
    // ...
}

// Menggunakan Gate (untuk permission custom)
public function approveKRS(KRS $krs)
{
    Gate::authorize('approve-krs');
    // ...
}
```

---

## 6. Middleware Shortcut di Routes

```php
Route::middleware(['auth:sanctum', 'can:manage-users'])->group(function () {
    Route::apiResource('users', UserController::class);
});
```

---

## 7. Konvensi Penamaan Permission Slug

Format: `{modul}.{aksi}` — contoh:

| Slug | Deskripsi |
|---|---|
| `users.read` | Melihat daftar user |
| `users.create` | Membuat user baru |
| `users.update` | Mengubah data user |
| `users.delete` | Menghapus user |
| `krs.approve` | Menyetujui KRS mahasiswa |
| `nilai.input` | Input nilai mahasiswa |

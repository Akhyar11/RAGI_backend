---
name: service-layer-pattern
description: Standar arsitektur Service Layer di Laravel untuk memisahkan logika bisnis dari Controller agar kode mudah diuji, digunakan ulang, dan dirawat.
---

# Standar Service Layer Pattern

Setiap kali logika bisnis lebih dari sekedar CRUD sederhana, **WAJIB** menggunakan Service class terpisah. Controller hanya boleh bertugas sebagai "penerima request dan pengirim response".

---

## 1. Kapan Harus Menggunakan Service?

Buat Service class jika metode controller memiliki:
- Lebih dari 1 operasi ke database
- Kondisi bisnis (if/else) yang kompleks
- Operasi yang perlu dipakai di lebih dari 1 tempat
- Proses yang memerlukan transaksi database (`DB::transaction`)

**Contoh kasus wajib pakai Service:**
- Proses konversi Calon Mahasiswa → Mahasiswa (SPMB)
- Perhitungan nilai akhir dan IPK (SIAKAD)
- Proses penguncian KRS oleh SIKEU

---

## 2. Struktur Direktori

```
app/
└── Services/
    └── IAM/
    │   ├── UserService.php
    │   └── AuthService.php
    └── SPMB/
    │   ├── PendaftaranService.php
    │   └── KonversiMahasiswaService.php
    └── SIAKAD/
        └── NilaiService.php
```

Kelompokkan Service berdasarkan **modul** (IAM, SPMB, SIAKAD, dll.).

---

## 3. Struktur Service Class

```php
<?php

namespace App\Services\IAM;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'username' => $data['username'],
                'email'    => $data['email'],
                'password' => Hash::make($data['password']),
                'user_type' => $data['user_type'],
            ]);

            // Logika bisnis tambahan di sini
            // (misal: kirim email, catat audit log, dll.)

            return $user;
        });
    }

    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user->update($data);
            return $user->fresh();
        });
    }
}
```

---

## 4. Cara Inject ke Controller

```php
<?php
namespace App\Http\Controllers;

use App\Services\IAM\UserService;

class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

    public function store(StoreUserRequest $request)
    {
        $user = $this->userService->create($request->validated());

        return response()->json([
            'status'  => 'success',
            'message' => 'User created successfully',
            'data'    => $user,
        ], 201);
    }
}
```

---

## 5. Aturan Tambahan

- Service **TIDAK BOLEH** mengakses `$request` secara langsung — data dioper lewat array dari controller.
- Service **WAJIB** menggunakan `DB::transaction()` jika ada lebih dari satu operasi tulis ke database.
- Service **BOLEH** memanggil Service lain, tapi hindari circular dependency.
- **Unit test** untuk logika bisnis kompleks ditulis untuk Service-nya langsung (di `tests/Unit/Services/`), bukan hanya Feature Test.

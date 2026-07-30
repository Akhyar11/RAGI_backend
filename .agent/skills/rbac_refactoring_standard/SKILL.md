---
name: rbac-refactoring-standard
description: Standar refaktor untuk memastikan seluruh sistem backend diimplementasikan menggunakan Role-Based Access Control (RBAC) murni berbasis relasi tabel role dan permission, tanpa ada field user_type statis.
---

# Standar Refaktor RBAC Backend (Laravel)

Skill ini wajib digunakan setiap kali merancang API, menulis _query_ ke database, merancang controller, menyusun factory/seeder, atau mengatur Policy keamanan. Sistem saat ini secara eksklusif menggunakan **Role-Based Access Control (RBAC)** dan telah **menghapus secara total** kolom/atribut `user_type` dari seluruh basis kode.

## 1. Database, Migration & Model
- **DILARANG KERAS** menambahkan, memvalidasi, atau membuat query menggunakan atribut `user_type` pada model atau tabel `users`.
- Selalu andalkan relasi Pivot M-to-M (`user_roles`) atau relasi bawaan `$user->roles()` dan `$user->permissions()`.
- Jangan menaruh properti `user_type` di dalam atribut `$fillable` model `User`.

## 2. Autentikasi dan Kebijakan Akses (Policies)
- Jangan pernah mengecek hak istimewa admin menggunakan kondisi perbandingan string, contohnya: `$user->user_type === 'admin'`.
- Gunakan metode relasional otorisasi yang diimplementasikan di model User, misalnya `$user->hasRole('admin')`, `$user->hasRole('superadmin')`, atau `$user->hasPermission('namamodul.action')`.
- Contoh:
  ```php
  // SALAH (JANGAN DILAKUKAN)
  if (auth()->user()->user_type !== 'admin') abort(403);

  // BENAR
  if (!auth()->user()->hasRole('admin') && !auth()->user()->hasRole('superadmin')) {
      abort(403);
  }
  ```

## 3. API Response Payload & Controller
- Jangan pernah menyertakan atau memvalidasi (melalui request) _field_ `user_type` di dalam form CRUD maupun data balasan (format JSON).
- Pastikan relasi _roles_ selalu disertakan (_eager loaded_ via `with('roles')` atau pemanggilan `$user->load('roles')`) ketika data pengguna dikembalikan. Ini terutama krusial di _AuthController_ atau saat proses login SSO (Single Sign-On), sehingga antarmuka _frontend_ dapat mengevaluasi dan merespons otorisasi dengan akurat berdasarkan array Roles tersebut.

## 4. Seeder dan Factory
- Saat membuat atau mengelola Seeder (misal `AdminUserSeeder`, `PegawaiSeeder`), hindari injeksi nilai `user_type`. Sebagai gantinya, panggil fitur `$user->roles()->attach(...)` untuk mengamankan Role pada pengguna bersangkutan.

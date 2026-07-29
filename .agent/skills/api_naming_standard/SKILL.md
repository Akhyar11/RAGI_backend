---
name: api-naming-standard
description: Standar konvensi penamaan (naming conventions) untuk URL/Endpoint API, Method, dan penamaan variabel/kolom dalam response JSON. Aktifkan skill ini saat merancang, membuat, atau me-review endpoint API.
---

# Standar Penamaan API

Agar API di seluruh ekosistem Kampus Terintegrasi memiliki konsistensi yang tinggi, mudah dipahami oleh Frontend, dan mudah dirawat oleh tim Backend, berikut adalah aturan baku yang **WAJIB** diikuti saat membuat endpoint API.

---

## 1. Format URL (Endpoint Naming)

1. **Gunakan Kebab-Case**  
   Gunakan huruf kecil (lowercase) dan pisahkan kata dengan tanda hubung (`-`). Jangan gunakan CamelCase atau snake_case pada URL.
   ✅ BENAR: `/api/users`, `/api/audit-logs`, `/api/role-permissions`
   ❌ SALAH: `/api/Users`, `/api/auditLogs`, `/api/role_permissions`

2. **Gunakan Kata Benda Jamak (Plural Nouns) untuk Resource**  
   Endpoint API merujuk pada "kumpulan" data (resource). Gunakan kata jamak bahasa Inggris (atau kata baku jika bahasa Indonesia digunakan secara khusus, meski sangat disarankan full bahasa Inggris).
   ✅ BENAR: `/api/users`, `/api/roles`, `/api/permissions`
   ❌ SALAH: `/api/user`, `/api/get-roles`, `/api/create-permission`

3. **Jangan Gunakan Kata Kerja (Verbs) di URL**  
   Fungsi/Tindakan harus didefinisikan menggunakan **HTTP Methods** (GET, POST, PUT, DELETE), bukan melalui nama URL.
   ✅ BENAR: `POST /api/users` (untuk membuat)
   ❌ SALAH: `POST /api/create-user`, `GET /api/get-users`

4. **Hierarki Resource Bersarang (Nested Resources)**  
   Jika sebuah entitas bergantung atau merupakan anak dari entitas lain, cerminkan hierarkinya dengan struktur induk-anak.
   ✅ BENAR: `GET /api/users/{id}/roles` (mendapatkan peran milik user tertentu)
   ❌ SALAH: `GET /api/roles-by-user/{id}`

5. **Aksi Khusus (Custom Actions)**  
   Jika ada tindakan yang tidak cocok dengan pola standar CRUD (seperti me-reset password, mengaktifkan status, atau login), Anda boleh menggunakan kata kerja di ujung URL setelah resource.
   ✅ BENAR: `PATCH /api/users/{id}/status`, `POST /api/auth/login`, `POST /api/auth/refresh`
   ❌ SALAH: `POST /api/login-auth`

---

## 2. Penggunaan HTTP Methods

Gunakan *method* HTTP yang sesuai dengan aksi standar RESTful:

- **`GET`**: Hanya untuk membaca (Read) atau mengambil data. Tidak boleh mengubah state server.
- **`POST`**: Untuk membuat (Create) entitas baru, atau menjalankan tindakan kustom (seperti `login`, `logout`).
- **`PUT`**: Untuk memperbarui (Update) data **secara utuh/menyeluruh** (mengganti resource).
- **`PATCH`**: Untuk memperbarui sebagian (Partial Update), contoh: toggle status.
- **`DELETE`**: Untuk menghapus data (baik soft delete maupun hard delete).

---

## 3. Format Response & Struktur Payload (JSON Naming)

1. **Gunakan Snake_Case untuk Response Body**  
   Semua atribut dalam objek JSON balikan (dan masukan request) **WAJIB** menggunakan `snake_case`. Ini adalah standar de facto dalam ekosistem PHP/Laravel.
   ✅ BENAR:
   ```json
   {
       "first_name": "Budi",
       "is_active": true,
       "created_at": "2026-07-29T10:00:00Z"
   }
   ```
   ❌ SALAH: `firstName`, `IsActive`, `createdAt`

2. **Konsistensi Field Boolean**  
   Gunakan prefix `is_`, `has_`, atau `can_` untuk semua nilai boolean.
   ✅ BENAR: `is_active`, `is_verified`, `has_access`
   ❌ SALAH: `active`, `verified`, `status_aktif` (rancu dengan string)

3. **Konsistensi Field Waktu/Tanggal**  
   Gunakan akhiran `_at` untuk menunjukkan atribut waktu atau timestamp, dan `_date` untuk atribut tanggal biasa (Y-m-d).
   ✅ BENAR: `created_at`, `updated_at`, `last_login_at`, `birth_date`
   ❌ SALAH: `created`, `login_time`, `tanggal_lahir`

---

## 4. Parameter Query string (GET Requests)

Sama seperti response, gunakan `snake_case` untuk query parameter yang digunakan untuk filter/sorting.

- `per_page`: Menentukan limit item per halaman.
- `sort_by`: Kolom yang diurutkan.
- `sort_order`: Arah urutan (`asc` atau `desc`).
- ✅ BENAR: `GET /api/users?sort_by=created_at&sort_order=desc&per_page=15`

---

## 5. Prefix Grouping

- **`/api/admin/...`**: Semua operasi yang sifatnya pengelolaan global dan hanya bisa dilakukan oleh Super Admin (misal CRUD permissions, manajemen sistem).
- **`/api/auth/...`**: Operasi yang berkaitan dengan siklus otentikasi (login, register, verifikasi, refresh token).
- **`/api/[Modul]/...`**: Prefix per-modul (contoh: `/api/spmb`, `/api/siakad`, `/api/sikeu`).

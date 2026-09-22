# UserController

> **Modul**: IAM  
> **Base URL**: `/api/admin/users`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat/Diperbarui**: 2026-09-21  

Dokumentasi API untuk manajemen akun pengguna, penugasan peran (roles), aktivasi status, perubahan kata sandi, dan penghapusan pengguna (soft delete). Endpoint ini dibatasi khusus untuk Super Admin.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/admin/users` | Mendapatkan daftar pengguna berpaginasi | ✅ Super Admin |
| POST | `/api/admin/users` | Menambahkan pengguna baru & penugasan role | ✅ Super Admin |
| GET | `/api/admin/users/{id}` | Menampilkan detail pengguna beserta perannya | ✅ Super Admin |
| PUT | `/api/admin/users/{id}` | Memperbarui informasi pengguna & sinkronisasi role | ✅ Super Admin |
| DELETE | `/api/admin/users/{id}` | Menghapus pengguna secara soft delete | ✅ Super Admin |
| PATCH | `/api/admin/users/{id}/status` | Mengaktifkan atau menonaktifkan pengguna | ✅ Super Admin |
| POST | `/api/admin/users/{id}/change-password` | Mengubah password pengguna oleh admin | ✅ Super Admin |
| POST | `/api/admin/users/{id}/impersonate` | Merasuki pengguna & terbitkan token impersonasi | ✅ Super Admin |
| POST | `/api/admin/users/leave-impersonate` | Mengakhiri sesi mode impersonasi pengguna | ✅ Auth (pemegang token impersonasi) |
| GET | `/api/admin/impersonate-status` | Status sesi impersonasi token pemanggil | ✅ Auth (semua user login) |

---

## [GET] /api/admin/users

> Menampilkan daftar seluruh akun pengguna dengan dukungan filter multi-kriteria, pencarian nama/username/email, whitelist pengurutan, dan paginasi standar.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Default | Deskripsi |
|---|---|---|---|
| `search` | string | - | Kata kunci pencarian (name, username, email) |
| `name` | string | - | Filter spesifik berdasarkan nama pengguna |
| `is_active` | boolean | - | Filter status aktif (`true` / `false` / `1` / `0`) |
| `is_verified` | boolean | - | Filter status verifikasi akun |
| `role_id` | integer | - | Filter pengguna yang memiliki ID role tertentu |
| `created_at` | string | - | Filter tanggal pendaftaran akun (format `YYYY-MM-DD`) |
| `sort_by` | string | `created_at` | Kolom pengurutan (`id`, `name`, `username`, `email`, `created_at`, `is_active`, `is_verified`) |
| `sort_order` | string | `desc` | Arah urutan: `asc` / `desc` |
| `per_page` | integer | `15` | Jumlah data per halaman (1 s/d 100) |
| `page` | integer | `1` | Nomor halaman data |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data user berhasil dimuat.",
    "data": [
        {
            "id": 1,
            "username": "superadmin",
            "name": "Super Administrator",
            "email": "superadmin@kampus.ac.id",
            "phone": "081234567890",
            "referral_code": "REF-SUP001",
            "is_active": true,
            "is_verified": true,
            "last_login_at": "2026-09-21T08:00:00.000000Z",
            "created_at": "2026-09-21T00:00:00.000000Z",
            "updated_at": "2026-09-21T08:00:00.000000Z",
            "roles": [
                {
                    "id": 1,
                    "name": "Super Admin",
                    "slug": "super_admin"
                }
            ]
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1,
        "from": 1,
        "to": 1
    },
    "filters": {
        "search": null,
        "name": null,
        "is_active": null,
        "is_verified": null,
        "role_id": null,
        "created_at": null,
        "sort_by": "created_at",
        "sort_order": "desc"
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki akses superadmin."
}
```

---

## [POST] /api/admin/users

> Mendaftarkan pengguna baru oleh Super Admin dan menetapkan perannya secara otomatis dalam transaksi database.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Content-Type` | `application/json` | ✅ |
| `Accept` | `application/json` | ✅ |

### Request Body

```json
{
    "username": "dosen.andi",
    "name": "Dr. Andi Wijaya, M.T.",
    "email": "andi@kampus.ac.id",
    "password": "Password123!",
    "password_confirmation": "Password123!",
    "phone": "081234567890",
    "is_active": true,
    "is_verified": true,
    "roles": [2]
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "User berhasil dibuat.",
    "data": {
        "id": 2,
        "username": "dosen.andi",
        "name": "Dr. Andi Wijaya, M.T.",
        "email": "andi@kampus.ac.id",
        "phone": "081234567890",
        "referral_code": "REF-ANDI01",
        "is_active": true,
        "is_verified": true,
        "created_at": "2026-09-21T10:00:00.000000Z",
        "updated_at": "2026-09-21T10:00:00.000000Z",
        "roles": [
            {
                "id": 2,
                "name": "Dosen",
                "slug": "dosen"
            }
        ]
    }
}
```

### Response Error

**422 Unprocessable Content**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "username": [
            "Username sudah digunakan."
        ],
        "email": [
            "Email sudah terdaftar."
        ]
    }
}
```

---

## [GET] /api/admin/users/{id}

> Mengambil informasi detail satu akun pengguna berdasarkan ID.

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
    "message": "Data user berhasil dimuat.",
    "data": {
        "id": 2,
        "username": "dosen.andi",
        "name": "Dr. Andi Wijaya, M.T.",
        "email": "andi@kampus.ac.id",
        "phone": "081234567890",
        "referral_code": "REF-ANDI01",
        "is_active": true,
        "is_verified": true,
        "created_at": "2026-09-21T10:00:00.000000Z",
        "updated_at": "2026-09-21T10:00:00.000000Z",
        "roles": [
            {
                "id": 2,
                "name": "Dosen",
                "slug": "dosen"
            }
        ]
    }
}
```

### Response Error

**404 Not Found**
```json
{
    "status": "error",
    "message": "Pengguna tidak ditemukan."
}
```

---

## [PUT] /api/admin/users/{id}

> Memperbarui data pengguna dan menyinkronkan daftar peran secara atomik.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Content-Type` | `application/json` | ✅ |
| `Accept` | `application/json` | ✅ |

### Request Body

```json
{
    "username": "dosen.andi",
    "name": "Prof. Dr. Andi Wijaya, M.T.",
    "email": "andi@kampus.ac.id",
    "phone": "081234567899",
    "is_active": true,
    "is_verified": true,
    "roles": [2, 3]
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "User berhasil diperbarui.",
    "data": {
        "id": 2,
        "username": "dosen.andi",
        "name": "Prof. Dr. Andi Wijaya, M.T.",
        "email": "andi@kampus.ac.id",
        "phone": "081234567899",
        "referral_code": "REF-ANDI01",
        "is_active": true,
        "is_verified": true,
        "created_at": "2026-09-21T10:00:00.000000Z",
        "updated_at": "2026-09-21T10:30:00.000000Z",
        "roles": [
            {
                "id": 2,
                "name": "Dosen",
                "slug": "dosen"
            },
            {
                "id": 3,
                "name": "Kaprodi",
                "slug": "kaprodi"
            }
        ]
    }
}
```

---

## [DELETE] /api/admin/users/{id}

> Menghapus akun pengguna menggunakan mekanisme Soft Delete (`deleted_at`). Akun tidak dihapus permanen dari database. Password dan token tidak dikembalikan dalam response demi keamanan.

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
    "message": "User berhasil dihapus.",
    "data": {
        "id": 2,
        "deleted_at": "2026-09-21T10:35:00.000000Z"
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki akses superadmin."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Pengguna tidak ditemukan."
}
```

---

## [PATCH] /api/admin/users/{id}/status

> Mengaktifkan atau menonaktifkan akun pengguna oleh administrator.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Content-Type` | `application/json` | ✅ |
| `Accept` | `application/json` | ✅ |

### Request Body

```json
{
    "is_active": false
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "User berhasil dinonaktifkan.",
    "data": {
        "id": 2,
        "name": "Anisa Rahmawati",
        "username": "dosen",
        "email": "dosen@kampus.ac.id",
        "is_active": false
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki akses superadmin."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "User tidak ditemukan."
}
```

**422 Unprocessable Content**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "is_active": [
            "Bidang is active wajib diisi."
        ]
    }
}
```

---

## [POST] /api/admin/users/{id}/change-password

> Mengubah kata sandi pengguna oleh administrator tanpa memerlukan kata sandi lama.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Content-Type` | `application/json` | ✅ |
| `Accept` | `application/json` | ✅ |

### Request Body

```json
{
    "password": "PasswordBaru#2026",
    "password_confirmation": "PasswordBaru#2026"
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Password pengguna dosen berhasil diperbarui.",
    "data": {
        "id": 2,
        "name": "Anisa Rahmawati",
        "username": "dosen",
        "email": "dosen@kampus.ac.id"
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki akses superadmin."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "User tidak ditemukan."
}
```

**422 Unprocessable Content**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "password": [
            "Password minimal harus 6 karakter.",
            "Konfirmasi password tidak cocok."
        ]
    }
}
```

---

## [POST] /api/admin/users/{id}/impersonate

> Merasuki pengguna dan menerbitkan access token atas nama target user. Seluruh aktivitas impersonasi dicatat dalam Audit Log untuk akuntabilitas.

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
    "message": "Berhasil merasuki pengguna Anisa Rahmawati.",
    "data": {
        "token": "2|abc123xyz...",
        "access_token": "2|abc123xyz...",
        "token_type": "Bearer",
        "user": {
            "id": 2,
            "name": "Anisa Rahmawati",
            "username": "dosen",
            "email": "dosen@kampus.ac.id",
            "referral_code": "REF-DOS002",
            "roles": [
                {
                    "id": 2,
                    "name": "Dosen",
                    "slug": "dosen"
                }
            ]
        },
        "impersonated_by": {
            "id": 1,
            "username": "superadmin",
            "name": "Super Administrator"
        },
        "impersonation_session_id": 12
    }
}
```

> Setiap panggilan mencatat satu baris di tabel `core_impersonation_sessions`
> (kunci per-token `impersonation_token_id`). Satu admin boleh merasuki akun A
> di device 1 dan akun B di device 2 secara bersamaan tanpa saling menimpa.

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki akses superadmin."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "User tidak ditemukan."
}
```

**422 Unprocessable Content**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "user_id": [
            "Anda tidak dapat merasuki akun Anda sendiri."
        ]
    }
}
```

---

## [POST] /api/admin/users/leave-impersonate

> Mengakhiri sesi impersonasi milik token pemanggil saja (sesi device lain
> milik admin yang sama tidak ikut tertutup) dan menerbitkan token admin baru
> agar tab baru tanpa simpanan adminToken tetap bisa kembali ke akun admin.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token_impersonasi}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Sesi impersonasi berhasil diakhiri.",
    "data": {
        "admin": {
            "id": 1,
            "username": "superadmin",
            "name": "Super Administrator"
        },
        "access_token": "1|def456uvw...",
        "token": "1|def456uvw...",
        "token_type": "Bearer"
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Tidak sedang dalam mode impersonasi.",
    "errors": {
        "impersonation": [
            "Tidak sedang dalam mode impersonasi."
        ]
    }
}
```

> Token biasa (bukan token impersonasi) yang memanggil endpoint ini akan
> ditolak 422 dan TIDAK dicabut — berbeda dari perilaku lama yang menghapus
> token apapun.

---

## [GET] /api/admin/impersonate-status

> Status sesi impersonasi untuk token pemanggil. Dipakai banner frontend di
> tab/subdomain baru agar tetap muncul tanpa bergantung pada sessionStorage
> tab lama. Isolasi per-token: device 1 (token A) dan device 2 (token B)
> mendapat status masing-masing walaupun admin-nya sama.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (sedang merasuki)

**200 OK**
```json
{
    "status": "success",
    "message": "Sesi impersonasi aktif.",
    "data": {
        "is_impersonating": true,
        "impersonation_session_id": 12,
        "started_at": "2026-09-22T10:00:00.000000Z",
        "impersonated_by": {
            "id": 1,
            "username": "superadmin",
            "name": "Super Administrator"
        }
    }
}
```

### Response Sukses (tidak merasuki)

**200 OK**
```json
{
    "status": "success",
    "message": "Tidak sedang dalam mode impersonasi.",
    "data": {
        "is_impersonating": false
    }
}
```

> Token lawas (dibuat sebelum tabel `core_impersonation_sessions` ada)
> mengembalikan tambahan `"is_legacy": true` dengan
> `"impersonation_session_id": null`.

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

---

> **Catatan Keamanan:** Atribut `password` dan `remember_token` selalu disembunyikan dan tidak pernah dikembalikan dalam respon API apa pun. Operasi penghapusan bersifat Soft Delete sehingga data historis pada log dan relasi akademik tetap terjaga. Sesi impersonasi dilindungi oleh autentikasi ganda dan tercatat pada jejak Audit Log sistem.


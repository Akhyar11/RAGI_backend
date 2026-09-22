# UserSessionController

> **Modul**: IAM  
> **Base URL**: `/api/admin/sessions` & `/api/auth/sessions`  
> **Autentikasi**: Bearer Token (Passport)  
> **Dibuat/Diperbarui**: 2026-09-21  

Dokumentasi API untuk pemantauan dan pengelolaan sesi login aktif dari pengguna berdasarkan token Passport.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/auth/sessions` | Menampilkan daftar sesi login aktif milik pengguna saat ini | ✅ Authenticated |
| DELETE | `/api/auth/sessions/{id}` | Mencabut sesi pada perangkat tertentu | ✅ Authenticated |
| DELETE | `/api/auth/sessions/others` | Mencabut semua sesi lain kecuali perangkat saat ini | ✅ Authenticated |
| GET | `/api/admin/sessions` | Menampilkan seluruh sesi aktif di sistem berpaginasi | ✅ Super Admin |
| DELETE | `/api/admin/sessions/{id}` | Memutuskan paksa (force logout) sesi pengguna tertentu | ✅ Super Admin |

---

## [GET] /api/admin/sessions

> Mengambil daftar seluruh sesi aktif pengguna di sistem dengan dukungan pencarian multi-kolom, filter spesifik, pengurutan whitelist, dan paginasi standar.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Default | Deskripsi |
|---|---|---|---|
| `search` | string | - | Kata kunci pencarian (ip_address, user_agent, username, name, email) |
| `user_id` | integer | - | Filter berdasarkan ID pengguna spesifik |
| `username` | string | - | Filter berdasarkan username atau nama pengguna |
| `ip_address` | string | - | Filter berdasarkan alamat IP |
| `user_agent` | string | - | Filter berdasarkan peramban / platform |
| `created_at` | string | - | Filter tanggal pembuatan sesi (format `YYYY-MM-DD`) |
| `sort_by` | string | `created_at` | Kolom pengurutan (`id`, `user_id`, `ip_address`, `created_at`, `user_agent`) |
| `sort_order` | string | `desc` | Arah urutan: `asc` / `desc` |
| `per_page` | integer | `15` | Jumlah sesi per halaman (1 s/d 100) |
| `page` | integer | `1` | Nomor halaman data |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data retrieved successfully",
    "data": [
        {
            "id": 5,
            "user_id": 1,
            "token": "e4f5a89...",
            "ip_address": "192.168.1.1",
            "user_agent": "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36",
            "created_at": "2026-09-21T10:00:00.000000Z",
            "user": {
                "id": 1,
                "username": "superadmin",
                "name": "Super Administrator",
                "email": "superadmin@kampus.ac.id"
            }
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
        "user_id": null,
        "username": null,
        "ip_address": null,
        "user_agent": null,
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

## [DELETE] /api/admin/sessions/{id}

> Memutuskan atau mencabut paksa sesi milik pengguna mana saja di sistem (Force Logout).

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
    "message": "Sesi berhasil diputus paksa (force logout)."
}
```

### Response Error

**404 Not Found**
```json
{
    "status": "error",
    "message": "Sesi tidak ditemukan."
}
```

---

## [GET] /api/auth/sessions

> Menampilkan daftar perangkat dan sesi aktif untuk akun yang saat ini sedang login.

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
    "message": "Data retrieved successfully",
    "data": [
        {
            "id": 15,
            "user_id": 3,
            "token": "d748f2a1b9...",
            "ip_address": "127.0.0.1",
            "user_agent": "Mozilla/5.0 (X11; Linux x86_64)...",
            "created_at": "2026-09-21T10:00:00.000000Z"
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
        "sort_by": "created_at",
        "sort_order": "desc"
    }
}
```

---

## [DELETE] /api/auth/sessions/{id}

> Menghapus / logout dari suatu perangkat tertentu berdasarkan ID sesi.

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
    "message": "Sesi berhasil dihapus (logout dari perangkat)."
}
```

### Response Error

**404 Not Found**
```json
{
    "status": "error",
    "message": "Sesi tidak ditemukan atau Anda tidak memiliki akses."
}
```

---

## [DELETE] /api/auth/sessions/others

> Mencabut seluruh token dan sesi aktif lainnya selain perangkat yang sedang digunakan saat ini.

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
    "message": "2 sesi lain berhasil dihapus (logout dari perangkat lain)."
}
```

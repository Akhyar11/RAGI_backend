# ModuleController

> **Modul**: IAM & Auth Center  
> **Base URL**: `/api/admin/modules` & `/api/modules/my-modules`  
> **Autentikasi**: Bearer Token (Passport)  
> **Dibuat**: 2026-07-30  
> **Diperbarui**: 2026-10-23  

Controller ini menangani manajemen *Master Modul* sistem (`core_modules`) serta penyediaan daftar modul aktif yang berizin diakses oleh pengguna yang sedang terautentikasi (`my-modules`) untuk keperluan Modul Switcher (App Launcher).

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/modules/my-modules` | Daftar modul aktif yang dapat diakses oleh user login (dinamis RBAC) | ✅ User login |
| GET | `/api/admin/modules` | Daftar semua modul sistem untuk manajemen | ✅ User login |
| POST | `/api/admin/modules` | Buat modul baru | ✅ Admin |
| PUT | `/api/admin/modules/{id}` | Update modul | ✅ Admin |
| DELETE | `/api/admin/modules/{id}` | Hapus modul (kecuali modul inti `sso`) | ✅ Admin |
| PUT | `/api/admin/modules/{id}/toggle` | Toggle status aktif modul | ✅ Admin |

---

## GET /api/modules/my-modules

> Mengembalikan daftar modul aktif yang memiliki izin akses bagi user login. Superadmin menerima semua modul aktif; pengguna umum menerima modul yang memiliki setidaknya satu menu berizin bagi perannya.

### Headers

| Header | Value | Deskripsi |
|---|---|---|
| `Authorization` | `Bearer {token}` | Token akses JWT pengguna |
| `Accept` | `application/json` | Format respons yang diharapkan |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Berhasil mengambil daftar modul pengguna",
    "data": [
        {
            "id": 1,
            "name": "IAM & Auth Center",
            "code": "sso",
            "description": "Modul inti untuk autentikasi dan otorisasi",
            "primary_color": "#3b82f6",
            "is_active": true
        },
        {
            "id": 7,
            "name": "SIAKAD (Akademik)",
            "code": "siakad",
            "description": "Sistem Informasi Akademik",
            "primary_color": "#06b6d4",
            "is_active": true
        }
    ]
}
```

### Response Error

**401 Unauthorized**:
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

---

## GET /api/admin/modules

> Mengambil seluruh daftar master modul sistem untuk keperluan administrasi dan plotting menu.

### Headers

| Header | Value | Deskripsi |
|---|---|---|
| `Authorization` | `Bearer {token}` | Token akses JWT admin |
| `Accept` | `application/json` | Format respons yang diharapkan |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "data": [
        {
            "id": 1,
            "name": "IAM & Auth Center",
            "code": "sso",
            "description": "Modul inti untuk autentikasi dan otorisasi",
            "primary_color": "#3b82f6",
            "is_active": true
        }
    ]
}
```

### Response Error

**401 Unauthorized**:
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

---

## POST /api/admin/modules

> Membuat master modul baru di dalam sistem.

### Headers

| Header | Value | Deskripsi |
|---|---|---|
| `Authorization` | `Bearer {token}` | Token akses JWT admin |
| `Content-Type` | `application/json` | Format body permintaan |
| `Accept` | `application/json` | Format respons yang diharapkan |

### Request Body

```json
{
    "name": "Perpustakaan Digital",
    "code": "perpus",
    "description": "Sistem informasi pengelolaan perpustakaan kampus",
    "primary_color": "#10b981",
    "is_active": true
}
```

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Module created successfully",
    "data": {
        "id": 11,
        "name": "Perpustakaan Digital",
        "code": "perpus",
        "description": "Sistem informasi pengelolaan perpustakaan kampus",
        "primary_color": "#10b981",
        "is_active": true
    }
}
```

### Response Error

**422 Unprocessable Content** (Validasi gagal):
```json
{
    "message": "The code has already been taken.",
    "errors": {
        "code": [
            "The code has already been taken."
        ]
    }
}
```

**403 Forbidden**:
```json
{
    "status": "error",
    "message": "Anda tidak memiliki izin untuk mengelola modul."
}
```

---

## PUT /api/admin/modules/{id}

> Memperbarui identitas dan status master modul yang sudah ada.

### Headers

| Header | Value | Deskripsi |
|---|---|---|
| `Authorization` | `Bearer {token}` | Token akses JWT admin |
| `Content-Type` | `application/json` | Format body permintaan |
| `Accept` | `application/json` | Format respons yang diharapkan |

### Request Body

```json
{
    "name": "Perpustakaan & Repositori",
    "code": "perpus",
    "description": "Sistem informasi perpustakaan dan repositori karya ilmiah",
    "primary_color": "#059669",
    "is_active": true
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Module updated successfully",
    "data": {
        "id": 11,
        "name": "Perpustakaan & Repositori",
        "code": "perpus",
        "description": "Sistem informasi perpustakaan dan repositori karya ilmiah",
        "primary_color": "#059669",
        "is_active": true
    }
}
```

### Response Error

**404 Not Found**:
```json
{
    "status": "error",
    "message": "Modul tidak ditemukan."
}
```

**422 Unprocessable Content**:
```json
{
    "message": "The name field is required.",
    "errors": {
        "name": [
            "The name field is required."
        ]
    }
}
```

---

## DELETE /api/admin/modules/{id}

> Menghapus master modul dari sistem. Modul inti `sso` tidak dapat dihapus.

### Headers

| Header | Value | Deskripsi |
|---|---|---|
| `Authorization` | `Bearer {token}` | Token akses JWT admin |
| `Accept` | `application/json` | Format respons yang diharapkan |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Module deleted successfully"
}
```

### Response Error

**403 Forbidden** (Mencoba menghapus modul inti `sso`):
```json
{
    "status": "error",
    "message": "Cannot delete the core SSO module."
}
```

**404 Not Found**:
```json
{
    "status": "error",
    "message": "Modul tidak ditemukan."
}
```

---

## PUT /api/admin/modules/{id}/toggle

> Mengubah status aktif/nonaktif master modul. Modul inti `sso` tidak dapat dinonaktifkan.

### Headers

| Header | Value | Deskripsi |
|---|---|---|
| `Authorization` | `Bearer {token}` | Token akses JWT admin |
| `Accept` | `application/json` | Format respons yang diharapkan |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Module status updated",
    "data": {
        "id": 11,
        "name": "Perpustakaan & Repositori",
        "code": "perpus",
        "is_active": false
    }
}
```

### Response Error

**403 Forbidden** (Mencoba menonaktifkan modul inti `sso`):
```json
{
    "status": "error",
    "message": "Cannot deactivate the core SSO module."
}
```

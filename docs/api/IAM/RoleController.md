# RoleController & PermissionController

> **Modul**: IAM & Auth Center  
> **Base URL**: `/api/roles` & `/api/permissions`  
> **Autentikasi**: Bearer Token (Passport)  
> **Dibuat**: 2026-07-29

Controller ini menangani manajemen *Role* (Peran) dan *Permission* (Hak Akses) untuk implementasi Role-Based Access Control (RBAC).

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/roles` | Daftar semua role beserta permissionnya | ✅ `roles.read` |
| POST | `/api/roles` | Buat role baru & assign permission | ✅ `roles.create` |
| GET | `/api/roles/{id}` | Detail satu role | ✅ `roles.read` |
| PUT | `/api/roles/{id}` | Update role & sync permission | ✅ `roles.update` |
| DELETE | `/api/roles/{id}` | Hapus role | ✅ `roles.delete` |
| GET | `/api/permissions` | Daftar semua permission yang tersedia | ✅ `roles.read` |

---

## POST /api/roles

> Membuat role baru dan secara opsional menautkannya dengan beberapa permission sekaligus.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "name": "Operator Keuangan",
    "slug": "operator-sikeu",
    "description": "Staf bagian validasi bayar",
    "is_active": true,
    "permissions": [1, 2, 5]
}
```

> **Catatan**: Field `permissions` adalah *array* dari ID permission yang ada di tabel `permissions`.

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Data created successfully",
    "data": {
        "name": "Operator Keuangan",
        "slug": "operator-sikeu",
        "description": "Staf bagian validasi bayar",
        "is_active": true,
        "id": 10,
        "created_at": "2026-07-29T10:00:00.000000Z",
        "updated_at": "2026-07-29T10:00:00.000000Z",
        "permissions": [
            {
                "id": 1,
                "name": "Manage Users",
                "slug": "users.manage",
                "module": "IAM"
            }
        ]
    }
}
```

---

## GET /api/permissions

> Mengambil daftar semua permission yang ada di sistem (dibutuhkan oleh Frontend saat membuat/mengedit Role).

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari nama/modul permission |
| `module` | string | ❌ | — | Filter spesifik per modul (contoh: `IAM`) |
| `sort_by` | string | ❌ | `module` | Kolom pengurutan (`name`, `module`) |
| `per_page` | integer | ❌ | `50` | Jumlah data per halaman |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Data retrieved successfully",
    "data": [
        {
            "id": 1,
            "name": "Manage Users",
            "slug": "users.manage",
            "module": "IAM",
            "action": "manage",
            "description": "CRUD semua data pengguna"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 50,
        "total": 12,
        "last_page": 1,
        "from": 1,
        "to": 12
    },
    "filters": {
        "search": null,
        "module": null,
        "sort_by": "module",
        "sort_order": "asc"
    }
}
```

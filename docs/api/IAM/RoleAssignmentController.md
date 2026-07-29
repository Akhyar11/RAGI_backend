# RoleAssignmentController

> **Modul**: IAM (Identity & Access Management)
> **Base URL**: `/api/admin`
> **Autentikasi**: Bearer Token (Sanctum)
> **Dibuat**: 2026-07-29
> **Diperbarui**: 2026-07-29

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| POST | `/api/admin/users/{id}/roles` | Assign multiple roles ke user | ✅ Admin |
| POST | `/api/admin/roles/{id}/permissions` | Assign multiple permissions ke role | ✅ Admin |
| GET | `/api/admin/user-roles` | List pemetaan User ke Roles | ✅ Admin |
| GET | `/api/admin/role-permissions` | List pemetaan Role ke Permissions | ✅ Admin |

---

## [POST] /api/admin/users/{id}/roles

> Menetapkan satu atau beberapa peran (roles) kepada pengguna. Endpoint ini menggunakan metode "sync", artinya role lama yang tidak ada dalam daftar request akan dicabut (detach).

### Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` (path) | integer | ✅ | ID User |

### Request Body

```json
{
    "roles": "array of integers, required, exists in roles.id"
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Roles assigned successfully",
    "data": {
        "id": 1,
        "username": "budi.admin",
        "roles": [
            {
                "id": 1,
                "name": "Super Admin",
                "slug": "super-admin",
                "pivot": {
                    "user_id": 1,
                    "role_id": 1
                }
            }
        ]
    }
}
```

---

## [POST] /api/admin/roles/{id}/permissions

> Menetapkan satu atau beberapa permission kepada sebuah peran (role). Endpoint ini menggunakan metode "sync".

### Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` (path) | integer | ✅ | ID Role |

### Request Body

```json
{
    "permissions": "array of integers, required, exists in permissions.id"
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Permissions assigned successfully",
    "data": {
        "id": 1,
        "name": "Super Admin",
        "permissions": [
            {
                "id": 1,
                "name": "View Roles",
                "pivot": {
                    "role_id": 1,
                    "permission_id": 1
                }
            }
        ]
    }
}
```

---

## [GET] /api/admin/user-roles

> Mendapatkan daftar semua pengguna beserta roles mereka.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari berdasarkan username/email |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "User roles retrieved successfully",
    "data": [
        {
            "id": 1,
            "username": "budi",
            "roles": [...]
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 100
    }
}
```

---

## [GET] /api/admin/role-permissions

> Mendapatkan daftar semua role beserta permissions mereka.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari berdasarkan nama role / slug |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Role permissions retrieved successfully",
    "data": [
        {
            "id": 1,
            "name": "Super Admin",
            "permissions": [...]
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 10
    }
}
```

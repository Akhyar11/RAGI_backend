# PermissionController

> **Modul**: IAM (Identity & Access Management)
> **Base URL**: `/api/admin/permissions`
> **Autentikasi**: Bearer Token (Sanctum)
> **Dibuat**: 2026-07-29
> **Diperbarui**: 2026-07-29

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/admin/permissions` | Daftar semua permission | ✅ Admin |
| POST | `/api/admin/permissions` | Buat permission baru | ✅ Admin |
| PUT | `/api/admin/permissions/{id}` | Update data permission | ✅ Admin |
| DELETE | `/api/admin/permissions/{id}` | Hapus permission | ✅ Admin |

---

## [GET] /api/admin/permissions

> Mengambil daftar seluruh permission dengan opsi pencarian, filter modul, dan paginasi.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari berdasarkan name atau module |
| `module` | string | ❌ | — | Filter berdasarkan modul |
| `sort_by` | string | ❌ | `module` | Kolom pengurutan (`module`, `name`) |
| `sort_order` | string | ❌ | `asc` | Arah urutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `50` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data retrieved successfully",
    "data": [
        {
            "id": 1,
            "module": "IAM",
            "action": "read",
            "name": "View Roles",
            "slug": "roles.read",
            "description": "Allow viewing roles"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 50,
        "total": 1,
        "last_page": 1,
        "from": 1,
        "to": 1
    },
    "filters": {
        "search": null,
        "module": null,
        "sort_by": "module",
        "sort_order": "asc"
    }
}
```

---

## [POST] /api/admin/permissions

> Membuat permission baru.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "module": "string, required, max 100",
    "action": "string, required, max 50",
    "name": "string, required, max 150",
    "slug": "string, required, max 150, unique:permissions",
    "description": "string, nullable"
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Permission created successfully",
    "data": {
        "id": 2,
        "module": "SPMB",
        "action": "approve",
        "name": "Approve Registrations",
        "slug": "spmb.approve",
        "description": "Approve student registrations",
        "created_at": "2026-07-29T14:00:00.000000Z",
        "updated_at": "2026-07-29T14:00:00.000000Z"
    }
}
```

---

## [PUT] /api/admin/permissions/{id}

> Memperbarui data permission.

### Request Body

Sama seperti `POST`.

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Permission updated successfully",
    "data": {
        "id": 2,
        "module": "SPMB",
        "action": "approve",
        "name": "Approve Student Registrations",
        "slug": "spmb.approve",
        "description": "Approve registrations",
        "created_at": "2026-07-29T14:00:00.000000Z",
        "updated_at": "2026-07-29T14:15:00.000000Z"
    }
}
```

---

## [DELETE] /api/admin/permissions/{id}

> Menghapus data permission dari sistem secara permanen.

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Permission deleted successfully"
}
```

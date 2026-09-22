# AuditLogController

> **Modul**: IAM  
> **Base URL**: `/api/admin/audit-logs`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat/Diperbarui**: 2026-09-21  

Dokumentasi API untuk pembacaan dan pemantauan rekam jejak aktivitas sistem (Audit Logs). Endpoint ini dibatasi untuk pengguna dengan wewenang pemeriksaan log (Super Admin atau pemilik permission `view-audit-logs`).

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/admin/audit-logs` | Menampilkan daftar aktivitas audit sistem berpaginasi | ✅ Super Admin |
| GET | `/api/admin/audit-logs/{id}` | Menampilkan detail satu catatan audit log | ✅ Super Admin |

---

## [GET] /api/admin/audit-logs

> Mengambil daftar rekaman audit log dengan filter modular, pencarian multi-kolom, pengurutan whitelist, dan paginasi standar.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Default | Deskripsi |
|---|---|---|---|
| `search` | string | - | Kata kunci pencarian (module, action, table_name, ip_address, payload, username, email) |
| `username` | string | - | Filter berdasarkan username atau nama pengguna |
| `action` | string | - | Filter aksi (login, create, update, delete, restore, dll.) |
| `ip_address` | string | - | Filter alamat IP |
| `payload` | string | - | Filter isi payload log |
| `created_at` | string | - | Filter berdasarkan tanggal pencatatan log (format `YYYY-MM-DD`) |
| `user_id` | integer | - | Filter ID pengguna yang melakukan aksi |
| `sort_by` | string | `created_at` | Kolom pengurutan (`id`, `user_id`, `action`, `ip_address`, `module`, `table_name`, `created_at`) |
| `sort_order` | string | `desc` | Arah pengurutan (`asc`, `desc`) |
| `per_page` | integer | `15` | Jumlah rekaman per halaman (1 s/d 100) |
| `page` | integer | `1` | Nomor halaman data |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data retrieved successfully",
    "data": [
        {
            "id": 12,
            "user_id": 1,
            "module": "IAM",
            "action": "update",
            "table_name": "core_users",
            "record_id": 3,
            "old_values": {
                "name": "Budi Santoso",
                "phone": "08123456789"
            },
            "new_values": {
                "name": "Budi Santoso M.Kom",
                "phone": "08111111111"
            },
            "ip_address": "127.0.0.1",
            "user_agent": "Mozilla/5.0 ...",
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
        "total": 120,
        "last_page": 8,
        "from": 1,
        "to": 15
    },
    "filters": {
        "search": null,
        "username": null,
        "action": null,
        "ip_address": null,
        "payload": null,
        "created_at": null,
        "user_id": null,
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
    "message": "Anda tidak memiliki hak akses untuk melihat audit log."
}
```

---

## [GET] /api/admin/audit-logs/{id}

> Menampilkan rincian lengkap dari satu catatan audit log spesifik.

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
    "data": {
        "id": 12,
        "user_id": 1,
        "module": "IAM",
        "action": "update",
        "table_name": "core_users",
        "record_id": 3,
        "old_values": {
            "phone": "08123456789"
        },
        "new_values": {
            "phone": "08111111111"
        },
        "ip_address": "127.0.0.1",
        "user_agent": "Mozilla/5.0 ...",
        "created_at": "2026-09-21T10:00:00.000000Z",
        "user": {
            "id": 1,
            "username": "superadmin",
            "name": "Super Administrator",
            "email": "superadmin@kampus.ac.id"
        }
    }
}
```

### Response Error

**404 Not Found**
```json
{
    "status": "error",
    "message": "Log tidak ditemukan",
    "data": null
}
```

# API Dokumentasi: Audit Logs

Dokumentasi ini menjelaskan _endpoint_ untuk membaca rekam jejak sistem (Audit Logs). Endpoint ini hanya dapat diakses oleh *Super Admin* atau pengguna dengan permission `view-audit-logs`.

**Base URL:** `/api/admin/audit-logs`

## 1. List Audit Logs
Menampilkan daftar aktivitas sistem dengan dukungan paginasi, pencarian, dan filter.

**Endpoint:** `GET /api/admin/audit-logs`  
**Auth Required:** Yes (Bearer Token)

### Parameter Query (Opsional)
- `page` (int): Halaman saat ini (default: 1).
- `per_page` (int): Jumlah data per halaman (default: 15).
- `search` (string): Mencari berdasarkan module, action, atau table_name.
- `user_id` (int): Memfilter log berdasarkan ID pengguna spesifik.

### Response Sukses (200 OK)
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
            "table_name": "users",
            "record_id": 3,
            "old_values": {
                "phone": "08123456789"
            },
            "new_values": {
                "phone": "08111111111"
            },
            "ip_address": "127.0.0.1",
            "user_agent": "Mozilla/5.0 ...",
            "created_at": "2026-07-29T10:00:00.000000Z",
            "user": {
                "id": 1,
                "username": "superadmin",
                "email": "superadmin@kampus.ac.id",
                "user_type": "admin"
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
        "user_id": null,
        "sort_by": "created_at",
        "sort_order": "desc"
    }
}
```

## 2. Detail Audit Log
Menampilkan rincian satu catatan log secara spesifik.

**Endpoint:** `GET /api/admin/audit-logs/{id}`  
**Auth Required:** Yes (Bearer Token)

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Data retrieved successfully",
    "data": {
        "id": 12,
        "user_id": 1,
        // ...
    }
}
```

### Response Gagal (404 Not Found)
```json
{
    "status": "error",
    "message": "Log tidak ditemukan",
    "data": null
}
```

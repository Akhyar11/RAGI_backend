# API Dokumentasi: Session & Devices Management

Dokumentasi ini menjelaskan *endpoints* untuk mengelola perangkat dan sesi yang aktif dari pengguna (berdasarkan token Passport).

**Base URL:** `/api/auth/sessions`  
*(Catatan: endpoint ini berada di bawah prefix `/api/auth/` berdasarkan struktur routing terbaru)*

## 1. List Active Sessions
Menampilkan daftar perangkat (sesi login) yang sedang aktif untuk pengguna yang sedang *login*.

**Endpoint:** `GET /api/auth/sessions`  
**Auth Required:** Yes (Bearer Token)

### Response Sukses (200 OK)
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
            "user_agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)...",
            "expires_at": null,
            "created_at": "2026-07-29T10:00:00.000000Z"
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

## 2. Revoke Sesi (Logout Perangkat Tertentu)
Menghapus (logout paksa) sesi dari suatu perangkat berdasarkan `id` tabel `user_sessions_iam`.

**Endpoint:** `DELETE /api/auth/sessions/{id}`  
**Auth Required:** Yes (Bearer Token)

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Sesi berhasil dihapus (logout dari perangkat)."
}
```

### Response Gagal (404 Not Found)
Jika ID tidak ditemukan atau milik user lain.
```json
{
    "status": "error",
    "message": "Sesi tidak ditemukan atau Anda tidak memiliki akses."
}
```

## 3. Revoke Semua Sesi Lain
Me-*revoke* semua sesi yang ada **kecuali** sesi (perangkat) yang sedang digunakan saat ini. Berguna jika pengguna merasa akunnya diretas.

**Endpoint:** `DELETE /api/auth/sessions/others`  
**Auth Required:** Yes (Bearer Token)

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "3 sesi lain berhasil dihapus (logout dari perangkat lain)."
}
```

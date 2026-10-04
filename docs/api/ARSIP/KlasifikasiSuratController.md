# KlasifikasiSuratController

> **Modul**: ARSIP  
> **Base URL**: `/api/arsip/klasifikasi`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-10-02  
> **Diperbarui**: 2026-10-05  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/arsip/klasifikasi` | Daftar referensi klasifikasi & kode unit surat | ✅ Authenticated |
| POST | `/api/arsip/klasifikasi` | Tambah kode klasifikasi / unit baru | ✅ Admin / `arsip.master.manage` |
| GET | `/api/arsip/klasifikasi/{id}` | Detail referensi klasifikasi surat | ✅ Authenticated |
| PUT | `/api/arsip/klasifikasi/{id}` | Perbarui data klasifikasi surat | ✅ Admin / `arsip.master.manage` |
| DELETE | `/api/arsip/klasifikasi/{id}` | Hapus data klasifikasi surat | ✅ Admin / `arsip.master.manage` |

---

## GET /api/arsip/klasifikasi

> Menampilkan daftar master kode klasifikasi dan kode unit surat terdaftar dengan dukungan pencarian, filter kategori, status aktif, dan opsi dropdown (`all=true`).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Pencarian kode, nama, atau keterangan |
| `kategori` | string | ❌ | — | Filter kategori: `klasifikasi`, `unit`, `jenjang` |
| `is_active` | boolean | ❌ | — | Filter status aktif (`true` / `false`) |
| `all` | boolean | ❌ | `false` | Jika `true`, memuat seluruh data tanpa paginasi (untuk dropdown) |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses (200 OK - Paginasi)

```json
{
    "status": "success",
    "message": "Daftar klasifikasi surat berhasil dimuat.",
    "data": [
        {
            "id": 1,
            "kode": "DI",
            "nama": "SK",
            "kategori": "klasifikasi",
            "keterangan": "Surat Keputusan",
            "is_active": true,
            "created_at": "2026-10-05T00:00:00.000000Z",
            "updated_at": "2026-10-05T00:00:00.000000Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 14,
        "last_page": 1,
        "from": 1,
        "to": 14
    },
    "filters": {
        "search": null,
        "kategori": null,
        "is_active": null
    }
}
```

---

## POST /api/arsip/klasifikasi

> Menambahkan referensi kode klasifikasi surat atau unit baru ke sistem.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "kode": "DII",
    "nama": "ST/SPPD",
    "kategori": "klasifikasi",
    "keterangan": "Surat Tugas / Perjalanan Dinas",
    "is_active": true
}
```

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Klasifikasi surat berhasil ditambahkan.",
    "data": {
        "id": 2,
        "kode": "DII",
        "nama": "ST/SPPD",
        "kategori": "klasifikasi",
        "keterangan": "Surat Tugas / Perjalanan Dinas",
        "is_active": true,
        "created_at": "2026-10-05T00:00:00.000000Z",
        "updated_at": "2026-10-05T00:00:00.000000Z"
    }
}
```

---

## GET /api/arsip/klasifikasi/{id}

> Mengambil detail data referensi klasifikasi surat berdasarkan ID.

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Rincian klasifikasi surat berhasil dimuat.",
    "data": {
        "id": 1,
        "kode": "DI",
        "nama": "SK",
        "kategori": "klasifikasi",
        "keterangan": "Surat Keputusan",
        "is_active": true,
        "created_at": "2026-10-05T00:00:00.000000Z",
        "updated_at": "2026-10-05T00:00:00.000000Z"
    }
}
```

---

## PUT /api/arsip/klasifikasi/{id}

> Memperbarui referensi klasifikasi surat.

### Request Body

```json
{
    "nama": "Surat Keputusan Direktur",
    "keterangan": "Surat keputusan tingkat pimpinan",
    "is_active": true
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Klasifikasi surat berhasil diperbarui.",
    "data": {
        "id": 1,
        "kode": "DI",
        "nama": "Surat Keputusan Direktur",
        "kategori": "klasifikasi",
        "keterangan": "Surat keputusan tingkat pimpinan",
        "is_active": true,
        "created_at": "2026-10-05T00:00:00.000000Z",
        "updated_at": "2026-10-05T00:05:00.000000Z"
    }
}
```

---

## DELETE /api/arsip/klasifikasi/{id}

> Menghapus data referensi klasifikasi surat (soft delete).

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Klasifikasi surat berhasil dihapus."
}
```

### Response Error

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki hak akses untuk mengelola klasifikasi surat."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "kode": ["Kode klasifikasi sudah terdaftar."]
    }
}
```

# MasterSpmbController

> **Modul**: SPMB  
> **Base URL**: `/api/spmb/master`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat/Diperbarui**: 2026-09-21  

Dokumentasi API untuk referensi master SPMB meliputi tahun akademik, jalur pendaftaran, gelombang pendaftaran, dan opsi master SPMB.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/spmb/master/tahun-akademik` | Mendapatkan daftar master tahun akademik aktif | ✅ Admin / Staff |
| GET | `/api/spmb/master/jalur` | Mendapatkan daftar jalur seleksi penerimaan | ✅ Admin / Staff |
| GET | `/api/spmb/master/gelombang` | Mendapatkan daftar gelombang penerimaan berpaginasi | ✅ Admin / Staff |
| GET | `/api/spmb/master/gelombang/{id}` | Mendapatkan detail informasi gelombang penerimaan | ✅ Admin / Staff |
| GET | `/api/spmb/master/options` | Mendapatkan seluruh opsi master SPMB secara ringkas | ✅ Admin / Staff |

---

## [GET] /api/spmb/master/tahun-akademik

> Mengambil daftar master tahun akademik SPMB yang aktif.

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
    "message": "Data tahun akademik berhasil dimuat.",
    "data": [
        {
            "id": 1,
            "kode": "20261",
            "nama": "2026/2027 Ganjil",
            "is_active": true,
            "is_current": true
        },
        {
            "id": 2,
            "kode": "20252",
            "nama": "2025/2026 Genap",
            "is_active": false,
            "is_current": false
        }
    ]
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
    "message": "Anda tidak memiliki izin untuk melihat tahun akademik SPMB."
}
```

---

## [GET] /api/spmb/master/jalur

> Mengambil daftar master jalur seleksi penerimaan mahasiswa baru.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Default | Deskripsi |
|---|---|---|---|
| `search` | string | - | Pencarian kata kunci nama atau kode jalur |
| `status` | boolean | - | Filter status aktif (`true` atau `false`) |
| `sort_by` | string | `created_at` | Kolom pengurutan (`id`, `kode`, `nama`, `created_at`) |
| `sort_order` | string | `desc` | Arah pengurutan (`asc`, `desc`) |
| `per_page` | integer | `15` | Jumlah data per halaman (1 s/d 100) |
| `page` | integer | `1` | Nomor halaman data |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data jalur penerimaan berhasil dimuat.",
    "data": [
        {
            "id": 1,
            "kode": "REG",
            "nama": "Reguler",
            "is_active": true,
            "created_at": "2026-09-21T00:00:00.000000Z"
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
        "status": null,
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
    "message": "Anda tidak memiliki izin untuk melihat jalur masuk SPMB."
}
```

---

## [GET] /api/spmb/master/gelombang

> Mengambil daftar gelombang pendaftaran mahasiswa baru dengan fitur pencarian, filter kriteria, pengurutan whitelist, dan paginasi standar.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Default | Deskripsi |
|---|---|---|---|
| `search` | string | - | Pencarian kata kunci pada nama gelombang |
| `nama` | string | - | Filter spesifik berdasarkan nama gelombang |
| `jalur_masuk_id` | integer | - | Filter berdasarkan ID master jalur penerimaan |
| `tanggal_buka` | string | - | Filter tanggal pembukaan gelombang minimal (format `YYYY-MM-DD`) |
| `tanggal_tutup` | string | - | Filter tanggal penutupan gelombang maksimal (format `YYYY-MM-DD`) |
| `kuota` | string | - | Filter status kuota: `tersedia` atau `penuh` |
| `biaya` | string | - | Filter jenis biaya pendaftaran: `gratis` atau `berbayar` |
| `status` | string | - | Filter status (`draft`, `aktif`, `ditutup`, `selesai`) |
| `sort_by` | string | `created_at` | Kolom pengurutan (`id`, `nama`, `tanggal_buka`, `tanggal_tutup`, `kuota_total`, `biaya_pendaftaran`, `status`, `created_at`) |
| `sort_order` | string | `desc` | Arah urutan (`asc`, `desc`) |
| `per_page` | integer | `15` | Jumlah data per halaman (1 s/d 100) |
| `page` | integer | `1` | Nomor halaman data |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data gelombang berhasil dimuat.",
    "data": [
        {
            "id": 1,
            "nama": "Gelombang 1 Reguler 2026/2027",
            "tahun_akademik_id": 1,
            "jalur_masuk_id": 1,
            "master_biaya_id": 1,
            "tanggal_buka": "2026-01-01",
            "tanggal_tutup": "2026-04-30",
            "tanggal_pengumuman": "2026-05-05",
            "kuota_total": 150,
            "kuota_terisi": 45,
            "biaya_pendaftaran": 250000,
            "status": "aktif",
            "created_at": "2026-09-21T10:00:00.000000Z",
            "updated_at": "2026-09-21T10:00:00.000000Z",
            "jalur_masuk": {
                "id": 1,
                "nama": "Reguler",
                "kode": "REG"
            },
            "master_biaya": {
                "id": 1,
                "nama_biaya": "Pendaftaran S1",
                "nominal": 250000
            },
            "tahun_akademik": {
                "id": 1,
                "nama": "2026/2027 Ganjil"
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
        "nama": null,
        "jalur_masuk_id": null,
        "tanggal_buka": null,
        "tanggal_tutup": null,
        "kuota": null,
        "biaya": null,
        "status": null,
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
    "message": "Anda tidak memiliki akses ke resource SPMB ini."
}
```

---

## [GET] /api/spmb/master/gelombang/{id}

> Mengambil informasi detail satu gelombang pendaftaran berdasarkan ID.

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
    "message": "Data detail gelombang berhasil dimuat.",
    "data": {
        "id": 1,
        "nama": "Gelombang 1 Reguler 2026/2027",
        "tahun_akademik_id": 1,
        "jalur_masuk_id": 1,
        "master_biaya_id": 1,
        "tanggal_buka": "2026-01-01",
        "tanggal_tutup": "2026-04-30",
        "tanggal_pengumuman": "2026-05-05",
        "kuota_total": 150,
        "kuota_terisi": 45,
        "biaya_pendaftaran": 250000,
        "status": "aktif",
        "created_at": "2026-09-21T10:00:00.000000Z",
        "updated_at": "2026-09-21T10:00:00.000000Z",
        "jalur_masuk": {
            "id": 1,
            "nama": "Reguler",
            "kode": "REG"
        },
        "master_biaya": {
            "id": 1,
            "nama_biaya": "Pendaftaran S1",
            "nominal": 250000
        },
        "tahun_akademik": {
            "id": 1,
            "nama": "2026/2027 Ganjil"
        }
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
    "message": "Anda tidak memiliki izin untuk melihat detail gelombang SPMB."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Gelombang penerimaan tidak ditemukan."
}
```

---

## [GET] /api/spmb/master/options

> Mendapatkan seluruh opsi master SPMB secara ringkas (tahun akademik, jalur, gelombang) untuk dropdown formulir pendaftaran.

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
    "message": "Opsi master SPMB berhasil dimuat.",
    "data": {
        "tahun_akademik": [
            {
                "id": 1,
                "nama": "2026/2027 Ganjil",
                "is_current": true
            }
        ],
        "jalur_masuk": [
            {
                "id": 1,
                "kode": "REG",
                "nama": "Reguler"
            }
        ],
        "gelombang": [
            {
                "id": 1,
                "nama": "Gelombang 1 Reguler 2026/2027",
                "status": "aktif"
            }
        ]
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
    "message": "Anda tidak memiliki akses ke opsi SPMB."
}
```

---

> **Catatan Keamanan:** Seluruh operasi referensi master SPMB dilindungi oleh autentikasi Sanctum dan otorisasi berbasis peran. Password dan kredensial sensitif pengguna tidak pernah disertakan dalam respons API. Penghapusan dan perubahan status mengikuti aturan integritas referensial dan soft delete pada data transaksional pendaftar aktif.

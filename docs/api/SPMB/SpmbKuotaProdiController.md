# SpmbKuotaProdiController

> **Modul**: SPMB  
> **Base URL**: `/api/spmb/kuota-prodi`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat/Diperbarui**: 2026-09-21  

Dokumentasi API untuk manajemen alokasi dan kuota penerimaan calon mahasiswa baru per program studi dan tahun akademik.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/spmb/kuota-prodi` | Mendapatkan daftar kuota program studi berpaginasi | ✅ Admin |
| POST | `/api/spmb/kuota-prodi` | Menambah atau memperbarui (upsert) kuota prodi | ✅ Admin |
| GET | `/api/spmb/kuota-prodi/{id}` | Mendapatkan detail kuota prodi berdasarkan ID | ✅ Admin |
| PUT/PATCH | `/api/spmb/kuota-prodi/{id}` | Memperbarui jumlah kuota total program studi | ✅ Admin |
| DELETE | `/api/spmb/kuota-prodi/{id}` | Menghapus kuota program studi | ✅ Admin |

---

## [GET] /api/spmb/kuota-prodi

> Menampilkan daftar kuota program studi dengan filter, pencarian, pengurutan, dan paginasi standar.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Default | Deskripsi |
|---|---|---|---|
| `search` | string | - | Kata kunci pencarian nama program studi |
| `tahun_akademik_id` | integer | - | Filter berdasarkan ID tahun akademik |
| `program_studi_id` | integer | - | Filter berdasarkan ID program studi |
| `status_kuota` | string | - | Filter kuota: `tersedia` atau `penuh` |
| `min_kuota` | integer | - | Filter kuota minimal |
| `sort_by` | string | `created_at` | Kolom pengurutan (`id`, `tahun_akademik_id`, `program_studi_id`, `kuota_total`, `kuota_terisi`, `created_at`) |
| `sort_order` | string | `desc` | Arah pengurutan (`asc`, `desc`) |
| `per_page` | integer | `15` | Jumlah data per halaman (1 s/d 100) |
| `page` | integer | `1` | Nomor halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data kuota prodi berhasil dimuat.",
    "data": [
        {
            "id": 1,
            "tahun_akademik_id": 1,
            "program_studi_id": 2,
            "kuota_total": 120,
            "kuota_terisi": 45,
            "created_at": "2026-09-21T10:00:00.000000Z",
            "updated_at": "2026-09-21T10:00:00.000000Z",
            "tahun_akademik": {
                "id": 1,
                "nama_tahun": "2026/2027",
                "is_active": true
            },
            "program_studi": {
                "id": 2,
                "kode_prodi": "INF",
                "nama_prodi": "Informatika",
                "jenjang": "S1"
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
        "tahun_akademik_id": null,
        "program_studi_id": null,
        "status_kuota": null,
        "min_kuota": null,
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
    "message": "Akses ditolak. Anda tidak memiliki izin untuk melihat data kuota prodi."
}
```

---

## [POST] /api/spmb/kuota-prodi

> Menyimpan kuota prodi baru atau memperbarui data kuota yang telah ada jika kombinasi tahun akademik dan prodi sudah tercatat.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Content-Type` | `application/json` | ✅ |
| `Accept` | `application/json` | ✅ |

### Request Body

```json
{
    "tahun_akademik_id": 1,
    "program_studi_id": 2,
    "kuota_total": 120
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Kuota prodi berhasil disimpan.",
    "data": {
        "id": 1,
        "tahun_akademik_id": 1,
        "program_studi_id": 2,
        "kuota_total": 120,
        "kuota_terisi": 0,
        "created_at": "2026-09-21T10:00:00.000000Z",
        "updated_at": "2026-09-21T10:00:00.000000Z",
        "tahun_akademik": {
            "id": 1,
            "nama_tahun": "2026/2027"
        },
        "program_studi": {
            "id": 2,
            "nama_prodi": "Informatika"
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
    "message": "Akses ditolak. Anda tidak memiliki izin untuk menambahkan kuota prodi."
}
```

**422 Unprocessable Content**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "tahun_akademik_id": [
            "Tahun akademik wajib dipilih."
        ],
        "kuota_total": [
            "Kuota total harus bernilai minimal 1."
        ]
    }
}
```

---

## [GET] /api/spmb/kuota-prodi/{id}

> Mengambil informasi detail kuota program studi berdasarkan ID.

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
    "message": "Data detail kuota prodi berhasil dimuat.",
    "data": {
        "id": 1,
        "tahun_akademik_id": 1,
        "program_studi_id": 2,
        "kuota_total": 120,
        "kuota_terisi": 45,
        "created_at": "2026-09-21T10:00:00.000000Z",
        "updated_at": "2026-09-21T10:00:00.000000Z",
        "tahun_akademik": {
            "id": 1,
            "nama_tahun": "2026/2027"
        },
        "program_studi": {
            "id": 2,
            "nama_prodi": "Informatika"
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
    "message": "Akses ditolak. Anda tidak memiliki izin untuk melihat detail kuota prodi."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Kuota prodi tidak ditemukan."
}
```

---

## [PUT/PATCH] /api/spmb/kuota-prodi/{id}

> Memperbarui batasan kuota total penerimaan program studi.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Content-Type` | `application/json` | ✅ |
| `Accept` | `application/json` | ✅ |

### Request Body

```json
{
    "kuota_total": 150
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Kuota prodi berhasil diperbarui.",
    "data": {
        "id": 1,
        "tahun_akademik_id": 1,
        "program_studi_id": 2,
        "kuota_total": 150,
        "kuota_terisi": 45,
        "created_at": "2026-09-21T10:00:00.000000Z",
        "updated_at": "2026-09-21T10:15:00.000000Z"
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
    "message": "Akses ditolak. Anda tidak memiliki izin untuk memperbarui kuota prodi."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Kuota prodi tidak ditemukan."
}
```

**422 Unprocessable Content**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "kuota_total": [
            "Kuota total harus bernilai minimal 1."
        ]
    }
}
```

---

## [DELETE] /api/spmb/kuota-prodi/{id}

> Menghapus data kuota program studi dari sistem.

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
    "message": "Kuota prodi berhasil dihapus.",
    "data": {
        "id": 1
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
    "message": "Akses ditolak. Anda tidak memiliki izin untuk menghapus kuota prodi."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Kuota prodi tidak ditemukan."
}
```

---

> **Catatan Keamanan & Integritas:**  
> - Atribut password dan kredensial sensitif pengguna tidak pernah dikembalikan dalam response API kuota prodi.  
> - Operasi penghapusan kuota program studi mengikuti integritas data relasional (`soft-delete` / integritas referensial). Kuota yang telah memiliki pendaftar terverifikasi tidak dapat dihapus secara sepihak untuk menjaga konsistensi rekapitulasi SPMB.

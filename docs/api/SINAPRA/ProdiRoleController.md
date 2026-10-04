# ProdiRoleController

> **Modul**: SINAPRA (Sarana, Prasarana, & Aset)  
> **Base URL**: `/api/sinapra/master`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-10-01  
> **Diperbarui**: 2026-10-01  

Controller ini mengelola **pemetaan (plotting) Program Studi ke Role Laboran Pengampu**. Data Program Studi dibaca langsung secara dinamis dari modul SIAKAD (`siakad_program_studi`), sehingga setiap penambahan prodi baru atau perubahan data di SIAKAD akan otomatis tersinkronisasi. Admin SINAPRA hanya memiliki wewenang mengatur plotting role, tanpa kemampuan mengubah, menambah, atau menghapus entitas Program Studi itu sendiri.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/sinapra/master/prodi-roles` | Daftar Program Studi beserta role laboran yang di-plot | ✅ Admin SINAPRA |
| GET | `/api/sinapra/master/prodi-roles/roles-options` | Daftar master role laboran yang tersedia untuk opsi pemilihan | ✅ Admin SINAPRA |
| POST / PUT | `/api/sinapra/master/prodi-roles/{prodiId}` | Menyimpan / memperbarui mapping role laboran untuk suatu Program Studi | ✅ Admin SINAPRA |

---

## GET /api/sinapra/master/prodi-roles

> Menampilkan daftar seluruh Program Studi aktif dari SIAKAD beserta daftar role laboran yang ditugaskan di SINAPRA.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Pencarian kode prodi atau nama program studi |
| `jenjang` | string | ❌ | — | Filter jenjang prodi: `D3`, `D4`, `S1`, `S2` |
| `fakultas_id` | integer | ❌ | — | Filter berdasarkan ID fakultas |
| `status_plotting` | string | ❌ | — | Opsi: `terplot` (memiliki minimal 1 role) atau `belum_terplot` (belum memiliki role) |
| `sort_by` | string | ❌ | `nama` | Kolom pengurutan (`nama`, `kode_prodi`, `jenjang`, `created_at`) |
| `sort_order` | string | ❌ | `asc` | Arah urutan: `asc` atau `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Nomor halaman data |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar plotting program studi ke role laboran berhasil diambil",
    "data": [
        {
            "id": 2,
            "kode_prodi": "TRPL-D4",
            "nama": "Teknologi Rekayasa Perangkat Lunak S1 Terapan",
            "jenjang": "D4",
            "fakultas_id": 1,
            "fakultas": {
                "id": 1,
                "kode": "FTI",
                "nama": "Fakultas Teknologi Informasi"
            },
            "sinapra_roles": [
                {
                    "id": 15,
                    "name": "Admin Laboratorium",
                    "slug": "admin_laboratorium",
                    "description": "Pengelola operasional laboratorium prodi",
                    "pivot": {
                        "program_studi_id": 2,
                        "role_id": 15,
                        "keterangan": "Pengampu Lab Rekayasa Perangkat Lunak"
                    }
                }
            ]
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
        "jenjang": null,
        "fakultas_id": null,
        "status_plotting": null,
        "sort_by": "nama",
        "sort_order": "asc"
    }
}
```

---

## GET /api/sinapra/master/prodi-roles/roles-options

> Mengambil daftar role RBAC aktif dari `core_roles` untuk ditampilkan pada opsi dropdown/multiselect formulir plotting.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar role laboran tersedia berhasil dimuat",
    "data": [
        {
            "id": 14,
            "name": "Admin SINAPRA",
            "slug": "admin_sinapra",
            "description": "Administrator Sarana & Prasarana"
        },
        {
            "id": 15,
            "name": "Admin Laboratorium",
            "slug": "admin_laboratorium",
            "description": "Pengelola operasional laboratorium"
        }
    ]
}
```

---

## POST /api/sinapra/master/prodi-roles/{prodiId}

> Menyimpan atau memperbarui plotting role laboran untuk suatu Program Studi. Endpoint ini juga dapat diakses dengan metode `PUT`.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Content-Type` | `application/json` | ✅ |
| `Accept` | `application/json` | ✅ |

### Request Body

```json
{
    "role_id": 15
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Plotting role laboran untuk Program Studi Teknologi Rekayasa Perangkat Lunak S1 Terapan berhasil disimpan",
    "data": {
        "id": 2,
        "kode_prodi": "TRPL-D4",
        "nama": "Teknologi Rekayasa Perangkat Lunak S1 Terapan",
        "jenjang": "D4",
        "fakultas_id": 1,
        "fakultas": {
            "id": 1,
            "kode": "FTI",
            "nama": "Fakultas Teknologi Informasi"
        },
        "sinapra_roles": [
            {
                "id": 15,
                "name": "Admin Laboratorium",
                "slug": "admin_laboratorium",
                "description": "Pengelola operasional laboratorium",
                "pivot": {
                    "program_studi_id": 2,
                    "role_id": 15,
                    "keterangan": "Laboran penanggung jawab ruang praktikum software TRPL"
                }
            }
        ]
    }
}
```

# IamApiIntegrationController

> **Modul**: IAM & Auth Center  
> **Base URL**: `/api/v1` (atau `/api`)  
> **Autentikasi**: Bearer Token (Sanctum / Passport)  
> **Dibuat**: 2026-10-12  
> **Diperbarui**: 2026-10-12

Controller ini mengelola registrasi integrasi API eksternal tingkat sistem yang dikunci oleh developer (seperti endpoint migrasi SPMB dari sistem lama) dan memberikan akses RU (Read & Update) bagi Admin IAM untuk mengonfigurasi URL endpoint, kredensial token, dan menguji koneksi (*health check*).

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/api-integrations` | Daftar integrasi API sistem dengan filter & paginasi | ✅ Permission `iam.api_integration.read` |
| GET | `/api/api-integrations/{id}` | Detail konfigurasi & kontrak schema integrasi | ✅ Permission `iam.api_integration.read` |
| PUT | `/api/api-integrations/{id}` | Update URL API, Token, Auth Type, dan Status | ✅ Permission `iam.api_integration.manage` |
| POST | `/api/api-integrations/{id}/test` | Uji koneksi live (ping request & cek respons) | ✅ Permission `iam.api_integration.manage` |

---

## GET /api/api-integrations

Mengambil daftar seluruh integrasi API sistem yang terdaftar di database.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Pencarian nama, key, module, atau deskripsi |
| `module` | string | ❌ | — | Filter kode modul (misal `spmb`, `siakad`) |
| `is_active` | boolean | ❌ | — | Filter status aktif (`true` / `false`) |
| `sort_by` | string | ❌ | `created_at` | Kolom sortir (`name`, `key`, `module`, `http_method`, `is_active`, `last_synced_at`, `created_at`) |
| `sort_order` | string | ❌ | `desc` | Arah urutan (`asc` / `desc`) |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Nomor halaman |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar integrasi API berhasil diambil",
    "data": [
        {
            "id": 1,
            "key": "spmb_pendaftaran",
            "name": "SPMB Pendaftaran",
            "module": "spmb",
            "http_method": "GET",
            "description": "Endpoint API migrasi data calon mahasiswa baru / pendaftaran dari sistem lama",
            "expected_schema": {
                "type": "array",
                "items": {
                    "type": "object",
                    "properties": {
                        "no_pendaftaran": { "type": "string" },
                        "nama_lengkap": { "type": "string" },
                        "nik": { "type": "string" },
                        "email": { "type": "string" },
                        "no_hp": { "type": "string" },
                        "tempat_lahir": { "type": "string" },
                        "tanggal_lahir": { "type": "string" },
                        "jenis_kelamin": { "type": "string" },
                        "jalur_pendaftaran": { "type": "string" },
                        "pilihan_prodi_1": { "type": "string" },
                        "pilihan_prodi_2": { "type": "string" },
                        "status": { "type": "string" },
                        "status_pembayaran": { "type": "string" }
                    },
                    "required": ["no_pendaftaran", "nama_lengkap", "nik", "email", "no_hp", "tempat_lahir", "tanggal_lahir", "jenis_kelamin", "pilihan_prodi_1"]
                }
            },
            "url_api": "https://api-lama.kampus.ac.id/api/pendaftar",
            "token": "secret-token-key",
            "auth_type": "bearer",
            "is_active": true,
            "last_synced_at": "2026-10-12T09:30:00.000000Z",
            "last_status": "success",
            "created_at": "2026-10-12T09:00:00.000000Z",
            "updated_at": "2026-10-12T09:30:00.000000Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1,
        "from": 1,
        "to": 1,
        "filters": {
            "search": null,
            "module": null,
            "is_active": null,
            "sort_by": "created_at",
            "sort_order": "desc"
        }
    }
}
```

---

## GET /api/api-integrations/{id}

Mengambil detail satu integrasi API termasuk kontrak schema format JSON yang diharapkan.

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Detail integrasi API berhasil diambil",
    "data": {
        "id": 1,
        "key": "spmb_pendaftaran",
        "name": "SPMB Pendaftaran",
        "module": "spmb",
        "http_method": "GET",
        "description": "Endpoint API migrasi data calon mahasiswa baru / pendaftaran dari sistem lama",
        "expected_schema": { ... },
        "url_api": "https://api-lama.kampus.ac.id/api/pendaftar",
        "token": "secret-token-key",
        "auth_type": "bearer",
        "is_active": true,
        "last_synced_at": null,
        "last_status": null
    }
}
```

---

## PUT /api/api-integrations/{id}

Mengubah URL endpoint, kredensial auth token, tipe autentikasi, serta status aktif/nonaktif.

### Request Body

```json
{
    "url_api": "https://api-lama.kampus.ac.id/api/pendaftar",
    "token": "my-secret-token",
    "auth_type": "bearer",
    "is_active": true
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Konfigurasi integrasi API berhasil diperbarui",
    "data": {
        "id": 1,
        "key": "spmb_pendaftaran",
        "name": "SPMB Pendaftaran",
        "url_api": "https://api-lama.kampus.ac.id/api/pendaftar",
        "auth_type": "bearer",
        "is_active": true
    }
}
```

---

## POST /api/api-integrations/{id}/test

Melakukan *live testing* koneksi ke URL API target. Endpoint ini dapat menguji nilai URL/Token yang dikirimkan di request body secara langsung (tanpa harus disimpan dulu) atau menguji nilai yang tersimpan di DB.

### Request Body (Opsional untuk pengujian ad-hoc)

```json
{
    "url_api": "https://api-lama.kampus.ac.id/api/pendaftar",
    "token": "optional-override-token",
    "auth_type": "bearer"
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Tes koneksi berhasil terhubung (200 OK dalam 124.5ms)",
    "data": {
        "http_status": 200,
        "is_success": true,
        "duration_ms": 124.5,
        "headers": {
            "content-type": ["application/json; charset=utf-8"]
        },
        "response_sample": [
            {
                "nomor_pendaftaran": "PMB2024001",
                "nama_lengkap": "Budi Santoso",
                "nik": "3201012345670001",
                "email": "budi@gmail.com",
                "nomor_hp": "081234567890"
            }
        ],
        "expected_schema": { ... }
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "No query results for model [App\\Models\\IamApiIntegration] 999"
}
```

**422 Unprocessable Content**
```json
{
    "status": "error",
    "message": "URL API belum ditentukan untuk pengujian koneksi.",
    "errors": {
        "url_api": [
            "Format URL API tidak valid."
        ]
    }
}
```

**502 Bad Gateway / Connection Timeout**
```json
{
    "status": "error",
    "message": "Tes koneksi gagal terhubung ke server tujuan. Pastikan URL dapat dijangkau."
}
```

# IamApiIntegrationController

> **Modul**: IAM & Auth Center  
> **Base URL**: `/api/v1` (atau `/api`)  
> **Autentikasi**: Bearer Token (Sanctum / Passport)  
> **Dibuat**: 2026-10-12  
> **Diperbarui**: 2026-11-12

Controller ini mengelola registrasi integrasi API eksternal tingkat sistem yang dikunci oleh developer dan memberikan akses bagi Admin IAM untuk mengonfigurasi URL endpoint, kredensial token, menguji koneksi, serta menjalankan sinkronisasi data yang didukung.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/api-integrations` | Daftar integrasi API sistem dengan filter & paginasi | ✅ Permission `iam.api_integration.read` |
| GET | `/api/api-integrations/{id}` | Detail konfigurasi & kontrak schema integrasi | ✅ Permission `iam.api_integration.read` |
| PUT | `/api/api-integrations/{id}` | Update URL API, Token, Auth Type, dan Status | ✅ Permission `iam.api_integration.manage` |
| POST | `/api/api-integrations/{id}/test` | Uji koneksi live (ping request & cek respons) | ✅ Permission `iam.api_integration.manage` |
| POST | `/api/api-integrations/{id}/sync` | Ambil data sumber dan sinkronkan ke master Program Studi SIAKAD | ✅ Permission `iam.api_integration.manage` |

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
            "key": "siakad_program_studi",
            "name": "Migrasi Program Studi SIAKAD",
            "module": "siakad",
            "http_method": "GET",
            "description": "Sumber data read-only untuk migrasi program studi ke master SIAKAD",
            "expected_schema": {
                "type": "array",
                "items": {
                    "type": "object",
                    "properties": {
                        "source_id": { "type": ["string", "integer"] },
                        "kode_prodi": { "type": "string" },
                        "kode_prodi_dikti": { "type": ["string", "null"] },
                        "nama": { "type": "string" },
                        "jenjang": { "type": "string" },
                        "akreditasi": { "type": ["string", "null"] },
                        "is_active": { "type": "boolean" },
                        "fakultas": { "type": ["string", "null"] }
                    },
                    "required": ["source_id", "kode_prodi", "nama", "jenjang", "is_active"]
                }
            },
            "url_api": "https://sumber.kampus.ac.id/api/program-studi",
            "token_configured": false,
            "auth_type": "bearer",
            "is_active": true,
            "last_synced_at": null,
            "last_status": null,
            "created_at": "2026-11-12T09:00:00.000000Z",
            "updated_at": "2026-11-12T09:00:00.000000Z"
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
        "key": "siakad_program_studi",
        "name": "Migrasi Program Studi SIAKAD",
        "module": "siakad",
        "http_method": "GET",
        "description": "Sumber data read-only untuk migrasi program studi ke master SIAKAD",
        "expected_schema": { ... },
        "url_api": "https://sumber.kampus.ac.id/api/program-studi",
        "token_configured": false,
        "auth_type": "bearer",
        "is_active": true,
        "last_synced_at": null,
        "last_status": null
    }
}
```

---

## PUT /api/api-integrations/{id}

Mengubah URL endpoint, kredensial auth token, tipe autentikasi, serta status aktif/nonaktif. Respons hanya mengembalikan `token_configured`, bukan nilai token.

### Request Body

```json
{
    "url_api": "https://sumber.kampus.ac.id/api/program-studi",
    "token": "my-secret-token",
    "auth_type": "bearer",
    "is_active": true
}
```

Jika hanya mengubah pengaturan lain, hilangkan field `token` atau kirim `null`/string kosong. Token tersimpan akan dipertahankan. Kirim token non-kosong hanya untuk menggantinya.

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Konfigurasi integrasi API berhasil diperbarui",
    "data": {
        "id": 1,
        "key": "siakad_program_studi",
        "name": "Migrasi Program Studi SIAKAD",
        "url_api": "https://sumber.kampus.ac.id/api/program-studi",
        "token_configured": true,
        "auth_type": "bearer",
        "is_active": true
    }
}
```

Nilai `token_configured` hanya memberi tahu apakah kredensial sudah tersimpan. Nilai token asli tidak pernah disertakan dalam respons API.

---

## POST /api/api-integrations/{id}/test

Melakukan *live testing* koneksi ke URL API target. Endpoint ini dapat menguji nilai URL/Token yang dikirimkan di request body secara langsung (tanpa harus disimpan dulu) atau menguji nilai yang tersimpan di DB.

### Request Body (Opsional untuk pengujian ad-hoc)

```json
{
    "url_api": "https://sumber.kampus.ac.id/api/program-studi",
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
                "source_id": 101,
                "kode_prodi": "TI-D3",
                "kode_prodi_dikti": null,
                "nama": "Teknik Informatika",
                "jenjang": "D3",
                "akreditasi": null,
                "is_active": true,
                "fakultas": null
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

---

## POST /api/api-integrations/{id}/sync

Mengambil seluruh halaman data dari konfigurasi endpoint yang tersimpan, memvalidasi kontrak dan referensi master, lalu menerapkan data ke target yang didukung. Saat ini key `siakad_program_studi` disinkronkan ke tabel `siakad_program_studi` berdasarkan `kode_prodi`.

### Request Headers

| Header | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `Authorization` | string | Ya | Bearer token autentikasi |
| `Accept` | string | Ya | `application/json` |

### Request Body

Tidak memerlukan body. URL, token, tipe autentikasi, dan status aktif menggunakan konfigurasi integrasi yang tersimpan.

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Sinkronisasi berhasil dijalankan.",
    "data": {
        "source_total": 2,
        "created": 1,
        "updated": 1,
        "skipped": 0,
        "skipped_items": []
    }
}
```

### Perilaku dan Batasan

- Respons sumber dapat berupa array JSON, `{ "data": [...] }`, atau respons paginator dengan `meta.last_page`; proses dibatasi sampai 100 halaman.
- Data dicocokkan berdasarkan `source_id` yang disimpan pada `siakad_program_studi.source_id`; data lama tanpa mapping external memakai fallback `kode_prodi`. Sync ulang memperbarui row yang sama walau kode sumber berubah dan tidak menghapus row tujuan yang tidak ada pada sumber.
- Jenjang dan akreditasi harus cocok dengan item aktif pada master referensi SIAKAD. Item tidak valid masuk ke `skipped_items` beserta alasan.
- `fakultas_id` baru tetap null jika fakultas tidak dikirim. Fakultas yang dikirim harus cocok dengan kode atau nama fakultas aktif; relasi fakultas existing tidak dihapus ketika sumber tidak mengirim fakultas.
- Program studi yang sudah soft-delete tidak dibuat ulang otomatis.
- Respons sukses memuat maksimal 100 rincian `skipped_items`; hitungan `skipped` tetap total keseluruhan.

### Contoh Respons dengan Baris Dilewati

```json
{
    "status": "success",
    "message": "Sinkronisasi berhasil dijalankan.",
    "data": {
        "source_total": 1,
        "created": 0,
        "updated": 0,
        "skipped": 1,
        "skipped_items": [
            {
                "source_id": 12,
                "kode_prodi": "TI-D3",
                "row": 1,
                "reason": "Kode jenjang tidak tersedia pada master referensi SIAKAD."
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
    "message": "This action is unauthorized."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data integrasi API tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "URL API belum dikonfigurasi."
}
```

**502 Bad Gateway**
```json
{
    "status": "error",
    "message": "Tidak dapat terhubung ke API sumber."
}
```

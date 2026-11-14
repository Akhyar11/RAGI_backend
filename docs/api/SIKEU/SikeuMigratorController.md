# SikeuMigratorController

> **Modul**: Keuangan & Akuntansi (SIKEU)  
> **Base URL**: `/api/v1/sikeu/migrator`  
> **Autentikasi**: Bearer Token (Sanctum / Passport)  
> **Dibuat**: 2026-11-13  
> **Diperbarui**: 2026-11-14  

Controller ini mengelola orkestrasi migrasi dan sinkronisasi data transaksi keuangan dari berbagai sistem legacy (`indonusa`, `sikeudb`, atau via stream endpoint API eksternal) ke sistem RAG SIKEU, memantau ringkasan status migrasi, menjalankan job asynchronous via Laravel Queue (`MigrateSikeuJob`), serta mengelola pemetaan COA pada tabel `sikeu_mig_akun_mapping`.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/migrator/status` | Ringkasan statistik data migrasi, status koneksi & konfigurasi | ✅ Permission `sikeu.akuntansi.read` |
| POST | `/api/v1/sikeu/migrator/start` | Memulai migrasi background via Laravel Queue Job (`MigrateSikeuJob`) | ✅ Permission `sikeu.akuntansi.manage` |
| GET | `/api/v1/sikeu/migrator/mappings` | Daftar mapping akun legacy (`sikeu_mig_akun_mapping`) dengan filter & paginasi | ✅ Permission `sikeu.akuntansi.read` |
| PUT | `/api/v1/sikeu/migrator/mappings/{id}` | Update target COA akun baru untuk kode perkiraan legacy | ✅ Permission `sikeu.akuntansi.manage` |
| POST | `/api/v1/sikeu/migrator/reset` | Reset data jurnal/pembayaran hasil migrasi (hanya yang memiliki legacy_source) | ✅ Permission `sikeu.akuntansi.manage` |

---

## 1. GET /api/v1/sikeu/migrator/status

Mengambil ringkasan data yang sudah terpindah ke RAG, status konektivitas database sumber, dan konfigurasi migrator.

> [!NOTE]
> - Uji koneksi (`connections`) bersifat pasif/read-only (hanya memverifikasi koneksi PDO) dan tidak menulis atau memodifikasi data target.
> - Kredensial token upstream tidak pernah diekspos; API hanya mengembalikan flag `token_configured` bernilai boolean.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Status migrasi data SIKEU berhasil diambil",
    "data": {
        "summary": {
            "total_jurnal": 360489,
            "total_pembayaran": 197528,
            "total_mappings": 298,
            "mapped_accounts": 298,
            "unmapped_accounts": 0,
            "total_unresolved": 0
        },
        "jurnal_stats": [
            {
                "source": "INPUT_SISTEM_RAG",
                "count": 15,
                "nominal": 50295000,
                "min_date": "2026-08-31",
                "max_date": "2026-10-28"
            },
            {
                "source": "LEGACY_INDONUSA_STMIK",
                "count": 318500,
                "nominal": 612450000000,
                "min_date": "2011-01-20",
                "max_date": "2023-12-31"
            }
        ],
        "pembayaran_stats": [
            {
                "source": "LEGACY_INDONUSA_STMIK",
                "count": 197528,
                "nominal": 598200000000,
                "min_date": "2011-01-20",
                "max_date": "2023-12-31"
            }
        ],
        "unresolved_stats": [],
        "sync_states": [
            {
                "module_id": "SIKEU",
                "source_name": "mysql_indonusa",
                "entity_type": "jurnal",
                "last_record_date": "2023-12-31 23:59:59",
                "last_sync_time": "2026-11-13 18:35:00",
                "total_records": 318500
            }
        ],
        "connections": {
            "mysql_indonusa": {
                "status": "connected",
                "message": "Koneksi berhasil"
            },
            "mysql_sikeudb": {
                "status": "connected",
                "message": "Koneksi berhasil"
            }
        },
        "config": {
            "mode": "direct_db",
            "auth_scheme": "bearer",
            "api_url": "",
            "token_configured": true,
            "batch_size": 500
        }
    }
}
```

---

## 2. POST /api/v1/sikeu/migrator/start

Memicu eksekusi proses migrasi data di latar belakang (*background queue* via `MigrateSikeuJob`) agar request web tidak mengalami timeout.

> [!IMPORTANT]
> - URL API dan token integrasi dibaca langsung dari konfigurasi server terenkripsi (`config/sikeu.php`) untuk mencegah eksploitasi Server-Side Request Forgery (SSRF) dan kebocoran kredensial.
> - Proses migrasi bersifat idempoten: baris data yang sudah tersinkronisasi tidak akan diduplikasi (dicek berdasarkan `legacy_id` dan `legacy_source`).
> - Mahasiswa yang tidak terdaftar di sistem SIAKAD dicatat ke tabel staging `sikeu_mig_unresolved` dan dilewati (`skipped`), sehingga tidak memunculkan data tagihan/pembayaran yatim (*orphan*).
> - Paginasi sinkronisasi API upstream memiliki batas aman (`max_pages: 1000`) dan pelacakan sidik jari (*fingerprint loop detection*) untuk mencegah infinite loop.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body
```json
{
    "source": "all",
    "type": "jurnal",
    "mode": "direct_db",
    "auth_scheme": "bearer",
    "limit": 0,
    "batch_size": 500
}
```

### Parameter Request

| Field | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `source` | string | ❌ | `all` | Sumber data: `all`, `indonusa`, `sikeudb` |
| `type` | string | ❌ | `all` | Tipe entitas: `all`, `jurnal`, `pembayaran` |
| `mode` | string | ❌ | `direct_db` | Moda pengambilan: `direct_db` atau `api` |
| `auth_scheme` | string | ❌ | `bearer` | Skema autentikasi API upstream: `bearer` atau `header_key` |
| `limit` | integer | ❌ | `0` | Batas maksimum record (0 = seluruh data) |
| `batch_size` | integer | ❌ | `500` | Ukuran chunking proses database (maks. 5000) |

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Migrasi data SIKEU telah dijadwalkan di background queue.",
    "data": {
        "options": {
            "source": "all",
            "type": "jurnal",
            "mode": "direct_db",
            "auth_scheme": "bearer",
            "limit": 0,
            "batch_size": 500,
            "user_id": 1
        },
        "dispatched_at": "2026-11-14T09:20:00.000000Z"
    }
}
```

> **Contoh Payload Hasil Job Sync (Tercatat di Audit Log & Queue Result)**:
```json
{
    "mode": "direct_db",
    "fetched": 1500,
    "created": 1490,
    "updated": 0,
    "skipped": 10,
    "failed": 0,
    "skip_reasons": {
        "duplicate": 6,
        "missing_mahasiswa": 3,
        "unmapped_account": 1
    }
}
```

---

## 3. GET /api/v1/sikeu/migrator/mappings

Menampilkan daftar seluruh pemetaan kode perkiraan legacy ke COA modern RAG dari tabel `sikeu_mig_akun_mapping`.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters
| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari kode lama, nama lama, atau kode COA target |
| `status` | string | ❌ | — | Filter `mapped` (sudah ada akun target) atau `unmapped` |
| `sort_by` | string | ❌ | `created_at` | Kolom sortir: `created_at`, `kd_perkiraan_lama`, `nama_akun_lama`, `kode_akun_baru` |
| `sort_order` | string | ❌ | `desc` | Arah urutan (`asc` / `desc`) |
| `per_page` | integer | ❌ | `15` | Limit per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Nomor halaman (default: 1) |

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Daftar mapping akun legacy berhasil diambil",
    "data": [
        {
            "id": 272,
            "kd_perkiraan_lama": "998",
            "nama_akun_lama": "SALDO AWAL EKUITAS",
            "akun_id_baru": 32,
            "kode_akun_baru": "301.03",
            "created_at": "2026-11-13T09:20:38.000000Z",
            "updated_at": "2026-11-13T09:20:38.000000Z",
            "akun_baru": {
                "id": 32,
                "kode_akun": "301.03",
                "nama_akun": "Ekuitas Saldo Awal",
                "kelompok": "ekuitas",
                "saldo_normal": "kredit",
                "is_active": true
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 298,
        "last_page": 20,
        "from": 1,
        "to": 15
    }
}
```

---

## 4. PUT /api/v1/sikeu/migrator/mappings/{id}

Memperbarui target akun COA untuk sebuah kode perkiraan legacy pada tabel `sikeu_mig_akun_mapping`.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body
```json
{
    "akun_id_baru": 32,
    "nama_akun_lama": "SALDO AWAL EKUITAS"
}
```

### Parameter Request

| Field | Type | Required | Deskripsi |
|---|---|---|---|
| `akun_id_baru` | integer | ✅ | Relational entity ID pada tabel `sikeu_akun_keuangan` |
| `nama_akun_lama` | string | ❌ | Label nama deskriptif akun legacy |

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Mapping akun legacy berhasil diperbarui",
    "data": {
        "id": 272,
        "kd_perkiraan_lama": "998",
        "nama_akun_lama": "SALDO AWAL EKUITAS",
        "akun_id_baru": 32,
        "kode_akun_baru": "301.03",
        "created_at": "2026-11-13T09:20:38.000000Z",
        "updated_at": "2026-11-14T09:20:00.000000Z",
        "akun_baru": {
            "id": 32,
            "kode_akun": "301.03",
            "nama_akun": "Ekuitas Saldo Awal",
            "kelompok": "ekuitas",
            "saldo_normal": "kredit",
            "is_active": true
        }
    }
}
```

---

## 5. POST /api/v1/sikeu/migrator/reset

Mereset data hasil migrasi (jurnal dan/atau pembayaran yang memiliki `legacy_source`) tanpa menyentuh data native RAG atau modul lain.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body
```json
{
    "confirm": true,
    "type": "jurnal",
    "source": "all"
}
```

### Parameter Request

| Field | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `confirm` | boolean | ✅ | — | Konfirmasi eksplisit penghapusan data (wajib true/accepted) |
| `type` | string | ❌ | `jurnal` | Tipe data yang dibersihkan: `all`, `jurnal`, `pembayaran` |
| `source` | string | ❌ | `all` | Sumber asal migrasi: `all`, `indonusa`, `sikeudb` |

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Data hasil migrasi berhasil dibersihkan",
    "data": {
        "jurnal_dihapus": 44908,
        "detail_jurnal_dihapus": 89816,
        "pembayaran_dihapus": 0,
        "unresolved_dibersihkan": 0
    }
}
```

---

## Contoh Response Error Standar

### 400 Bad Request
```json
{
    "status": "error",
    "message": "Sumber migrasi tidak valid atau belum didukung."
}
```

### 401 Unauthorized
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

### 403 Forbidden
```json
{
    "status": "error",
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

### 404 Not Found
```json
{
    "status": "error",
    "message": "Mapping akun legacy tidak ditemukan."
}
```

### 422 Unprocessable Entity
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "akun_id_baru": [
            "Akun target tidak ditemukan dalam bagan akun (COA) aktif."
        ]
    }
}
```

### 500 Internal Server Error
```json
{
    "status": "error",
    "message": "Terjadi kesalahan internal pada server."
}
```

# CutiController

> **Modul**: SIMPEG / **Base URL**: /api/simpeg/cuti / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-09-26

Modul ini mengelola permohonan cuti pegawai (tahunan, sakit, melahirkan, alasan penting, besar), validasi kuota dan dokumen pendukung, persetujuan/penolakan oleh pimpinan/SDM, serta notifikasi terintegrasi WhatsApp dan Email.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/simpeg/cuti` | Daftar pengajuan cuti pegawai | ✅ Bearer |
| POST | `/api/simpeg/cuti` | Buat permohonan cuti baru | ✅ Bearer |
| PATCH | `/api/simpeg/cuti/{id}/status` | Persetujuan / penolakan status pengajuan cuti | ✅ Bearer |
| GET | `/api/simpeg/cuti/{id}/file` | Stream lampiran cuti via Signed URL | ❌ Publik (Signed URL) |

---

## 1. GET /api/simpeg/cuti

> Mengambil daftar permohonan cuti pegawai dengan filter, pencarian, dan pagination.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari alasan cuti, nama pegawai, NIP, NIDN, jenis cuti |
| `master_jenis_cuti_id` | integer | ❌ | — | Filter jenis cuti |
| `status_approval` | string | ❌ | — | Filter status: `menunggu`, `disetujui`, `ditolak` |
| `pegawai_id` | integer | ❌ | — | Filter ID pegawai (khusus admin/approver) |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan (`created_at`, `tanggal_mulai`, `tanggal_selesai`, `jumlah_hari`) |
| `sort_order` | string | ❌ | `desc` | Arah urutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Data cuti berhasil diambil",
    "data": [
        {
            "id": 1,
            "pegawai_id": 12,
            "master_jenis_cuti_id": 1,
            "tanggal_mulai": "2026-10-01",
            "tanggal_selesai": "2026-10-03",
            "jumlah_hari": 3,
            "alasan": "Keperluan keluarga di luar kota",
            "status_approval": "menunggu",
            "catatan_approval": null,
            "approved_by": null,
            "created_at": "2026-09-19T08:00:00.000000Z",
            "updated_at": "2026-09-19T08:00:00.000000Z",
            "pegawai": {
                "id": 12,
                "nama_lengkap": "Budi Santoso, S.Kom",
                "nip": "199001012015041002"
            },
            "master_jenis_cuti": {
                "id": 1,
                "kode": "CUTI_TAHUNAN",
                "nama": "Cuti Tahunan"
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
        "search": "",
        "status_approval": "",
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
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki izin untuk melihat data cuti pegawai."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data cuti tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Parameter query tidak valid.",
    "errors": {
        "per_page": [
            "Nilai per_page harus berupa angka."
        ]
    }
}
```

---

## 2. POST /api/simpeg/cuti

> Mengajukan permohonan cuti baru.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `multipart/form-data` | ✅ |

### Request Body (Form Data)

| Field | Type | Required | Validasi | Deskripsi |
|---|---|---|---|---|
| `pegawai_id` | integer | ✅ | `required|exists:pegawai,id` | ID Pegawai yang mengajukan |
| `master_jenis_cuti_id` | integer | ✅ | `required|exists:master_jenis_cuti,id` | ID Referensi Jenis Cuti |
| `tanggal_mulai` | date | ✅ | `required|date` | Tanggal mulai cuti (`YYYY-MM-DD`) |
| `tanggal_selesai` | date | ✅ | `required|date|after_or_equal:tanggal_mulai` | Tanggal selesai cuti (`YYYY-MM-DD`) |
| `alasan` | string | ✅ | `required|string|max:500` | Alasan permohonan cuti |
| `file` | file | ❌ | `nullable|file|mimes:pdf,jpg,jpeg,png|max:2048` | Dokumen bukti pendukung |

Contoh representasi data:
```json
{
    "pegawai_id": 12,
    "master_jenis_cuti_id": 1,
    "tanggal_mulai": "2026-10-01",
    "tanggal_selesai": "2026-10-03",
    "alasan": "Keperluan keluarga di luar kota",
    "file": "(binary lampiran PDF opsional)"
}
```

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Pengajuan cuti berhasil dibuat",
    "data": {
        "id": 1,
        "pegawai_id": 12,
        "master_jenis_cuti_id": 1,
        "tanggal_mulai": "2026-10-01",
        "tanggal_selesai": "2026-10-03",
        "jumlah_hari": 3,
        "alasan": "Keperluan keluarga di luar kota",
        "status_approval": "pending",
        "created_at": "2026-09-19T08:00:00.000000Z",
        "updated_at": "2026-09-19T08:00:00.000000Z"
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
    "message": "Anda tidak memiliki hak akses untuk mengajukan cuti."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "master_jenis_cuti_id": [
            "Jenis cuti yang dipilih tidak valid."
        ],
        "tanggal_selesai": [
            "Tanggal selesai harus berupa tanggal setelah atau sama dengan tanggal mulai."
        ]
    }
}
```

---

## 3. PATCH /api/simpeg/cuti/{id}/status

> Memperbarui status persetujuan atau penolakan pengajuan cuti pegawai.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

| Field | Type | Required | Validasi | Deskripsi |
|---|---|---|---|---|
| `status_approval` | string | ✅ | `required|in:pending,approved,rejected` | Status keputusan persetujuan |
| `catatan_approval` | string | ❌ | `nullable|string|max:255` | Catatan/alasan keputusan |

Contoh payload:
```json
{
    "status_approval": "approved",
    "catatan_approval": "Disetujui oleh atasan langsung"
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Status pengajuan cuti berhasil diperbarui",
    "data": {
        "id": 1,
        "status_approval": "approved",
        "catatan_approval": "Disetujui oleh atasan langsung"
    },
    "notifications": {
        "whatsapp": true,
        "email": true
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
    "message": "Anda tidak memiliki hak akses (permission) untuk menyetujui / menolak Cuti."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data pengajuan cuti tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "status_approval": [
            "Status approval harus salah satu dari: menunggu, disetujui, ditolak."
        ]
    }
}
```

---

## 4. GET /api/simpeg/cuti/{id}/file

> Stream lampiran cuti (inline) untuk dibuka di browser. **Tanpa Bearer token**, dilindungi **Signed URL** (berlaku 15 menit). Dipakai oleh atribut `file_pendukung_url` pada response cuti.

### Headers

| Key | Value | Required |
|---|---|---|
| `Accept` | `application/json` | ❌ |

### Query Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `expires` | integer | ✅ | Timestamp kedaluwarsa (diisi otomatis oleh Signed URL) |
| `signature` | string | ✅ | Tanda tangan HMAC (diisi otomatis oleh Signed URL) |

### Response Sukses (200 OK)
Binary stream (`Content-Disposition: inline`), dibaca dari disk kandidat (r2-private/public/local).

### Response Error

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Invalid signature."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Berkas pendukung tidak ditemukan."
}
```

---

## Catatan Khusus & Integritas Data
- **Soft Delete**: Data cuti menggunakan soft delete (`deleted_at`), riwayat pengajuan tidak pernah dihapus permanen untuk keperluan audit.
- **Kerahasiaan Data**: Dokumen surat keterangan sakit atau alasan personal hanya dapat diunduh oleh pegawai bersangkutan dan pejabat SDM berwenang.
- **Lampiran Privat (Signed URL)**: `file_pendukung_url` mengembalikan URL bertanda-tangan (15 menit) ke endpoint stream generik `/api/files/view?path=…`; berkas disimpan di private disk sehingga tidak dapat diakses lewat URL publik.
- **Password & Token**: Password, hashed password, dan token autentikasi tidak pernah dikembalikan dalam response API ini.

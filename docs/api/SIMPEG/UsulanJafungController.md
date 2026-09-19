# UsulanJafungController

> **Modul**: SIMPEG / **Base URL**: /api/simpeg/usulan-jafung / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-09-19

Modul ini mengelola proses usulan kenaikan Jabatan Fungsional (Jafung) dosen, validasi angka kredit, verifikasi dokumen SK hasil, catatan reviewer/tim penilai, serta soft delete usulan.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/simpeg/usulan-jafung` | Daftar usulan jafung dosen | ✅ Bearer |
| GET | `/api/simpeg/usulan-jafung/{id}` | Detail usulan jafung | ✅ Bearer |
| POST | `/api/simpeg/usulan-jafung` | Buat usulan jafung baru | ✅ Bearer |
| PUT | `/api/simpeg/usulan-jafung/{id}` | Perbarui usulan jafung | ✅ Bearer |
| DELETE | `/api/simpeg/usulan-jafung/{id}` | Hapus usulan jafung (soft delete) | ✅ Bearer |

---

## 1. GET /api/simpeg/usulan-jafung

> Mengambil daftar usulan kenaikan jafung dengan filtering dan pagination.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari nama pegawai, NIP, NIDN, catatan reviewer |
| `status_usulan` | string | ❌ | — | Filter status: `submitted`, `in_review`, `disetujui`, `ditolak` |
| `jafung_tujuan_id` | integer | ❌ | — | Filter ID jabatan fungsional tujuan |
| `pegawai_id` | integer | ❌ | — | Filter ID pegawai tertentu |
| `sort_by` | string | ❌ | `created_at` | Kolom urutan (`created_at`, `angka_kredit_usulan`, `status_usulan`, `id`) |
| `sort_order` | string | ❌ | `desc` | Arah urutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Data usulan jafung berhasil diambil",
    "data": [
        {
            "id": 1,
            "pegawai_id": 10,
            "jafung_asal_id": 2,
            "jafung_tujuan_id": 3,
            "angka_kredit_usulan": 300,
            "status_usulan": "submitted",
            "catatan_reviewer": null,
            "file_sk_hasil": null,
            "created_at": "2026-09-19T08:00:00.000000Z",
            "updated_at": "2026-09-19T08:00:00.000000Z",
            "pegawai": {
                "id": 10,
                "nama_lengkap": "Dr. Siti Aminah, M.Kom",
                "nip": "198501012010122001",
                "nidn": "0001018501"
            },
            "jafung_asal": {
                "id": 2,
                "nama": "Asisten Ahli"
            },
            "jafung_tujuan": {
                "id": 3,
                "nama": "Lektor"
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
    "message": "Anda tidak memiliki hak akses untuk melihat data usulan jafung."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data usulan jafung tidak ditemukan."
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

## 2. GET /api/simpeg/usulan-jafung/{id}

> Mengambil detail data pengajuan usulan jafung.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Detail usulan jafung berhasil diambil",
    "data": {
        "id": 1,
        "pegawai_id": 10,
        "jafung_asal_id": 2,
        "jafung_tujuan_id": 3,
        "angka_kredit_usulan": 300,
        "status_usulan": "submitted",
        "catatan_reviewer": null,
        "file_sk_hasil": null,
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
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki izin untuk melihat detail usulan jafung ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Usulan jafung tidak ditemukan."
}
```

---

## 3. POST /api/simpeg/usulan-jafung

> Mengajukan usulan kenaikan jabatan fungsional baru.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `multipart/form-data` | ✅ |

### Request Body

```json
{
    "pegawai_id": 10,
    "jafung_asal_id": 2,
    "jafung_tujuan_id": 3,
    "angka_kredit_usulan": 300,
    "status_usulan": "submitted",
    "catatan_reviewer": "Kelengkapan berkas jurnal nasional terakreditasi Sinta 2",
    "file_sk_hasil": "(binary PDF file)"
}
```

| Field | Type | Required | Validasi |
|---|---|---|---|
| `pegawai_id` | integer | ✅ | Exists di `pegawai,id` |
| `jafung_tujuan_id` | integer | ✅ | Exists di `master_jabatan_fungsional,id` |
| `jafung_asal_id` | integer | ❌ | Exists di `master_jabatan_fungsional,id` |
| `angka_kredit_usulan` | numeric | ❌ | Min: 0 |
| `status_usulan` | string | ❌ | In: `draft,submitted,in_review,disetujui,ditolak` |
| `catatan_reviewer` | string | ❌ | Teks catatan |
| `file_sk_hasil` | file | ❌ | PDF, max 5MB |

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Usulan kenaikan Jafung berhasil diajukan",
    "data": {
        "id": 2,
        "pegawai_id": 10,
        "jafung_asal_id": 2,
        "jafung_tujuan_id": 3,
        "angka_kredit_usulan": 300,
        "status_usulan": "submitted",
        "created_at": "2026-09-19T08:10:00.000000Z",
        "updated_at": "2026-09-19T08:10:00.000000Z"
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
    "message": "Anda tidak memiliki izin untuk mengajukan usulan jafung."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "pegawai_id": ["Pegawai wajib dipilih."],
        "jafung_tujuan_id": ["Jabatan fungsional tujuan wajib dipilih."]
    }
}
```

---

## 4. PUT /api/simpeg/usulan-jafung/{id}

> Memperbarui usulan kenaikan jafung atau mereview berkas oleh tim verifikator.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` atau `multipart/form-data` | ✅ |

### Request Body

| Field | Type | Required | Validasi | Deskripsi |
|---|---|---|---|---|
| `pegawai_id` | integer | ❌ | `sometimes|required|exists:simpeg_pegawai,id` | ID Pegawai |
| `jafung_asal_id` | integer | ❌ | `nullable|exists:simpeg_jabatan_fungsional_akademik,id` | ID Jafung Asal |
| `jafung_tujuan_id` | integer | ❌ | `sometimes|required|exists:simpeg_jabatan_fungsional_akademik,id` | ID Jafung Tujuan |
| `angka_kredit_usulan` | integer | ❌ | `sometimes|required|integer|min:0` | Angka Kredit Usulan |
| `status_usulan` | string | ❌ | `nullable|in:disetujui,ditolak` | Status Usulan Hasil Verifikasi |
| `catatan_reviewer` | string | ❌ | `nullable|string` | Catatan Tim Penilai |
| `file_sk_hasil` | file | ❌ | `nullable|file|mimes:pdf,jpg,jpeg,png|max:5120` | Berkas SK Hasil (maks 5MB) |

```json
{
    "angka_kredit_usulan": 300,
    "status_usulan": "disetujui",
    "catatan_reviewer": "Memenuhi syarat angka kredit kumulatif",
    "file_sk_hasil": null
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Usulan jafung berhasil diperbarui",
    "data": {
        "id": 2,
        "status_usulan": "disetujui",
        "catatan_reviewer": "Memenuhi syarat angka kredit kumulatif",
        "updated_at": "2026-09-19T08:15:00.000000Z"
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
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Usulan jafung tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "status_usulan": [
            "Status usulan harus salah satu dari: disetujui, ditolak."
        ],
        "file_sk_hasil": [
            "Ukuran file SK maksimal 5MB."
        ]
    }
}
```

---

## 5. DELETE /api/simpeg/usulan-jafung/{id}

> Menghapus usulan jafung secara soft-delete. Usulan yang sudah berstatus `disetujui` tidak dapat dihapus.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Usulan jafung berhasil dihapus (soft delete)",
    "data": {
        "id": 1,
        "deleted_at": "2026-09-19T08:15:00.000000Z"
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
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Usulan jafung tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Usulan jafung yang sudah disetujui tidak dapat dihapus.",
    "errors": {
        "status_usulan": [
            "Usulan jafung yang telah disetujui terkunci dan tidak dapat dihapus."
        ]
    }
}
```

---

## Catatan Khusus & Integritas Data
- **Soft Delete**: Endpoint ini menggunakan `SoftDeletes` (`deleted_at`), sehingga data fisik tidak hilang dari database.
- **Audit Logs**: Riwayat aksi Create, Update, dan Delete tercatat otomatis di tabel `audit_logs` modul `SIMPEG`.
- **Password & Token**: Password, hashed password, dan token autentikasi tidak pernah dikembalikan dalam response API ini.

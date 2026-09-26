# DokumenController

> **Modul**: SIMPEG / **Base URL**: /api/simpeg/dokumen / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-09-26

Modul ini mengelola arsip e-file dokumen kepegawaian (KTP, KK, Ijazah, SK, Serdos, Sertifikat, dll.), penyimpanan terenkripsi/private, secure view dengan dynamic watermark anti-bocor, serta penghapusan berkas.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/simpeg/dokumen` | Daftar arsip e-file dokumen pegawai | ✅ Bearer |
| POST | `/api/simpeg/dokumen` | Unggah dokumen e-file pegawai baru | ✅ Bearer |
| GET | `/api/simpeg/dokumen/{id}/secure-view` | Link pratinjau aman + watermark (Signed URL) | ✅ Bearer |
| GET | `/api/simpeg/dokumen/{id}/download` | Unduh berkas fisik (stream) | ✅ Bearer |
| GET | `/api/simpeg/dokumen/{id}/file` | Stream berkas pratinjau via Signed URL | ❌ Publik (Signed URL) |
| DELETE | `/api/simpeg/dokumen/{id}` | Hapus arsip dokumen pegawai | ✅ Bearer |

---

## 1. GET /api/simpeg/dokumen

> Mengambil daftar dokumen pegawai dengan filter jenis dokumen, pencarian, dan pagination.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari nama dokumen, nama pegawai, NIP, NIDN |
| `jenis_dokumen` | string | ❌ | — | Filter jenis: `ktp`, `kk`, `ijazah`, `sk`, `serdos`, `sertifikat`, `lainnya` |
| `pegawai_id` | integer | ❌ | — | Filter ID pegawai tertentu (khusus admin) |
| `sort_by` | string | ❌ | `created_at` | Kolom urutan (`created_at`, `nama_dokumen`, `jenis_dokumen`, `file_size`) |
| `sort_order` | string | ❌ | `desc` | Arah urutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Data dokumen berhasil diambil",
    "data": [
        {
            "id": 1,
            "pegawai_id": 10,
            "nama_dokumen": "Ijazah S3 Ilmu Komputer",
            "jenis_dokumen": "ijazah",
            "file_path": "simpeg/dokumen_pegawai/ijazah_s3.pdf",
            "file_size": "2.4 MB",
            "created_at": "2026-09-19T08:00:00.000000Z",
            "updated_at": "2026-09-19T08:00:00.000000Z",
            "pegawai": {
                "id": 10,
                "nama_lengkap": "Dr. Siti Aminah, M.Kom",
                "nip": "198501012010122001"
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
    "message": "Anda tidak memiliki hak akses untuk melihat daftar dokumen kepegawaian."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Dokumen pegawai tidak ditemukan."
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

## 2. POST /api/simpeg/dokumen

> Mengunggah berkas e-file dokumen kepegawaian baru.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `multipart/form-data` | ✅ |

### Request Body (Form Data)

| Field | Type | Required | Validasi | Deskripsi |
|---|---|---|---|---|
| `pegawai_id` | integer | ✅ | `required|exists:pegawai,id` | ID Pegawai pemilik dokumen |
| `nama_dokumen` | string | ✅ | `required|string|max:255` | Nama/judul dokumen |
| `jenis_dokumen` | string | ✅ | `required|in:ktp,kk,ijazah,sk,serdos,sertifikat,lainnya` | Kategori dokumen |
| `file` | file | ✅ | `required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240` | File berkas yang diunggah (maks 10MB) |

Contoh representasi form:
```json
{
    "pegawai_id": 10,
    "nama_dokumen": "Sertifikat Pendidik Dosen",
    "jenis_dokumen": "serdos",
    "file": "(binary file PDF/Gambar)"
}
```

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Dokumen pegawai berhasil diunggah dan tersimpan aman di server",
    "data": {
        "id": 2,
        "pegawai_id": 10,
        "nama_dokumen": "Sertifikat Pendidik Dosen",
        "jenis_dokumen": "serdos",
        "file_path": "simpeg/dokumen_pegawai/serdos.pdf",
        "file_size": "1.2 MB",
        "created_at": "2026-09-19T08:15:00.000000Z",
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
    "message": "Anda tidak memiliki hak akses untuk mengunggah dokumen pegawai."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "file": [
            "Berkas harus berupa file dengan tipe: pdf, jpg, jpeg, png, doc, docx.",
            "Ukuran berkas tidak boleh melebihi 10MB."
        ],
        "nama_dokumen": [
            "Nama dokumen wajib diisi."
        ]
    }
}
```

---

## 3. GET /api/simpeg/dokumen/{id}/secure-view

> Menghasilkan link pratinjau aman (Signed URL, berlaku 15 menit) dan teks watermark. Link `file_url` boleh dibuka di tab baru tanpa Bearer token karena dilindungi middleware `signed`. Streaming dibaca dari disk kandidat (local/public/R2) sehingga tetap bekerja untuk berkas lama.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "data": {
        "dokumen_id": 2,
        "nama_dokumen": "Sertifikat Pendidik Dosen",
        "jenis_dokumen": "serdos",
        "watermark_overlay": "RAHASIA - Dr. Siti Aminah - 198501012010122001 - 2026-09-19",
        "file_url": "https://ragibe.poltekindonusa.ac.id/api/files/view?path=simpeg%2Fdokumen_pegawai%2F2026%2F09%2Fuuid.pdf&expires=1760000000&signature=abc123",
        "file_exists": true,
        "security_status": "Confidential - Encrypted & Watermarked"
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
    "message": "Akses Ditolak: Dokumen ini bersifat rahasia dan hanya dapat dibuka oleh Admin SIMPEG, Superadmin, atau pegawai pemilik dokumen tersebut."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Dokumen pegawai tidak ditemukan."
}
```

---

## 4. GET /api/simpeg/dokumen/{id}/download

> Mengunduh berkas fisik dokumen (stream) dengan nama unduhan asli. Stream dibaca dari disk kandidat (local/public/R2).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json, application/octet-stream` | ✅ |

### Response Sukses (200 OK)
Binary stream (`Content-Disposition: attachment`).

### Response Error

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Akses Ditolak: Dokumen ini bersifat rahasia dan hanya dapat dibuka oleh Admin SIMPEG, Superadmin, atau pegawai pemilik dokumen tersebut."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "File fisik tidak ditemukan pada lokasi storage server."
}
```

---

## 5. GET /api/simpeg/dokumen/{id}/file

> Stream berkas pratinjau (inline) untuk dibuka di browser. **Tanpa Bearer token**, dilindungi **Signed URL** (berlaku 15 menit). URL dihasilkan oleh endpoint `secure-view`.

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
Binary stream (`Content-Disposition: inline`).

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
    "message": "Berkas fisik dokumen tidak ditemukan."
}
```

---

## 6. DELETE /api/simpeg/dokumen/{id}

> Menghapus berkas dokumen pegawai.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Dokumen pegawai berhasil dihapus"
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
    "message": "Anda tidak memiliki hak akses untuk menghapus dokumen pegawai."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Dokumen pegawai tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Dokumen tidak dapat dihapus karena terkait dengan berkas kepegawaian aktif.",
    "errors": {
        "dokumen_id": [
            "Berkas dokumen sedang digunakan oleh proses verifikasi kepegawaian aktif."
        ]
    }
}
```

---

## Catatan Khusus & Integritas Data
- **Penyimpanan Berkas**: Seluruh berkas diunggah ke private disk yang tidak dapat diakses langsung tanpa autentikasi token.
- **Pratinjau Aman (Signed URL)**: `secure-view` mengembalikan `file_url` bertanda-tangan (berlaku 15 menit) yang men-stream berkas inline dari disk kandidat (local/public/R2), sehingga tetap bekerja walau berkas lama berada di disk berbeda.
- **Download**: Endpoint `/download` men-stream berkas sebagai attachment dengan nama unduhan asli.
- **Dynamic Watermark**: Tampilan view dokumen otomatis disisipi nama pengunduh, NIP, dan stempel waktu untuk mencegah kebocoran dokumen rahasia.
- **Soft Delete**: Berkas dokumen kepegawaian yang dihapus dicatat riwayatnya dalam audit log.
- **Password & Token**: Password, hashed password, dan token autentikasi tidak pernah dikembalikan dalam response API ini.

# Dokumentasi API: Arsip Nomor Surat Controller

Dokumentasi endpoint untuk pengelolaan **Nomor Surat Resmi** (`/api/arsip/nomor-surat`) dalam modul ARSIP.

---

## Headers Standar

- `Authorization: Bearer {token}`
- `Accept: application/json`
- `Content-Type: application/json`

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/arsip/nomor-surat` | Daftar nomor surat resmi | ✅ `arsip.nomor_surat.read` |
| POST | `/api/arsip/nomor-surat` | Terbitkan nomor surat (satuan atau bulk) | ✅ `arsip.nomor_surat.create` |
| GET | `/api/arsip/nomor-surat/{id}` | Detail nomor surat | ✅ `arsip.nomor_surat.read` |
| PUT | `/api/arsip/nomor-surat/{id}` | Perbarui data perihal/tujuan nomor surat | ✅ `arsip.nomor_surat.update` |
| POST | `/api/arsip/nomor-surat/{id}/upload-surat` | Unggah scan berkas surat resmi (PDF/JPG/PNG) | ✅ `arsip.nomor_surat.update` |
| GET | `/api/arsip/nomor-surat/{id}/download-surat` | Unduh berkas scan surat resmi | ✅ `arsip.nomor_surat.read` |
| GET | `/api/arsip/nomor-surat/check` | Periksa keabsahan dan dokumen nomor SK/surat | ✅ `arsip.nomor_surat.read` |
| POST | `/api/arsip/nomor-surat/{id}/upload-dokumen` | Unggah berkas dokumen fisik nomor surat | ✅ `arsip.nomor_surat.update` |
| POST | `/api/arsip/nomor-surat/{id}/batalkan` | Batalkan nomor surat | ✅ `arsip.nomor_surat.update` |

---

## 1. GET /api/arsip/nomor-surat

Mengambil daftar nomor surat resmi dengan filter tahun, unit, klasifikasi, status, dan pagination.

### Query Parameters

- `search` (string, optional) - Pencarian nomor surat, perihal, tujuan, atau catatan.
- `tahun` (integer, optional) - Filter tahun surat.
- `kode_unit` (string, optional) - Filter kode unit (misal: `DIII`, `S1`, `REK`).
- `kode_klasifikasi` (string, optional) - Filter kode klasifikasi (misal: `DIII`, `AKD`, `SARPRAS`).
- `status` (string, optional) - Filter status (`terpakai`, `direservasi`, `dibatalkan`).
- `module_origin` (string, optional) - Filter asal modul (misal: `arsip`, `sinapra`, `simpeg`).
- `is_backdate` (boolean, optional) - Filter status surat tanggal mundur / backdate (`1`/`0`/`true`/`false`).
- `sort_by` (string, default: `created_at`) - Kolom pengurutan (`id`, `nomor_urut`, `tanggal_surat`, `created_at`).
- `sort_order` (string, default: `desc`) - Arah pengurutan (`asc`, `desc`).
- `per_page` (integer, default: 15, max: 100) - Jumlah data per halaman.
- `page` (integer, default: 1) - Halaman data.

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Daftar nomor surat berhasil diambil",
  "data": [
    {
      "id": 1,
      "nomor_surat": "247/DIII/INDO/II/2025",
      "nomor_urut": 247,
      "kode_unit": "INDO",
      "kode_klasifikasi": "DIII",
      "bulan_romawi": "II",
      "tahun": 2025,
      "tanggal_surat": "2025-02-14",
      "is_backdate": false,
      "alasan_backdate": null,
      "perihal": "Peminjaman Laboratorium Praktikum",
      "tujuan": "Kepala Laboratorium Komputer",
      "status": "terpakai",
      "module_origin": "sinapra",
      "request_id": 5,
      "kop_surat_id": 2,
      "created_by": 1,
      "created_at": "2025-02-14T08:30:00.000000Z",
      "pembuat": {
        "id": 1,
        "name": "Super Administrator",
        "email": "admin@example.com"
      },
      "kop_surat": {
        "id": 2,
        "nama": "Kop Surat Resmi Universitas (2021-Sekarang)",
        "versi": "baru",
        "tahun_mulai": 2021
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
  }
}
```

---

## 2. POST /api/arsip/nomor-surat

Menerbitkan nomor surat resmi secara langsung (satuan atau bulk berurutan). Nomor surat otomatis ter-reset mulai 1 pada setiap pergantian tahun (1 Januari) tanpa menghapus data tahun lalu. Jika menerbitkan surat bertanggal mundur (backdate sebelum hari ini), wajib menyertakan alasan/justifikasi.

### Payload Satuan (Single)

```json
{
  "mode": "satuan",
  "tanggal_surat": "2025-02-14",
  "kode_unit": "INDO",
  "kode_klasifikasi": "DIII",
  "perihal": "Surat Keterangan Aktif Kuliah",
  "tujuan": "Instansi Terkait",
  "status": "terpakai",
  "module_origin": "arsip",
  "alasan_backdate": "Penerbitan surat susulan keperluan akreditasi",
  "catatan": "Keterangan opsional"
}
```

### Payload Sekaligus Banyak (Bulk)

```json
{
  "mode": "bulk",
  "jumlah_nomor": 5,
  "tanggal_surat": "2025-02-14",
  "kode_unit": "INDO",
  "kode_klasifikasi": "DIII",
  "perihal": "Sertifikat Pelatihan Mahasiswa",
  "tujuan": "Peserta Pelatihan",
  "status": "terpakai",
  "module_origin": "arsip",
  "alasan_backdate": "Penerbitan batch sertifikat pelatihan tahun lalu"
}
```

### Response Sukses (201 Created)

```json
{
  "status": "success",
  "message": "Nomor surat resmi berhasil diterbitkan.",
  "data": {
    "id": 1,
    "nomor_surat": "247/DIII/INDO/II/2025",
    "nomor_urut": 247,
    "kode_unit": "INDO",
    "kode_klasifikasi": "DIII",
    "bulan_romawi": "II",
    "tahun": 2025,
    "tanggal_surat": "2025-02-14",
    "is_backdate": true,
    "alasan_backdate": "Penerbitan surat susulan keperluan akreditasi",
    "perihal": "Surat Keterangan Aktif Kuliah"
  }
}
```

---

## 3. GET /api/arsip/nomor-surat/{id}

Mengambil detail lengkap nomor surat, kop surat yang terikat, serta pembuatnya.

---

## 4. POST /api/arsip/nomor-surat/{id}/batalkan

Membatalkan nomor surat resmi yang terlanjur terbit.

### Payload

```json
{
  "alasan": "Salah perihal surat dan dibatalkan atas permintaan pemohon"
}
```

---

## 5. POST /api/arsip/nomor-surat/{id}/upload-surat

- **Method**: `POST`
- **URL**: `/api/arsip/nomor-surat/{id}/upload-surat`
- **Permission**: `arsip.nomor_surat.update` atau `arsip.nomor_surat.create`
- **Deskripsi**: Admin ARSIP mengunggah berkas scan surat bertanda-tangan fisik yang telah terbit. Berkas disimpan secara privat di cloud storage aman.

### Headers

| Header | Value | Deskripsi |
|---|---|---|
| `Authorization` | `Bearer <token>` | Token otentikasi JWT/Passport |
| `Content-Type` | `multipart/form-data` | Form data file upload |
| `Accept` | `application/json` | Format respons yang diharapkan |

### Form Data

| Parameter | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `file_surat` | File (Binary) | Ya | Berkas scan dokumen (PDF, JPG, JPEG, PNG). Maksimal 10 MB. |

### Respon Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Berkas scan dokumen surat berhasil diunggah.",
  "data": {
    "id": 12,
    "nomor_surat": "001/SDM/OT.01/X/2026",
    "nomor_urut": 1,
    "status": "terpakai",
    "file_surat_path": "arsip/dokumen-surat/2026/10/d41d8cd9-8f00-4b24-96cf-c9c1e18cd8b8.pdf",
    "file_surat_url": "http://127.0.0.1:8000/api/files/view?path=arsip%2Fdokumen-surat%2F2026%2F10%2Fd41d8cd9.pdf&signature=abcdef123456",
    "has_file": true,
    "file_surat_uploaded_at": "2026-10-12T14:00:00+07:00",
    "file_uploader": {
      "id": 1,
      "name": "Admin Arsip & Tata Usaha",
      "email": "arsip@politeknik.ac.id"
    }
  }
}
```

### Respon Error (422 Unprocessable Entity)

```json
{
  "status": "error",
  "message": "Format berkas harus berupa PDF atau gambar (JPG, PNG).",
  "errors": {
    "file_surat": [
      "Format berkas harus berupa PDF atau gambar (JPG, PNG)."
    ]
  }
}
```

### Respon Error (403 Forbidden)

```json
{
  "status": "error",
  "message": "Anda tidak memiliki hak akses untuk mengunggah berkas scan nomor surat."
}
```

---

## 6. GET /api/arsip/nomor-surat/{id}/download-surat

- **Method**: `GET`
- **URL**: `/api/arsip/nomor-surat/{id}/download-surat`
- **Permission**: `arsip.nomor_surat.read`
- **Deskripsi**: Menghasilkan tautan bertanda-tangan (Signed URL) sementara yang berlaku selama 15 menit untuk mengunduh dokumen privat fisik scan surat resmi.

### Headers

| Header | Value | Deskripsi |
|---|---|---|
| `Authorization` | `Bearer <token>` | Token otentikasi JWT/Passport |
| `Accept` | `application/json` | Format respons yang diharapkan |

### Respon Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Tautan unduh dokumen surat berhasil dibuat.",
  "data": {
    "nomor_surat": "001/SDM/OT.01/X/2026",
    "download_url": "http://127.0.0.1:8000/api/files/view?path=arsip%2Fdokumen-surat%2F2026%2F10%2Fd41d8cd9.pdf&signature=abcdef123456",
    "expires_in_minutes": 15
  }
}
```

### Respon Error (404 Not Found)

```json
{
  "status": "error",
  "message": "Berkas scan dokumen surat belum diunggah."
}
```

### Respon Error (403 Forbidden)

```json
{
  "status": "error",
  "message": "Anda tidak memiliki hak akses untuk mengunduh dokumen surat."
}
```

---

## 7. GET /api/arsip/nomor-surat/check

Memeriksa keabsahan nomor surat / SK di modul Arsip serta mengecek apakah dokumen fisiknya sudah diunggah atau masih kosong. Digunakan untuk validasi lintas modul (misalnya penetapan Master Biaya SPMB).

### Query Parameters

- `nomor_surat` atau `nomor_sk` (string, required) - String nomor surat/SK yang akan diperiksa (contoh: `15/SK/YAY/X/2026`).

### Response Sukses (200 OK)

Nomor SK terdaftar, status aktif (tidak dibatalkan), dan dokumen fisik SK terverifikasi (tidak kosong).

```json
{
  "status": "success",
  "message": "Nomor SK '15/SK/YAY/X/2026' valid dan dokumen fisik SK terverifikasi di modul Arsip.",
  "data": {
    "id": 5,
    "nomor_surat": "15/SK/YAY/X/2026",
    "is_found": true,
    "is_valid": true,
    "has_document": true,
    "status": "terpakai",
    "perihal": "SK Penetapan Biaya Kuliah Mahasiswa Baru",
    "tujuan": "Yayasan / Pimpinan",
    "kode_unit": "YAY",
    "kode_klasifikasi": "SK",
    "tanggal_surat": "2026-10-01",
    "dokumen_url": "http://localhost:8000/api/files/view?path=arsip%2Fdokumen%2Fsk.pdf..."
  }
}
```

### Response Hasil Pengecekan

**1. Nomor SK Valid & Dokumen Fisik Terlampir (200 OK)**
```json
{
  "status": "success",
  "message": "Nomor SK '15/SK/YAY/X/2026' valid dan dokumen fisik SK terverifikasi di modul Arsip.",
  "data": {
    "id": 5,
    "nomor_surat": "15/SK/YAY/X/2026",
    "is_found": true,
    "is_valid": true,
    "has_document": true,
    "status": "terpakai",
    "perihal": "SK Penetapan Biaya Kuliah Mahasiswa Baru",
    "tujuan": "Yayasan / Pimpinan",
    "kode_unit": "YAY",
    "kode_klasifikasi": "SK",
    "tanggal_surat": "2026-10-01",
    "dokumen_url": "http://localhost:8000/api/files/view?path=arsip%2Fdokumen%2Fsk.pdf..."
  }
}
```

**2. Nomor SK Tidak Ditemukan (200 OK, is_found: false)**
```json
{
  "status": "success",
  "message": "Nomor SK 'SK-TIDAK-ADA' tidak ditemukan di sistem Arsip.",
  "data": {
    "id": null,
    "nomor_surat": "SK-TIDAK-ADA",
    "is_found": false,
    "is_valid": false,
    "has_document": false,
    "status": null,
    "perihal": null,
    "tujuan": null,
    "kode_unit": null,
    "kode_klasifikasi": null,
    "tanggal_surat": null,
    "dokumen_url": null
  }
}
```

**3. Dokumen Fisik SK Masih Kosong (200 OK, has_document: false)**
```json
{
  "status": "success",
  "message": "Nomor SK '10/SK/YAY/X/2026' terdaftar di sistem Arsip, tetapi dokumen/berkas fisik SK belum diunggah (dokumen masih kosong).",
  "data": {
    "id": 3,
    "nomor_surat": "10/SK/YAY/X/2026",
    "is_found": true,
    "is_valid": false,
    "has_document": false,
    "status": "terpakai",
    "perihal": "SK Penetapan Biaya Kuliah Mahasiswa Baru",
    "tujuan": "Yayasan / Pimpinan",
    "kode_unit": "YAY",
    "kode_klasifikasi": "SK",
    "tanggal_surat": "2026-10-01",
    "dokumen_url": null
  }
}
```

**4. Parameter Kosong (422 Unprocessable Entity)**
```json
{
  "status": "error",
  "message": "Parameter nomor surat / nomor SK wajib diisi.",
  "errors": {
    "nomor_surat": ["Nomor surat / nomor SK wajib diisi."]
  }
}
```

**5. Tidak Terautentikasi (401 Unauthorized)**
```json
{
  "status": "error",
  "message": "Unauthenticated."
}
```

**6. Tidak Memiliki Hak Akses (403 Forbidden)**
```json
{
  "status": "error",
  "message": "Anda tidak memiliki hak akses untuk memeriksa nomor surat."
}
```

---

## 8. POST /api/arsip/nomor-surat/{id}/upload-dokumen

Mengunggah dokumen fisik surat/SK resmi langsung ke entitas nomor surat.

### Headers

- `Authorization: Bearer {token}`
- `Content-Type: multipart/form-data`
- `Accept: application/json`

### Body (Form-Data)

- `dokumen` (file, required, max 10MB, mime: pdf/jpg/jpeg/png) - Berkas dokumen fisik surat resmi.

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Dokumen nomor surat berhasil diunggah.",
  "data": {
    "id": 5,
    "nomor_surat": "15/SK/YAY/X/2026",
    "file_path": "arsip/dokumen/uuid.pdf",
    "dokumen_url": "http://localhost:8000/api/files/view?path=arsip%2Fdokumen%2Fuuid.pdf..."
  }
}
```

### Response Error

**1. Validasi Gagal (422 Unprocessable Entity)**
```json
{
  "status": "error",
  "message": "The dokumen field is required.",
  "errors": {
    "dokumen": [
      "The dokumen field is required.",
      "The dokumen must be a file of type: pdf, jpg, jpeg, png.",
      "The dokumen must not be greater than 10240 kilobytes."
    ]
  }
}
```

**2. Data Tidak Ditemukan (404 Not Found)**
```json
{
  "status": "error",
  "message": "Data nomor surat tidak ditemukan."
}
```

**3. Tidak Terautentikasi (401 Unauthorized)**
```json
{
  "status": "error",
  "message": "Unauthenticated."
}
```

**4. Tidak Memiliki Hak Akses (403 Forbidden)**
```json
{
  "status": "error",
  "message": "Anda tidak memiliki hak akses untuk mengunggah dokumen nomor surat."
}
```

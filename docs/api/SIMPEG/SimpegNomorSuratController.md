# SimpegNomorSuratController

> **Modul**: SIMPEG  
> **Base URL**: `/api/simpeg/nomor-surat`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-10-13  
> **Diperbarui**: 2026-10-13  

Controller ini mengelola layanan penomoran surat resmi kampus bagi pegawai (dosen & tendik) di modul SIMPEG. Pegawai dapat melihat daftar nomor surat resmi yang telah diterbitkan oleh Admin ARSIP atas nama mereka, mengunggah scan berkas surat fisik yang telah ditandatangani, mengunduh berkas scan surat, serta mengajukan permohonan nomor surat resmi baru (satuan atau bulk) langsung dari modul SIMPEG.

---

## Headers Standar

- `Authorization: Bearer {token}`
- `Accept: application/json`

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth / Permission |
|---|---|---|---|
| GET | `/api/simpeg/nomor-surat/my-letters` | Daftar nomor surat resmi milik pegawai yang login | ✅ `simpeg.nomor_surat.read` |
| POST | `/api/simpeg/nomor-surat/request` | Pengajuan permohonan nomor surat baru (satuan/bulk) | ✅ `simpeg.nomor_surat.create` |
| POST | `/api/simpeg/nomor-surat/{id}/upload-scan` | Unggah scan berkas surat resmi oleh pegawai | ✅ `simpeg.nomor_surat.upload` / Pemilik |
| GET | `/api/simpeg/nomor-surat/{id}/download-scan` | Unduh berkas scan surat resmi | ✅ `simpeg.nomor_surat.read` / Pemilik |

---

## 1. GET /api/simpeg/nomor-surat/my-letters

Mengambil daftar nomor surat resmi kampus yang mengikat profil pegawai login (`pegawai_id`).

### Query Parameters

- `search` (string, optional) - Pencarian nomor surat, perihal, atau tujuan.
- `status` (string, optional) - Filter status nomor surat (`terpakai`, `direservasi`, `dibatalkan`).
- `has_file` (string/boolean, optional) - Filter kelengkapan berkas fisik (`1`/`true` = ada berkas, `0`/`false` = belum ada berkas).
- `sort_by` (string, default: `created_at`) - Pengurutan data (`id`, `nomor_urut`, `tanggal_surat`, `created_at`).
- `sort_order` (string, default: `desc`) - Arah pengurutan (`asc`, `desc`).
- `per_page` (integer, default: 15) - Jumlah data per halaman (maksimal 100).
- `page` (integer, default: 1) - Halaman data.

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Daftar nomor surat pegawai berhasil diambil",
  "data": [
    {
      "id": 1,
      "nomor_surat": "001/BAAK/DI/X/2026",
      "nomor_urut": 1,
      "kode_unit": "BAAK",
      "kode_klasifikasi": "DI",
      "tanggal_surat": "2026-10-13",
      "perihal": "Undangan Rapat Koordinasi",
      "tujuan": "Dosen Fakultas Teknik",
      "status": "terpakai",
      "pegawai_id": 12,
      "has_file": true,
      "file_surat_path": "arsip/dokumen-surat/uuid.pdf"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 1,
    "last_page": 1
  }
}
```

### Response Error (403 Forbidden)

```json
{
  "status": "error",
  "message": "Anda tidak memiliki hak akses untuk melihat daftar nomor surat."
}
```

---

## 2. POST /api/simpeg/nomor-surat/request

Mengajukan permohonan nomor surat resmi baru dari modul SIMPEG ke Admin ARSIP.

### Headers

- `Authorization: Bearer <token>`
- `Content-Type: multipart/form-data`
- `Accept: application/json`

### Body (Multipart Form)

- `mode` (string, optional: `satuan` atau `bulk`, default: `satuan`)
- `tanggal_surat` (string date Y-m-d, required)
- `kode_unit` (string, optional - jika kosong diisi kode unit kerja pemohon atau 'PENDING' untuk diverifikasi Admin ARSIP)
- `kode_klasifikasi` (string, optional - jika kosong bernilai 'PENDING' untuk diverifikasi & ditentukan Admin ARSIP)
- `perihal` (string, required)
- `tujuan` (string, optional)
- `jumlah_nomor` (integer, required jika mode=bulk, default: 1)
- `catatan_pemohon` (string, optional)
- `lampiran` (file: pdf/doc/docx/jpg/png max 10MB, optional)

### Respon Sukses (201 Created)

```json
{
  "status": "success",
  "message": "Permohonan nomor surat berhasil diajukan ke Tim Tata Persuratan / Arsip.",
  "data": {
    "id": 5,
    "user_id": 10,
    "module_origin": "simpeg",
    "perihal": "Permohonan Surat Keterangan Aktif Mengajar Semester Ganjil",
    "tujuan": "LLDIKTI Wilayah VI",
    "tanggal_surat": "2026-10-12",
    "kode_unit": "SDM",
    "kode_klasifikasi": "KP.01",
    "jumlah_nomor": 1,
    "status": "menunggu_verifikasi",
    "created_at": "2026-10-12T14:10:00+07:00"
  }
}
```

### Respon Error (422 Unprocessable Entity)

```json
{
  "status": "error",
  "message": "Perihal surat wajib diisi.",
  "errors": {
    "perihal": [
      "Perihal surat wajib diisi."
    ]
  }
}
```

### Respon Error (403 Forbidden)

```json
{
  "status": "error",
  "message": "Anda tidak memiliki hak akses untuk mengajukan permohonan nomor surat."
}
```

---

## 3. POST /api/simpeg/nomor-surat/{id}/upload-scan

Mengunggah scan berkas surat fisik yang telah ditandatangani dan dicap resmi.

### Headers

- `Authorization: Bearer <token>`
- `Content-Type: multipart/form-data`
- `Accept: application/json`

### Body (Multipart Form)

- `file_surat` (file: pdf, jpg, jpeg, png max 10MB, required)

### Respon Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Berkas scan dokumen surat resmi berhasil diunggah.",
  "data": {
    "id": 8,
    "nomor_surat": "008/SDM/KP.01/X/2026",
    "perihal": "Surat Tugas Penelitian Dosen",
    "file_surat_path": "arsip/dokumen-surat/2026/10/fe1a2b3c-4d5e-6f7a-8b9c-0d1e2f3a4b5c.pdf",
    "file_surat_url": "http://127.0.0.1:8000/api/files/view?path=arsip%2Fdokumen-surat%2F2026%2F10%2Ffe1a2b3c.pdf&signature=abcdef123456",
    "has_file": true,
    "file_surat_uploaded_at": "2026-10-12T14:20:00+07:00",
    "file_uploader": {
      "id": 10,
      "name": "Budi Santoso, M.Kom.",
      "email": "budi.santoso@politeknik.ac.id"
    }
  }
}
```

### Respon Error (403 Forbidden)

```json
{
  "status": "error",
  "message": "Anda tidak memiliki hak akses untuk mengunggah dokumen pada nomor surat ini."
}
```

### Respon Error (422 Unprocessable Entity)

```json
{
  "status": "error",
  "message": "Berkas scan dokumen surat wajib diunggah.",
  "errors": {
    "file_surat": [
      "Berkas scan dokumen surat wajib diunggah."
    ]
  }
}
```

---

## 4. GET /api/simpeg/nomor-surat/{id}/download-scan

Mengunduh berkas scan surat resmi yang telah diarsipkan via tautan Signed URL sementara.

### Headers

- `Authorization: Bearer <token>`
- `Accept: application/json`

### Respon Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Tautan unduh dokumen surat berhasil dibuat.",
  "data": {
    "nomor_surat": "008/SDM/KP.01/X/2026",
    "download_url": "http://127.0.0.1:8000/api/files/view?path=arsip%2Fdokumen-surat%2F2026%2F10%2Ffe1a2b3c.pdf&signature=abcdef123456",
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
  "message": "Akses Ditolak: Dokumen ini hanya dapat diakses oleh pihak bersangkutan atau staf SDM/Arsip."
}
```

# KontrakMonevController

> **Modul**: SIPPM (Sistem Informasi Penelitian dan Pengabdian Masyarakat)  
> **Base URL**: `/api/sippm`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-11-11  
> **Diperbarui**: 2026-11-11  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/sippm/kontrak` | Mengambil daftar kontrak hibah penelitian & pengabdian | ✅ Bearer |
| POST | `/api/sippm/proposal/{id}/kontrak` | Menerbitkan kontrak baru untuk proposal yang lolos | ✅ Admin LPPM |
| POST | `/api/sippm/kontrak/{id}/pencairan` | Mengajukan pencairan termin dana hibah | ✅ Bearer |
| POST | `/api/sippm/kontrak/{id}/upload-spk-ttd` | Mengunggah file scan SPK bertanda tangan basah | ✅ Bearer |
| POST | `/api/sippm/kontrak/{id}/approve-spk` | Menyetujui SPK oleh LPPM | ✅ Admin LPPM |
| POST | `/api/sippm/pencairan/{id}/upload-resi-sikeu` | Mengunggah bukti resi transfer SIKEU | ✅ Admin LPPM |
| POST | `/api/sippm/kontrak/{id}/laporan` | Mengunggah laporan kemajuan / akhir monev | ✅ Bearer |

---

## 1. GET /api/sippm/kontrak

> Mengambil daftar seluruh kontrak pelaksanaan hibah penelitian dan pengabdian.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari nomor kontrak atau nama kegiatan |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan |
| `sort_order` | string | ❌ | `desc` | Arah urutan (`asc` / `desc`) |
| `per_page` | integer | ❌ | `15` | Pagination limit |
| `page` | integer | ❌ | `1` | Nomor halaman |

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Daftar kontrak kegiatan berhasil dimuat.",
  "data": [
    {
      "id": 1,
      "proposal_id": 1,
      "nomor_kontrak": "SPK/LPPM/2026/001",
      "tgl_kontrak": "2026-04-01",
      "dana_disetujui": 18000000,
      "status_spk": "disetujui",
      "created_at": "2026-11-11T09:00:00.000000Z",
      "updated_at": "2026-11-11T09:00:00.000000Z"
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

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "Permintaan tidak valid atau parameter pagination salah."
}
```

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
  "message": "Data kontrak kegiatan tidak ditemukan."
}
```

---

## 2. POST /api/sippm/proposal/{id}/kontrak

> Menerbitkan kontrak baru untuk proposal yang telah lolos seleksi.

### Request Body

```json
{
  "nomor_kontrak": "SPK/LPPM/2026/002",
  "dana_disetujui": 20000000,
  "tgl_mulai": "2026-04-01",
  "tgl_selesai": "2026-10-31",
  "file_kontrak": "spk_kontrak_002.pdf"
}
```

### Response Sukses (201 Created)

```json
{
  "status": "success",
  "message": "Kontrak SPK berhasil diterbitkan.",
  "data": {
    "id": 2,
    "proposal_id": 2,
    "nomor_kontrak": "SPK/LPPM/2026/002",
    "dana_disetujui": 20000000,
    "tgl_mulai": "2026-04-01",
    "tgl_selesai": "2026-10-31",
    "status_spk": "draft",
    "created_at": "2026-11-11T09:30:00.000000Z",
    "updated_at": "2026-11-11T09:30:00.000000Z"
  }
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "Permintaan penerbitan kontrak tidak valid."
}
```

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
  "message": "Proposal kegiatan tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
  "status": "error",
  "message": "Validasi gagal.",
  "errors": {
    "tgl_selesai": ["Tanggal selesai harus setelah tanggal mulai."]
  }
}
```

---

## 3. POST /api/sippm/kontrak/{id}/pencairan

> Mengajukan permohonan pencairan termin dana hibah ke bagian keuangan (SIKEU).

### Request Body

```json
{
  "termin_ke": 1,
  "persen_pencairan": 70
}
```

### Response Sukses (201 Created)

```json
{
  "status": "success",
  "message": "Pengajuan pencairan dana termin berhasil dikirim dan dicatat di SIKEU.",
  "data": {
    "id": 1,
    "kontrak_kegiatan_id": 1,
    "termin_ke": 1,
    "persen_pencairan": 70,
    "nominal": 12600000,
    "status": "pengajuan",
    "created_at": "2026-11-11T10:15:00.000000Z",
    "updated_at": "2026-11-11T10:15:00.000000Z"
  }
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "Permintaan pencairan dana termin tidak valid."
}
```

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
  "message": "Kontrak kegiatan tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
  "status": "error",
  "message": "Validasi gagal.",
  "errors": {
    "persen_pencairan": ["Persen pencairan harus bernilai antara 1 hingga 100."]
  }
}
```

---

## 4. POST /api/sippm/kontrak/{id}/upload-spk-ttd

> Mengunggah dokumen SPK yang telah ditandatangani basah oleh pengusul.

### Request Body

```json
{
  "file_spk_ttd": "spk_signed_kontrak_001.pdf"
}
```

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Dokumen SPK bertanda tangan basah berhasil di-upload oleh Ketua Pengusul.",
  "data": {
    "id": 1,
    "file_spk_ttd": "spk_signed_kontrak_001.pdf",
    "status_spk": "signed_by_dosen",
    "updated_at": "2026-11-11T10:20:00.000000Z"
  }
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "Permintaan upload SPK tidak valid."
}
```

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
  "message": "Kontrak kegiatan tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
  "status": "error",
  "message": "Validasi gagal.",
  "errors": {
    "file_spk_ttd": ["File SPK bertanda tangan wajib dilampirkan."]
  }
}
```

---

## 5. POST /api/sippm/kontrak/{id}/approve-spk

> Menyetujui SPK oleh LPPM untuk memicu pencairan Termin 1.

### Request Body

```json
{
  "catatan": "SPK lengkap dan telah diverifikasi LPPM."
}
```

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Dokumen SPK telah disetujui Admin SIPPM. Status Termin 1 diperbarui menjadi Waiting to Disburse.",
  "data": {
    "id": 1,
    "status_spk": "approved_by_lppm",
    "updated_at": "2026-11-11T10:25:00.000000Z"
  }
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "Permintaan persetujuan SPK tidak valid."
}
```

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
  "message": "Kontrak kegiatan tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
  "status": "error",
  "message": "Validasi gagal.",
  "errors": {
    "catatan": ["Catatan persetujuan SPK harus berupa teks."]
  }
}
```

---

## 6. POST /api/sippm/pencairan/{id}/upload-resi-sikeu

> Mengunggah bukti resi transfer disbursement dari SIKEU dan memperbarui status pencairan.

### Request Body

```json
{
  "bukti_transfer": "resi_transfer_sikeu_12345.pdf"
}
```

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Bukti resi transfer SIKEU berhasil diunggah. Status Termin 1 diperbarui menjadi Already Disburse.",
  "data": {
    "id": 1,
    "kontrak_id": 1,
    "termin_ke": 1,
    "persen_pencairan": 70,
    "nominal_pencairan": 14000000,
    "status_pencairan": "disbursed",
    "file_bukti_transfer": "resi_transfer_sikeu_12345.pdf"
  }
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "Permintaan unggah resi transfer tidak valid."
}
```

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
  "message": "Data pencairan tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
  "status": "error",
  "message": "Validasi gagal.",
  "errors": {
    "bukti_transfer": ["Bukti transfer wajib diunggah."]
  }
}
```

---

## 7. POST /api/sippm/kontrak/{id}/laporan

> Mengunggah dokumen laporan kemajuan atau laporan akhir riset/monev.

### Request Body

```json
{
  "jenis_laporan": "kemajuan",
  "file_laporan": "laporan_kemajuan_kontrak_001.pdf",
  "persentase_capaian": 70
}
```

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Laporan berhasil diunggah.",
  "data": {
    "id": 1,
    "kontrak_kegiatan_id": 1,
    "jenis_laporan": "kemajuan",
    "file_laporan": "laporan_kemajuan_kontrak_001.pdf",
    "persentase_capaian": 70,
    "created_at": "2026-11-11T10:30:00.000000Z",
    "updated_at": "2026-11-11T10:30:00.000000Z"
  }
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "Permintaan unggah laporan monev tidak valid."
}
```

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
  "message": "Kontrak kegiatan tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
  "status": "error",
  "message": "Validasi gagal.",
  "errors": {
    "jenis_laporan": ["Jenis laporan harus berupa kemajuan atau akhir."],
    "file_laporan": ["File laporan wajib diunggah."]
  }
}
```

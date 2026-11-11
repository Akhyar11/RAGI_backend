# ProposalKegiatanController

> **Modul**: SIPPM (Sistem Informasi Penelitian dan Pengabdian Masyarakat)  
> **Base URL**: `/api/sippm/proposal`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-11-11  
> **Diperbarui**: 2026-11-11  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/sippm/proposal` | Mengambil daftar proposal kegiatan riset/PkM | ✅ Bearer |
| POST | `/api/sippm/proposal` | Mengajukan/membuat proposal usulan kegiatan baru | ✅ Bearer |
| GET | `/api/sippm/proposal/{id}` | Mengambil detail lengkap proposal kegiatan | ✅ Bearer |
| PUT | `/api/sippm/proposal/{id}` | Memperbarui data draft proposal kegiatan | ✅ Bearer |
| POST | `/api/sippm/proposal/{id}/submit` | Mengajukan proposal resmi ke LPPM | ✅ Bearer |
| POST | `/api/sippm/proposal/{id}/assign-reviewer` | Menugaskan reviewer dosen/evaluator | ✅ Bearer |
| GET | `/api/sippm/proposal/{id}/logbook` | Mengambil riwayat catatan logbook kegiatan | ✅ Bearer |
| POST | `/api/sippm/proposal/{id}/logbook` | Menambahkan catatan logbook kegiatan lapangan | ✅ Bearer |
| GET | `/api/sippm/ref/mahasiswa/{mahasiswaId}/mata-kuliah-aktif` | Mengambil mata kuliah aktif mahasiswa (SIAKAD) | ✅ Bearer |
| GET | `/api/sippm/ref/tendik` | Mengambil data referensi tenaga kependidikan (SIMPEG) | ✅ Bearer |
| GET | `/api/sippm/ref/dosen` | Mengambil data referensi dosen pengusul (SIMPEG) | ✅ Bearer |

---

## 1. GET /api/sippm/proposal

> Mengambil daftar usulan proposal hibah penelitian dan pengabdian masyarakat.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari berdasarkan judul usulan proposal |
| `status` | string | ❌ | — | Filter status (draft, submitted, lolos, dll.) |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan data |
| `sort_order` | string | ❌ | `desc` | Arah pengurutan (`asc` atau `desc`) |
| `per_page` | integer | ❌ | `15` | Pagination limit data per halaman |
| `page` | integer | ❌ | `1` | Nomor halaman data |

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Daftar proposal kegiatan berhasil dimuat.",
  "data": [
    {
      "id": 1,
      "periode_hibah_id": 1,
      "skema_kegiatan_id": 1,
      "ketua_pegawai_id": 1,
      "judul": "Pengembangan Sistem Cerdas Pengenalan Pola",
      "abstrak": "Penelitian berfokus pada implementasi arsitektur deep learning...",
      "rumpun_ilmu": "Teknik Informatika",
      "anggaran_diajukan": 20000000,
      "anggaran_disetujui": null,
      "status": "submitted",
      "target_tkt": 3,
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
  "message": "Permintaan filter proposal tidak valid."
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
  "message": "Anda tidak memiliki izin untuk melihat daftar proposal."
}
```

**404 Not Found**
```json
{
  "status": "error",
  "message": "Data proposal tidak ditemukan."
}
```

---

## 2. POST /api/sippm/proposal

> Mengajukan usulan proposal baru ke sistem SIPPM.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Request Body

```json
{
  "periode_id": 1,
  "skema_id": 1,
  "ketua_pegawai_id": 1,
  "judul": "Pengembangan Sistem Cerdas Pengenalan Pola",
  "abstrak": "Penelitian implementasi sistem cerdas kecerdasan buatan...",
  "rumpun_ilmu": "Teknik Informatika",
  "target_tkt": 3,
  "anggaran_diajukan": 20000000,
  "file_proposal": "proposal_dummy.pdf"
}
```

### Response Sukses (201 Created)

```json
{
  "status": "success",
  "message": "Proposal berhasil didaftarkan.",
  "data": {
    "id": 1,
    "periode_id": 1,
    "skema_id": 1,
    "ketua_pegawai_id": 1,
    "judul": "Pengembangan Sistem Cerdas Pengenalan Pola",
    "status": "draft",
    "anggaran_diajukan": 20000000,
    "created_at": "2026-11-11T09:00:00.000000Z"
  }
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "Format data pendaftaran proposal tidak valid."
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
  "message": "Skema atau periode terkait tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
  "status": "error",
  "message": "Validasi gagal.",
  "errors": {
    "judul": ["Judul proposal wajib diisi."],
    "skema_id": ["Skema kegiatan wajib dipilih."]
  }
}
```

---

## 3. GET /api/sippm/proposal/{id}

> Mengambil detail lengkap proposal kegiatan beserta anggota, reviewer, dan status monev.

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
    "id": 1,
    "kode_proposal": "PROP-2026-001",
    "judul": "Pengembangan Sistem Cerdas Pengenalan Pola",
    "abstrak": "Penelitian implementasi sistem cerdas...",
    "status": "submitted",
    "anggaran_diajukan": 20000000,
    "anggaran_disetujui": 20000000,
    "ketua": {
      "id": 1,
      "nama_lengkap": "Dr. Eng. Ahmad Dahlan, M.T."
    },
    "skema": {
      "id": 1,
      "nama_skema": "Penelitian Dosen Pemula (PDP)"
    }
  }
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "ID proposal tidak valid."
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

---

## 4. PUT /api/sippm/proposal/{id}

> Memperbarui draft data proposal sebelum difinalisasi.

### Request Body

```json
{
  "judul": "Pengembangan Sistem Cerdas Pengenalan Pola Berbasis IoT",
  "abstrak": "Revisi abstrak penelitian...",
  "anggaran_diajukan": 22000000
}
```

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Data proposal berhasil diperbarui.",
  "data": {
    "id": 1,
    "judul": "Pengembangan Sistem Cerdas Pengenalan Pola Berbasis IoT",
    "anggaran_diajukan": 22000000,
    "updated_at": "2026-11-11T09:30:00.000000Z"
  }
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "Permintaan perbaruan proposal tidak valid."
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
    "target_tkt": ["Target TKT harus antara level 1 hingga 9."]
  }
}
```

---

## 5. POST /api/sippm/proposal/{id}/submit

> Mengirimkan proposal secara resmi ke LPPM untuk evaluasi kelayakan.

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Proposal resmi diajukan ke LPPM.",
  "data": {
    "id": 1,
    "status": "submitted",
    "updated_at": "2026-11-11T09:45:00.000000Z"
  }
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "Proposal belum siap disubmit atau draft tidak lengkap."
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

---

## 6. POST /api/sippm/proposal/{id}/assign-reviewer

> Menugaskan dosen penilai / reviewer untuk mengevaluasi substansi proposal.

### Request Body

```json
{
  "reviewer_pegawai_id": 2
}
```

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Reviewer berhasil ditugaskan.",
  "data": {
    "id": 1,
    "proposal_id": 1,
    "reviewer_pegawai_id": 2,
    "tgl_penugasan": "2026-11-11",
    "status_penilaian": "belum_dinilai"
  }
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "Permintaan penugasan reviewer tidak valid."
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
    "reviewer_pegawai_id": ["Pegawai reviewer tidak valid atau tidak terdaftar."]
  }
}
```

---

## 7. GET /api/sippm/proposal/{id}/logbook

> Mengambil daftar catatan logbook kemajuan kegiatan proposal.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `sort_by` | string | ❌ | `tgl_kegiatan` | Kolom pengurutan logbook |
| `sort_order` | string | ❌ | `desc` | Arah pengurutan (`asc` atau `desc`) |
| `per_page` | integer | ❌ | `15` | Pagination limit |
| `page` | integer | ❌ | `1` | Nomor halaman |

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Data logbook kegiatan berhasil diambil.",
  "data": [
    {
      "id": 1,
      "proposal_id": 1,
      "tgl_kegiatan": "2026-11-11",
      "persentase_capaian": 50,
      "uraian_kegiatan": "Pengujian model pada server dan validasi dataset citra.",
      "hambatan": "Koneksi GPU cluster sempat terputus.",
      "status_verifikasi": "pending",
      "created_at": "2026-11-11T09:30:00.000000Z",
      "updated_at": "2026-11-11T09:30:00.000000Z"
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
  "message": "ID proposal tidak valid."
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
  "message": "Anda tidak memiliki otorisasi untuk melihat logbook proposal ini."
}
```

**404 Not Found**
```json
{
  "status": "error",
  "message": "Proposal kegiatan tidak ditemukan."
}
```

---

## 8. POST /api/sippm/proposal/{id}/logbook

> Menambahkan entri catatan kemajuan lapangan / logbook riset.

### Request Body

```json
{
  "tgl_kegiatan": "2026-11-11",
  "persentase_capaian": 50,
  "uraian_kegiatan": "Pengujian model pada server dan validasi dataset citra.",
  "hambatan": "Koneksi GPU cluster sempat terputus."
}
```

### Response Sukses (201 Created)

```json
{
  "status": "success",
  "message": "Catatan logbook kegiatan berhasil disimpan.",
  "data": {
    "id": 1,
    "proposal_id": 1,
    "tgl_kegiatan": "2026-11-11",
    "persentase_capaian": 50,
    "uraian_kegiatan": "Pengujian model pada server dan validasi dataset citra.",
    "hambatan": "Koneksi GPU cluster sempat terputus.",
    "status_verifikasi": "pending",
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
  "message": "Format data logbook tidak valid."
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
  "message": "Anda tidak memiliki otorisasi untuk mengisi logbook proposal ini."
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
    "tgl_kegiatan": ["Field tgl_kegiatan wajib diisi."],
    "persentase_capaian": ["Persentase capaian harus bernilai antara 0 hingga 100."],
    "uraian_kegiatan": ["Uraian kegiatan wajib diisi."]
  }
}
```

---

## 9. GET /api/sippm/ref/mahasiswa/{mahasiswaId}/mata-kuliah-aktif

> Mengambil daftar mata kuliah yang sedang ditempuh mahasiswa untuk konversi nilai PkM/Riset (SIAKAD).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "data": [
    {
      "id": 101,
      "kode_mk": "IF401",
      "nama_mk": "Kecerdasan Buatan Terapan",
      "sks": 3
    }
  ]
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "ID mahasiswa tidak valid."
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
  "message": "Mahasiswa tidak ditemukan."
}
```

---

## 10. GET /api/sippm/ref/tendik

> Mengambil daftar referensi pegawai tenaga kependidikan (Tendik) aktif dari SIMPEG.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "nama_lengkap": "Budi Santoso, S.Kom.",
      "nip": "198501012010121001",
      "unit_kerja": "Biro Administrasi Akademik"
    }
  ]
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "Permintaan referensi tendik tidak valid."
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
  "message": "Data tendik tidak ditemukan."
}
```

---

## 11. GET /api/sippm/ref/dosen

> Mengambil daftar referensi dosen aktif dari SIMPEG untuk pengusul/anggota tim.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "nama_lengkap": "Dr. Eng. Ahmad Dahlan, M.T.",
      "nidn": "0012058001",
      "program_studi": "Teknik Informatika"
    }
  ]
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "Permintaan referensi dosen tidak valid."
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
  "message": "Data dosen tidak ditemukan."
}
```

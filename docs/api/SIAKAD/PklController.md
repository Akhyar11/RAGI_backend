# PklController

> **Modul**: SIAKAD  
> **Base URL**: `/api/v1/siakad/pkl`  
> **Autentikasi**: Bearer Token (Passport / IAM)  
> **Dibuat**: 2026-10-23  
> **Diperbarui**: 2026-10-23  

Pengelolaan alur Praktek Kerja Lapangan (PKL) / Magang Industri / MBKM mahasiswa dari pendaftaran, verifikasi kaprodi/admin, logbook asistensi bimbingan, hingga penilaian komprehensif dua arah (pembimbing lapangan & dosen).

## Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ (POST/PUT/PATCH) |

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/siakad/pkl` | Daftar pengajuan PKL mahasiswa | ✅ `siakad.pkl.read` |
| POST | `/api/v1/siakad/pkl` | Pendaftaran usulan PKL baru | ✅ `siakad.pkl.create` |
| GET | `/api/v1/siakad/pkl/{id}` | Detail berkas & progres PKL | ✅ `siakad.pkl.read` |
| PATCH | `/api/v1/siakad/pkl/{id}/verify` | Verifikasi/approval usulan & dosen pembimbing | ✅ `siakad.pkl.manage` |
| POST | `/api/v1/siakad/pkl/{id}/bimbingan` | Input logbook harian aktivitas PKL | ✅ `siakad.pkl.create` |
| PATCH | `/api/v1/siakad/pkl/{id}/bimbingan/{bimbinganId}/verify` | Verifikasi catatan logbook oleh dosen | ✅ `siakad.pkl.manage` |
| POST | `/api/v1/siakad/pkl/{id}/nilai` | Input rubrik penilaian lapangan & dosen | ✅ `siakad.pkl.manage` |

## Query Parameters (Endpoint List)

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari nama instansi, judul laporan, nama/NIM mahasiswa |
| `program_studi_id` | integer | ❌ | — | Filter ID program studi (`siakad_program_studi`) |
| `tahun_akademik_id` | integer | ❌ | — | Filter ID tahun akademik (`siakad_tahun_akademik`) |
| `status` | string | ❌ | — | Filter status (`draft`, `diajukan`, `disetujui`, `sedang_berjalan`, `laporan_selesai`, `dinilai`, `ditolak`) |
| `my_advisees_only` | boolean | ❌ | true | Khusus dosen: hanya mahasiswa bimbingan sendiri |
| `per_page` | integer | ❌ | 15 | Batas pagination (maks: 100) |
| `page` | integer | ❌ | 1 | Halaman data |
| `sort_order` | string | ❌ | desc | Urutan berdasarkan `created_at` (`asc` / `desc`) |

---

## 1. [GET] /api/v1/siakad/pkl

### Response 200 OK
```json
{
  "status": "success",
  "message": "Daftar pengajuan PKL berhasil dimuat.",
  "data": [
    {
      "id": 1,
      "mahasiswa_id": 10,
      "tahun_akademik_id": 2,
      "program_studi_id": 1,
      "dosen_pembimbing_id": 5,
      "jenis": "pkl_reguler",
      "judul_laporan": "Sistem Monitoring IoT",
      "instansi_nama": "PT Inovasi Teknologi",
      "instansi_alamat": "Jl. Sudirman Kav 20, Jakarta",
      "instansi_kota": "Jakarta",
      "pembimbing_lapangan_nama": "Budi Hartono",
      "pembimbing_lapangan_kontak": "08123456789",
      "pembimbing_lapangan_jabatan": "Tech Lead",
      "tanggal_mulai": "2027-01-10",
      "tanggal_selesai": "2027-04-10",
      "status": "disetujui",
      "created_at": "2026-10-23T21:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  }
}
```

### Response 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

### Response 403 Forbidden
```json
{
  "status": "error",
  "message": "This action is unauthorized."
}
```

---

## 2. [POST] /api/v1/siakad/pkl

### Request Body (Multipart/form-data atau JSON)
```json
{
  "tahun_akademik_id": 2,
  "jenis": "pkl_reguler",
  "judul_laporan": "Implementasi Event-Driven Microservices",
  "instansi_nama": "PT Solusi Cloud Nusantara",
  "instansi_alamat": "Jl. Rasuna Said No. 12, Jakarta Selatan",
  "instansi_kota": "Jakarta Selatan",
  "pembimbing_lapangan_nama": "Ahmad Subagio",
  "pembimbing_lapangan_kontak": "081234567890",
  "pembimbing_lapangan_jabatan": "Senior Engineering Manager",
  "tanggal_mulai": "2027-01-15",
  "tanggal_selesai": "2027-04-15"
}
```

### Response 201 Created
```json
{
  "status": "success",
  "message": "Pengajuan PKL berhasil disimpan dan menunggu persetujuan Program Studi.",
  "data": {
    "id": 1,
    "status": "diajukan",
    "instansi_nama": "PT Solusi Cloud Nusantara"
  }
}
```

### Response 422 Unprocessable Entity
```json
{
  "status": "error",
  "message": "The instansi nama field is required.",
  "errors": {
    "instansi_nama": ["The instansi nama field is required."],
    "tanggal_selesai": ["The tanggal selesai field must be a date after or equal to tanggal mulai."]
  }
}
```

---

## 3. [GET] /api/v1/siakad/pkl/{id}

### Path Parameters
| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID riwayat PKL (`siakad_pkl.id`) |

### Response 200 OK
```json
{
  "status": "success",
  "message": "Detail PKL berhasil dimuat.",
  "data": {
    "id": 1,
    "mahasiswa_id": 10,
    "tahun_akademik_id": 2,
    "program_studi_id": 1,
    "dosen_pembimbing_id": 5,
    "jenis": "pkl_reguler",
    "judul_laporan": "Implementasi Event-Driven Microservices",
    "instansi_nama": "PT Solusi Cloud Nusantara",
    "instansi_alamat": "Jl. Rasuna Said No. 12, Jakarta Selatan",
    "instansi_kota": "Jakarta Selatan",
    "pembimbing_lapangan_nama": "Ahmad Subagio",
    "pembimbing_lapangan_kontak": "081234567890",
    "pembimbing_lapangan_jabatan": "Senior Engineering Manager",
    "tanggal_mulai": "2027-01-15",
    "tanggal_selesai": "2027-04-15",
    "status": "disetujui",
    "berkas_permohonan": "siakad/pkl/permohonan/uuid.pdf",
    "bimbingans": [],
    "penilaian": null
  }
}
```

### Response 404 Not Found
```json
{
  "status": "error",
  "message": "Data PKL tidak ditemukan."
}
```

---

## 4. [PATCH] /api/v1/siakad/pkl/{id}/verify

### Path Parameters
| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID pengajuan PKL |

### Request Body
```json
{
  "status": "disetujui",
  "dosen_pembimbing_id": 5,
  "catatan_prodi": "Disetujui untuk instansi terkait."
}
```

### Response 200 OK
```json
{
  "status": "success",
  "message": "Status pengajuan PKL berhasil diperbarui.",
  "data": {
    "id": 1,
    "status": "disetujui",
    "dosen_pembimbing_id": 5
  }
}
```

### Response 404 Not Found
```json
{
  "status": "error",
  "message": "Data pengajuan PKL tidak ditemukan."
}
```

### Response 422 Unprocessable Entity
```json
{
  "status": "error",
  "message": "The status field is required.",
  "errors": {
    "status": ["The status field is required."]
  }
}
```

---

## 5. [POST] /api/v1/siakad/pkl/{id}/bimbingan

### Path Parameters
| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID PKL |

### Request Body
```json
{
  "tanggal": "2027-01-20",
  "aktivitas": "Konfigurasi CI/CD Pipeline dan unit test automation",
  "target_capaian": "Target testing code coverage 80%",
  "kendala": "Akses kredensial IAM Cloud perlu perizinan security"
}
```

### Response 201 Created
```json
{
  "status": "success",
  "message": "Logbook aktivitas PKL berhasil disimpan.",
  "data": {
    "id": 1,
    "pkl_id": 1,
    "tanggal": "2027-01-20",
    "aktivitas": "Konfigurasi CI/CD Pipeline dan unit test automation",
    "status_verifikasi": "pending"
  }
}
```

### Response 404 Not Found
```json
{
  "status": "error",
  "message": "Data PKL tidak ditemukan."
}
```

### Response 422 Unprocessable Entity
```json
{
  "status": "error",
  "message": "The tanggal field is required.",
  "errors": {
    "tanggal": ["The tanggal field is required."],
    "aktivitas": ["The aktivitas field is required."]
  }
}
```

---

## 6. [PATCH] /api/v1/siakad/pkl/{id}/bimbingan/{bimbinganId}/verify

### Path Parameters
| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID PKL |
| `bimbinganId` | integer | ✅ | ID Logbook Bimbingan |

### Request Body
```json
{
  "status_verifikasi": "disetujui",
  "catatan_dosen": "Target tercapai dengan baik, lanjutkan pengujian integrasi."
}
```

### Response 200 OK
```json
{
  "status": "success",
  "message": "Logbook bimbingan PKL berhasil diverifikasi.",
  "data": {
    "id": 1,
    "pkl_id": 1,
    "status_verifikasi": "disetujui",
    "catatan_dosen": "Target tercapai dengan baik, lanjutkan pengujian integrasi."
  }
}
```

### Response 404 Not Found
```json
{
  "status": "error",
  "message": "Data logbook bimbingan PKL tidak ditemukan."
}
```

### Response 422 Unprocessable Entity
```json
{
  "status": "error",
  "message": "The status verifikasi field is required.",
  "errors": {
    "status_verifikasi": ["The selected status verifikasi is invalid."]
  }
}
```

---

## 7. [POST] /api/v1/siakad/pkl/{id}/nilai

### Path Parameters
| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID PKL |

### Request Body
```json
{
  "nilai_lapangan_total": 88.50,
  "nilai_dosen_bimbingan": 90.00,
  "nilai_dosen_laporan": 85.00,
  "nilai_dosen_presentasi": 88.00,
  "catatan_dosen": "Kinerja dan laporan komprehensif sangat baik.",
  "is_finalized": true
}
```

### Response 200 OK
```json
{
  "status": "success",
  "message": "Penilaian PKL berhasil disimpan.",
  "data": {
    "id": 1,
    "pkl_id": 1,
    "nilai_lapangan_total": "88.50",
    "nilai_dosen_total": "87.67",
    "nilai_angka_akhir": "88.00",
    "nilai_huruf_akhir": "A",
    "bobot_mutu_akhir": "4.00",
    "is_finalized": true
  }
}
```

### Response 404 Not Found
```json
{
  "status": "error",
  "message": "Data PKL tidak ditemukan."
}
```

### Response 422 Unprocessable Entity
```json
{
  "status": "error",
  "message": "The nilai lapangan total field must be between 0 and 100.",
  "errors": {
    "nilai_lapangan_total": ["The nilai lapangan total field must be between 0 and 100."]
  }
}
```

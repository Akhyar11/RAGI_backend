# LuaranSippmController

> **Modul**: SIPPM (Sistem Informasi Penelitian dan Pengabdian Masyarakat)  
> **Base URL**: `/api/sippm/luaran`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-11-11  
> **Diperbarui**: 2026-11-11  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/sippm/luaran/publikasi` | Mengambil daftar publikasi ilmiah dosen/peneliti | ✅ Bearer |
| POST | `/api/sippm/luaran/publikasi` | Mendaftarkan artikel publikasi ilmiah baru | ✅ Bearer |
| GET | `/api/sippm/luaran/hki` | Mengambil daftar sertifikat HKI, paten, dan buku | ✅ Bearer |
| POST | `/api/sippm/luaran/hki` | Mendaftarkan HKI / paten / buku baru | ✅ Bearer |
| POST | `/api/sippm/luaran/fetch-external` | Menarik metadata artikel dari Scopus, SINTA, atau DOI | ✅ Bearer |
| POST | `/api/sippm/luaran/import-external` | Mengimpor hasil penarikan eksternal ke basis data lokal | ✅ Bearer |
| POST | `/api/sippm/luaran/publikasi/{id}/verify` | Memvalidasi dan memverifikasi kelayakan luaran publikasi oleh LPPM | ✅ Admin LPPM |
| POST | `/api/sippm/luaran/hki/{id}/verify` | Memvalidasi dan memverifikasi sertifikat HKI/paten oleh LPPM | ✅ Admin LPPM |

---

## 1. GET /api/sippm/luaran/publikasi

> Mengambil daftar publikasi artikel jurnal dan prosiding.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari berdasarkan judul artikel |
| `pegawai_id` | integer | ❌ | — | Filter ID pegawai penulis |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan |
| `sort_order` | string | ❌ | `desc` | Arah urutan (`asc` / `desc`) |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman |
| `page` | integer | ❌ | `1` | Nomor halaman |

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "proposal_id": 1,
        "pegawai_id": 1,
        "judul_artikel": "Deep Learning Approach for Agricultural Crop Disease Detection",
        "jenis_publikasi": "jurnal_internasional_bereputasi",
        "nama_jurnal_prosiding": "IEEE Transactions on Agriculture",
        "indexing": "scopus_q1",
        "volume_issue_tahun": "Vol. 12 No. 3 (2026)",
        "doi": "10.1109/TAGRI.2026.01234",
        "url_artikel": "https://doi.org/10.1109/TAGRI.2026.01234",
        "status_verifikasi": "verified",
        "created_at": "2026-11-11T08:00:00.000000Z",
        "updated_at": "2026-11-11T08:00:00.000000Z"
      }
    ],
    "first_page_url": "http://127.0.0.1:8000/api/sippm/luaran/publikasi?page=1",
    "from": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  }
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "Parameter filter atau pengurutan publikasi tidak valid."
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
  "message": "Data publikasi tidak ditemukan."
}
```

---

## 2. POST /api/sippm/luaran/publikasi

> Mendaftarkan luaran publikasi ilmiah baru.

### Request Body

```json
{
  "proposal_id": 1,
  "pegawai_id": 1,
  "judul_artikel": "Optimization of Deep Neural Networks for Edge Devices",
  "jenis_publikasi": "jurnal_internasional_bereputasi",
  "nama_jurnal_prosiding": "Journal of Computing Systems",
  "indexing": "scopus_q2",
  "volume_issue_tahun": "Vol. 8 No. 2 (2026)",
  "doi": "10.1016/j.jocs.2026.1001",
  "url_artikel": "https://doi.org/10.1016/j.jocs.2026.1001"
}
```

### Response Sukses (201 Created)

```json
{
  "status": "success",
  "message": "Publikasi ilmiah berhasil didaftarkan.",
  "data": {
    "id": 2,
    "proposal_id": 1,
    "pegawai_id": 1,
    "judul_artikel": "Optimization of Deep Neural Networks for Edge Devices",
    "jenis_publikasi": "jurnal_internasional_bereputasi",
    "nama_jurnal_prosiding": "Journal of Computing Systems",
    "indexing": "scopus_q2",
    "volume_issue_tahun": "Vol. 8 No. 2 (2026)",
    "doi": "10.1016/j.jocs.2026.1001",
    "url_artikel": "https://doi.org/10.1016/j.jocs.2026.1001",
    "status_verifikasi": "pending",
    "created_at": "2026-11-11T09:00:00.000000Z",
    "updated_at": "2026-11-11T09:00:00.000000Z"
  }
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "Format data pendaftaran publikasi tidak valid."
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
  "message": "Pegawai atau proposal terkait tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
  "status": "error",
  "message": "Validasi gagal.",
  "errors": {
    "pegawai_id": ["Pegawai pengusul wajib terdaftar pada basis data SIMPEG."],
    "judul_artikel": ["Judul artikel wajib diisi."]
  }
}
```

---

## 3. GET /api/sippm/luaran/hki

> Mengambil daftar HKI, paten, dan buku ajar ber-ISBN.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `pegawai_id` | integer | ❌ | — | Filter ID pegawai pemilik HKI |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan |
| `sort_order` | string | ❌ | `desc` | Arah urutan (`asc` / `desc`) |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman |
| `page` | integer | ❌ | `1` | Nomor halaman |

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "proposal_id": 1,
        "pegawai_id": 1,
        "jenis_luaran": "hak_cipta",
        "judul": "Aplikasi Telemedicine Kampus Berbasis AI",
        "nomor_pencatatan_isbn": "EC00202600123",
        "penerbit_lembaga": "DJKI Kemenkumham RI",
        "tgl_terbit_catat": "2026-05-10",
        "status_verifikasi": "verified",
        "created_at": "2026-11-11T08:00:00.000000Z",
        "updated_at": "2026-11-11T08:00:00.000000Z"
      }
    ],
    "first_page_url": "http://127.0.0.1:8000/api/sippm/luaran/hki?page=1",
    "from": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  }
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "Parameter filter atau pengurutan HKI tidak valid."
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
  "message": "Data HKI tidak ditemukan."
}
```

---

## 4. POST /api/sippm/luaran/hki

> Mendaftarkan permohonan sertifikat HKI, paten, atau buku ajar baru.

### Request Body

```json
{
  "proposal_id": 1,
  "pegawai_id": 1,
  "jenis_luaran": "paten",
  "judul": "Metode Cepat Ekstraksi Minyak Atsiri dengan Gelombang Mikro Terarah",
  "nomor_pencatatan_isbn": "P00202600987",
  "penerbit_lembaga": "DJKI Kemenkumham RI",
  "tgl_terbit_catat": "2026-08-15"
}
```

### Response Sukses (201 Created)

```json
{
  "status": "success",
  "message": "Data HKI / Buku berhasil didaftarkan.",
  "data": {
    "id": 2,
    "proposal_id": 1,
    "pegawai_id": 1,
    "jenis_luaran": "paten",
    "judul": "Metode Cepat Ekstraksi Minyak Atsiri dengan Gelombang Mikro Terarah",
    "nomor_pencatatan_isbn": "P00202600987",
    "penerbit_lembaga": "DJKI Kemenkumham RI",
    "tgl_terbit_catat": "2026-08-15",
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
  "message": "Format data pendaftaran HKI tidak valid."
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
  "message": "Pegawai atau proposal terkait tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
  "status": "error",
  "message": "Validasi gagal.",
  "errors": {
    "nomor_pencatatan_isbn": ["Nomor pencatatan ISBN/HKI sudah pernah didaftarkan."]
  }
}
```

---

## 5. POST /api/sippm/luaran/fetch-external

> Menarik metadata artikel ilmiah secara otomatis dari Scopus, SINTA, atau basis data DOI Crossref.

### Request Body

```json
{
  "source": "doi",
  "identifier": "10.1109/TAGRI.2026.01234"
}
```

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Data publikasi berhasil ditarik dari DOI",
  "data": {
    "judul_artikel": "Deep Learning Approach for Agricultural Crop Disease Detection",
    "nama_jurnal_prosiding": "IEEE Transactions on Agriculture",
    "volume_issue_tahun": "Vol. 12 No. 3 (2026)",
    "doi": "10.1109/TAGRI.2026.01234",
    "publisher": "IEEE"
  }
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "Parameter sumber atau pengidentifikasi eksternal tidak valid."
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
  "message": "Artikel tidak ditemukan pada sumber eksternal."
}
```

**422 Unprocessable Entity**
```json
{
  "status": "error",
  "message": "Validasi gagal atau pengidentifikasi eksternal tidak ditemukan."
}
```

---

## 6. POST /api/sippm/luaran/import-external

> Mengimpor metadata hasil penarikan eksternal ke dalam sistem lokal.

### Request Body

```json
{
  "pegawai_id": 1,
  "judul_artikel": "Deep Learning Approach for Agricultural Crop Disease Detection",
  "jenis_publikasi": "jurnal_internasional_bereputasi",
  "nama_jurnal_prosiding": "IEEE Transactions on Agriculture",
  "doi": "10.1109/TAGRI.2026.01234"
}
```

### Response Sukses (201 Created)

```json
{
  "status": "success",
  "message": "Data publikasi berhasil di-import dan disinkronkan ke sistem.",
  "data": {
    "id": 3,
    "pegawai_id": 1,
    "judul_artikel": "Deep Learning Approach for Agricultural Crop Disease Detection",
    "jenis_publikasi": "jurnal_internasional_bereputasi",
    "nama_jurnal_prosiding": "IEEE Transactions on Agriculture",
    "created_at": "2026-11-11T10:00:00.000000Z"
  }
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "Format data impor eksternal tidak valid."
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
  "message": "Pegawai tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
  "status": "error",
  "message": "Validasi gagal.",
  "errors": {
    "pegawai_id": ["Pegawai pengusul wajib diisi."]
  }
}
```

---

## 7. POST /api/sippm/luaran/publikasi/{id}/verify

> Memvalidasi dan memverifikasi kelayakan publikasi ilmiah oleh LPPM.

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Publikasi ilmiah berhasil diverifikasi oleh LPPM.",
  "data": {
    "id": 1,
    "status_verifikasi": "verified",
    "updated_at": "2026-11-11T10:10:00.000000Z"
  }
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "ID publikasi tidak valid."
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
  "message": "Publikasi ilmiah tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
  "status": "error",
  "message": "Status verifikasi publikasi tidak memenuhi syarat validasi LPPM."
}
```

---

## 8. POST /api/sippm/luaran/hki/{id}/verify

> Memvalidasi dan memverifikasi sertifikat HKI/paten oleh LPPM.

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Data HKI / Buku berhasil diverifikasi oleh LPPM.",
  "data": {
    "id": 1,
    "status_verifikasi": "verified",
    "updated_at": "2026-11-11T10:15:00.000000Z"
  }
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "ID HKI tidak valid."
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
  "message": "Data HKI / Buku tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
  "status": "error",
  "message": "Status verifikasi HKI tidak memenuhi syarat validasi LPPM."
}
```

# MasterSippmController

> **Modul**: SIPPM (Sistem Informasi Penelitian dan Pengabdian Masyarakat)  
> **Base URL**: `/api/sippm`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-11-11  
> **Diperbarui**: 2026-11-11  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/sippm/skema` | Mengambil seluruh master skema kegiatan penelitian & pengabdian | ✅ Bearer |
| POST | `/api/sippm/skema` | Menambahkan master skema kegiatan baru | ✅ Bearer |
| PUT | `/api/sippm/skema/{id}` | Memperbarui master skema kegiatan | ✅ Bearer |
| DELETE | `/api/sippm/skema/{id}` | Menghapus master skema kegiatan | ✅ Bearer |
| GET | `/api/sippm/periode` | Mengambil seluruh gelombang periode hibah tahunan | ✅ Bearer |
| POST | `/api/sippm/periode` | Menambahkan gelombang periode hibah baru | ✅ Bearer |
| PUT | `/api/sippm/periode/{id}` | Memperbarui periode hibah | ✅ Bearer |
| DELETE | `/api/sippm/periode/{id}` | Menghapus periode hibah | ✅ Bearer |

---

## 1. GET /api/sippm/skema

> Mengambil daftar master skema kegiatan penelitian dan pengabdian masyarakat.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

Tidak membutuhkan query parameter (mengembalikan seluruh daftar master skema untuk opsi dropdown).

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "kode": "SKM-PEN-01",
      "nama": "Penelitian Dasar Unggulan Perguruan Tinggi",
      "tipe": "penelitian",
      "sumber_dana": "internal",
      "maksimal_anggaran": 25000000,
      "deskripsi": "Skema riset dasar untuk pengembangan IPTEK",
      "is_active": true,
      "created_at": "2026-11-11T08:00:00.000000Z",
      "updated_at": "2026-11-11T08:00:00.000000Z"
    }
  ]
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "Permintaan daftar skema tidak valid."
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
  "message": "Anda tidak memiliki izin untuk melihat master skema kegiatan."
}
```

**404 Not Found**
```json
{
  "status": "error",
  "message": "Data master skema kegiatan tidak ditemukan."
}
```

---

## 2. POST /api/sippm/skema

> Menambahkan master skema kegiatan baru.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Request Body

```json
{
  "kode": "SKM-PEN-02",
  "nama": "Penelitian Terapan Kampus",
  "tipe": "penelitian",
  "sumber_dana": "internal",
  "maksimal_anggaran": 30000000,
  "deskripsi": "Skema riset terapan berorientasi paten dan prototipe.",
  "is_active": true
}
```

### Response Sukses (201 Created)

```json
{
  "status": "success",
  "message": "Skema kegiatan berhasil ditambahkan.",
  "data": {
    "id": 2,
    "kode": "SKM-PEN-02",
    "nama": "Penelitian Terapan Kampus",
    "tipe": "penelitian",
    "sumber_dana": "internal",
    "maksimal_anggaran": 30000000,
    "deskripsi": "Skema riset terapan berorientasi paten dan prototipe.",
    "is_active": true,
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
  "message": "Format data skema kegiatan tidak valid."
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
  "message": "Sumber daya skema tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
  "status": "error",
  "message": "Validasi gagal.",
  "errors": {
    "kode": ["Kode skema wajib diisi atau sudah terdaftar."],
    "nama": ["Nama skema wajib diisi."]
  }
}
```

---

## 3. PUT /api/sippm/skema/{id}

> Memperbarui master skema kegiatan penelitian atau pengabdian.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Request Body

```json
{
  "nama": "Penelitian Terapan Kampus Unggulan",
  "maksimal_anggaran": 35000000,
  "is_active": true
}
```

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Skema kegiatan berhasil diperbarui.",
  "data": {
    "id": 1,
    "kode": "SKM-PEN-01",
    "nama": "Penelitian Terapan Kampus Unggulan",
    "maksimal_anggaran": 35000000,
    "is_active": true,
    "updated_at": "2026-11-11T10:00:00.000000Z"
  }
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "ID skema tidak valid."
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
  "message": "Skema kegiatan tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
  "status": "error",
  "message": "Validasi gagal.",
  "errors": {
    "nama": ["Nama skema tidak boleh kosong."]
  }
}
```

---

## 4. DELETE /api/sippm/skema/{id}

> Menghapus master skema kegiatan dari sistem.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Skema kegiatan berhasil dihapus.",
  "data": null
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "ID skema tidak valid."
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
  "message": "Skema kegiatan tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
  "status": "error",
  "message": "Skema kegiatan tidak dapat dihapus karena telah digunakan pada proposal aktif."
}
```

---

## 5. GET /api/sippm/periode

> Mengambil daftar gelombang periode hibah riset dan PkM tahunan.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

Tidak membutuhkan query parameter (mengembalikan seluruh daftar gelombang periode untuk opsi pilihan).

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "tahun_anggaran": "2026/2027",
      "nama_gelombang": "Hibah Internal Gelombang I",
      "tgl_buka_proposal": "2026-01-01",
      "tgl_tutup_proposal": "2026-03-31",
      "tgl_tutup_monev": "2026-07-31",
      "tgl_tutup_laporan": "2026-11-30",
      "is_active": true,
      "created_at": "2026-11-11T08:00:00.000000Z",
      "updated_at": "2026-11-11T08:00:00.000000Z"
    }
  ]
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "Permintaan daftar periode hibah tidak valid."
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
  "message": "Data periode hibah tidak ditemukan."
}
```

---

## 6. POST /api/sippm/periode

> Menambahkan periode gelombang hibah baru.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Request Body

```json
{
  "tahun_anggaran": "2026/2027",
  "nama_gelombang": "Hibah Internal Gelombang II",
  "tgl_buka_proposal": "2026-06-01",
  "tgl_tutup_proposal": "2026-08-31",
  "is_active": true
}
```

### Response Sukses (201 Created)

```json
{
  "status": "success",
  "message": "Gelombang periode hibah berhasil dibuat.",
  "data": {
    "id": 2,
    "tahun_anggaran": "2026/2027",
    "nama_gelombang": "Hibah Internal Gelombang II",
    "tgl_buka_proposal": "2026-06-01",
    "tgl_tutup_proposal": "2026-08-31",
    "is_active": true,
    "created_at": "2026-11-11T09:15:00.000000Z",
    "updated_at": "2026-11-11T09:15:00.000000Z"
  }
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "Format data periode hibah tidak valid."
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
  "message": "Sumber daya periode hibah tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
  "status": "error",
  "message": "Validasi gagal.",
  "errors": {
    "tgl_tutup_proposal": ["Tanggal tutup proposal harus sama atau setelah tanggal buka proposal."]
  }
}
```

---

## 7. PUT /api/sippm/periode/{id}

> Memperbarui periode hibah tahunan.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Request Body

```json
{
  "nama_gelombang": "Hibah Internal Periode Perpanjangan",
  "tgl_tutup_proposal": "2026-09-15"
}
```

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Periode hibah berhasil diperbarui.",
  "data": {
    "id": 1,
    "tahun_anggaran": "2026/2027",
    "nama_gelombang": "Hibah Internal Periode Perpanjangan",
    "tgl_tutup_proposal": "2026-09-15",
    "updated_at": "2026-11-11T10:15:00.000000Z"
  }
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "ID periode tidak valid."
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
  "message": "Periode hibah tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
  "status": "error",
  "message": "Validasi gagal.",
  "errors": {
    "tgl_tutup_proposal": ["Tanggal tutup proposal harus setelah tanggal buka proposal."]
  }
}
```

---

## 8. DELETE /api/sippm/periode/{id}

> Menghapus periode hibah dari sistem.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Periode hibah berhasil dihapus.",
  "data": null
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "ID periode tidak valid."
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
  "message": "Periode hibah tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
  "status": "error",
  "message": "Periode hibah tidak dapat dihapus karena telah memiliki pendaftaran proposal."
}
```

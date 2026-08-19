# MasterSippmController

> **Modul**: SIPPM (Penelitian & PkM)  
> **Base URL**: `/api/sippm`  
> **Autentikasi**: Bearer Token (Sanctum / auth:api)  
> **Dibuat**: 2026-08-04  
> **Diperbarui**: 2026-08-04

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth | Permission SSO |
|---|---|---|---|---|
| GET | `/api/sippm/skema` | Get master skema kegiatan | ✅ Bearer | `sippm.skema.read` |
| POST | `/api/sippm/skema` | Tambah master skema baru | ✅ Bearer | `sippm.skema.create` |
| PUT | `/api/sippm/skema/{id}` | Edit master skema kegiatan | ✅ Bearer | `sippm.skema.update` |
| DELETE | `/api/sippm/skema/{id}` | Hapus master skema kegiatan | ✅ Bearer | `sippm.skema.delete` |
| GET | `/api/sippm/periode` | Get master periode hibah | ✅ Bearer | `sippm.periode.read` |
| POST | `/api/sippm/periode` | Tambah master periode hibah baru | ✅ Bearer | `sippm.periode.create` |
| PUT | `/api/sippm/periode/{id}` | Edit master periode hibah | ✅ Bearer | `sippm.periode.update` |
| DELETE | `/api/sippm/periode/{id}` | Hapus master periode hibah | ✅ Bearer | `sippm.periode.delete` |

---

## 1. PUT /api/sippm/skema/{id}

> Memperbarui data master skema kegiatan hibah.

### Request Body
```json
{
  "kode": "SKM-PD-01",
  "nama": "Penelitian Dasar Dosen Pemula",
  "tipe": "penelitian",
  "sumber_dana": "internal",
  "maksimal_anggaran": 25000000,
  "deskripsi": "Skema hibah penelitian dasar bagi dosen pemula."
}
```

### Response Sukses (200 OK)
```json
{
  "status": "success",
  "message": "Skema kegiatan berhasil diperbarui.",
  "data": {
    "id": 1,
    "kode": "SKM-PD-01",
    "nama": "Penelitian Dasar Dosen Pemula",
    "tipe": "penelitian",
    "sumber_dana": "internal",
    "maksimal_anggaran": "25000000.00",
    "is_active": true
  }
}
```

---

## 2. DELETE /api/sippm/skema/{id}

> Menghapus data master skema kegiatan.

### Response Sukses (200 OK)
```json
{
  "status": "success",
  "message": "Skema kegiatan berhasil dihapus."
}
```

---

## 3. PUT /api/sippm/periode/{id}

> Memperbarui data master periode hibah.

### Request Body
```json
{
  "tahun_anggaran": "2026/2027",
  "nama_gelombang": "Gelombang I 2026/2027",
  "tgl_buka_proposal": "2026-08-01",
  "tgl_tutup_proposal": "2026-09-30"
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
    "nama_gelombang": "Gelombang I 2026/2027",
    "tgl_buka_proposal": "2026-08-01",
    "tgl_tutup_proposal": "2026-09-30",
    "is_active": true
  }
}
```

---

## 4. DELETE /api/sippm/periode/{id}

> Menghapus data master periode hibah.

### Response Sukses (200 OK)
```json
{
  "status": "success",
  "message": "Periode hibah berhasil dihapus."
}
```

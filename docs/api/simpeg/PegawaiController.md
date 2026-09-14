# PegawaiController

> **Modul**: SIMPEG (Sistem Informasi Kepegawaian)  
> **Base URL**: `/api/simpeg/pegawai`  
> **Autentikasi**: Bearer Token (Passport)  
> **Dibuat**: 2026-09-14  
> **Diperbarui**: 2026-09-14  

Controller ini mengelola data pegawai, pembuatan akun SSO otomatis (`core_users`) dengan password default `indonusa`, pengunduhan template import berkas, serta import data pegawai massal via CSV atau Excel (XLSX/XLS).

## Daftar Endpoint

| Method | Endpoint | Fungsi | Permission |
|---|---|---|---|
| GET | `/api/simpeg/pegawai/template` | Mengunduh template file CSV impor pegawai | `simpeg.pegawai.read` / `simpeg.pegawai.create` / `simpeg.pegawai.manage` |
| POST | `/api/simpeg/pegawai/import` | Mengimpor berkas data pegawai (.csv, .xlsx) | `simpeg.pegawai.create` / `simpeg.pegawai.manage` |
| GET | `/api/simpeg/pegawai/me` | Profil data pegawai dari user login | Login User |
| GET | `/api/simpeg/pegawai` | Daftar data pegawai (dengan pagination & filter) | `simpeg.pegawai.read` / `simpeg.pegawai.manage` |
| POST | `/api/simpeg/pegawai` | Tambah data pegawai baru (otomatis buat akun SSO) | `simpeg.pegawai.create` / `simpeg.pegawai.manage` |
| GET | `/api/simpeg/pegawai/{id}` | Detail profil pegawai | `simpeg.pegawai.read` / `simpeg.pegawai.manage` |
| PUT | `/api/simpeg/pegawai/{id}` | Memperbarui data pegawai | `simpeg.pegawai.update` / `simpeg.pegawai.manage` |
| DELETE | `/api/simpeg/pegawai/{id}` | Menghapus data pegawai | `simpeg.pegawai.delete` / `simpeg.pegawai.manage` |

---

## GET /api/simpeg/pegawai/template

> Mengunduh file template CSV dengan UTF-8 BOM untuk kompatibilitas Microsoft Excel.

### Headers
```http
Authorization: Bearer <token>
```

### Response
- Header: `Content-Type: text/csv; charset=UTF-8`
- Header: `Content-Disposition: attachment; filename="template_import_pegawai.csv"`
- Body: Berkas CSV mentah dengan kolom:
  `nip,nik,nama_lengkap,email,telepon,jenis_kelamin,tempat_lahir,tanggal_lahir,jenis_pegawai,status_kepegawaian,unit_kerja,jabatan,tanggal_masuk,alamat`

---

## POST /api/simpeg/pegawai/import

> Mengunggah dan memproses data pegawai secara massal dari file CSV atau Excel. Setiap pegawai yang diimpor akan otomatis dibuatkan akun SSO di `core_users` dengan default password `indonusa`.

### Headers
```http
Authorization: Bearer <token>
Content-Type: multipart/form-data
```

### Request Body (Form Data)
| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `file` | file | Ya | File berekstensi `.csv`, `.txt`, `.xlsx`, atau `.xls` (Maks 10MB) |

### Response Success (200 OK)
```json
{
  "status": "success",
  "message": "Proses impor selesai: 2 berhasil, 0 gagal.",
  "data": {
    "total": 2,
    "success": 2,
    "failed": 0,
    "errors": [],
    "data": [
      {
        "id": 12,
        "nama": "Dr. Ahmad Fadhil, M.Kom.",
        "nip": "198501152010121001",
        "email": "ahmad.fadhil@campus.ac.id",
        "username": "198501152010121001"
      }
    ]
  }
}
```

---

## POST /api/simpeg/pegawai

> Menambahkan data pegawai secara individual. Jika `user_id` kosong, sistem akan otomatis membuat akun SSO di `core_users` dengan role yang sesuai (`dosen` atau `tendik`) dan password default `indonusa`.

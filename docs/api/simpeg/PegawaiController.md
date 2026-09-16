# PegawaiController

> **Modul**: SIMPEG (Sistem Informasi Kepegawaian)  
> **Base URL**: `/api/simpeg/pegawai`  
> **Autentikasi**: Bearer Token (Passport)  
> **Dibuat**: 2026-09-14  
> **Dibuat**: 2026-09-14  
> **Diperbarui**: 2026-09-15  

Controller ini mengelola data pegawai, pembuatan akun SSO otomatis (`core_users`) dengan password default `indonusa`, relasi multi-role jenis pegawai (`core_roles`), pengunduhan template import berkas, serta import data pegawai massal via CSV atau Excel (XLSX/XLS).

## Daftar Endpoint

| Method | Endpoint | Fungsi | Permission |
|---|---|---|---|
| GET | `/api/simpeg/pegawai/roles` | Daftar opsi role aktif SSO untuk jenis pegawai | `simpeg.pegawai.read` / `simpeg.pegawai.manage` |
| GET | `/api/simpeg/pegawai/template` | Mengunduh template file CSV impor pegawai | `simpeg.pegawai.read` / `simpeg.pegawai.create` / `simpeg.pegawai.manage` |
| POST | `/api/simpeg/pegawai/import` | Mengimpor berkas data pegawai (.csv, .xlsx) | `simpeg.pegawai.create` / `simpeg.pegawai.manage` |
| GET | `/api/simpeg/pegawai/me` | Profil data pegawai dari user login | Login User |
| GET | `/api/simpeg/pegawai` | Daftar data pegawai (dengan pagination & filter role) | `simpeg.pegawai.read` / `simpeg.pegawai.manage` |
| POST | `/api/simpeg/pegawai` | Tambah data pegawai baru (multi-role SSO) | `simpeg.pegawai.create` / `simpeg.pegawai.manage` |
| GET | `/api/simpeg/pegawai/{id}` | Detail profil pegawai (termasuk roles & dosen) | `simpeg.pegawai.read` / `simpeg.pegawai.manage` |
| PUT | `/api/simpeg/pegawai/{id}` | Memperbarui data pegawai & sinkronisasi roles | `simpeg.pegawai.update` / `simpeg.pegawai.manage` |
| DELETE | `/api/simpeg/pegawai/{id}` | Menghapus data pegawai | `simpeg.pegawai.delete` / `simpeg.pegawai.manage` |
| POST | `/api/simpeg/pegawai/{id}/reset-face` | Mereset data biometrik wajah pegawai untuk registrasi ulang | `simpeg.pegawai.update` / `simpeg.pegawai.manage` |

---

## GET /api/simpeg/pegawai/roles

> Mengambil daftar seluruh role aktif dari SSO (`core_roles`) untuk digunakan sebagai opsi dinamis pilihan jenis pegawai.

### Headers
```http
Authorization: Bearer <token>
Accept: application/json
```

### Response Success (200 OK)
```json
{
  "status": "success",
  "message": "Daftar role berhasil diambil",
  "data": [
    {
      "id": 5,
      "name": "Dosen Pengajar",
      "slug": "dosen",
      "description": "Tenaga pendidik akademik"
    },
    {
      "id": 6,
      "name": "Tenaga Kependidikan",
      "slug": "tendik",
      "description": "Staf administrasi dan operasional"
    }
  ]
}
```

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

## POST /api/simpeg/pegawai/{id}/reset-face

> Mereset data biometrik wajah pegawai (`face_embedding` dan `face_enrolled_at`). Digunakan oleh Admin/HR ketika pegawai berganti perangkat atau gagal mengenali wajah sehingga perlu melakukan enrollment biometrik wajah ulang melalui aplikasi mobile presensi.

### Headers
```http
Authorization: Bearer <token>
Accept: application/json
```

### URL Parameters
| Parameter | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `id` | integer | Ya | ID entitas pegawai (`simpeg_pegawai.id`) |

### Response Success (200 OK)
```json
{
  "status": "success",
  "message": "Data biometrik wajah pegawai berhasil direset. Pegawai dapat mendaftarkan ulang wajahnya via aplikasi mobile presensi.",
  "data": {
    "id": 1,
    "nama_lengkap": "Dr. Wasis Utama, M.T.",
    "is_face_enrolled": false,
    "face_enrolled_at": null
  }
}
```

### Response Error (404 Not Found)
```json
{
  "status": "error",
  "message": "Pegawai tidak ditemukan."
}
```


# PendaftaranImportController

> **Modul**: SPMB / **Base URL**: `/api/spmb` / **Autentikasi**: Bearer Token / **Dibuat/Diperbarui**: 2026-11-26

Import data pendaftar dari file Excel sistem lama (format "DATA PENDAFTARAN ANGKATAN ..."). Dua langkah: **preview** (validasi saja, tidak menyimpan) lalu **import** (menyimpan baris valid). File yang sama dikirim pada kedua langkah; server tidak menyimpan file.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/spmb/pendaftaran/import/template` | Mengunduh template Excel kosong (sheet data + sheet petunjuk) | Bearer Token (`spmb.manage`) |
| POST | `/api/spmb/pendaftaran/import/preview` | Validasi seluruh baris tanpa menyimpan | Bearer Token (`spmb.manage`) |
| POST | `/api/spmb/pendaftaran/import` | Menyimpan baris valid beserta akun login | Bearer Token (`spmb.manage`) |

## Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `multipart/form-data` | ✅ |

## Body (kedua endpoint)

| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `file` | file | ✅ | `.xlsx` / `.xls`, maksimal 10 MB (`spmb.import.max_file_kb`) |

Header kolom dikenali berdasarkan nama (bukan posisi). Kolom wajib: `No.Daftar`, `Nama`, `Email`, `Program Studi`. Baris judul di atas header diabaikan.

## [GET] `/api/spmb/pendaftaran/import/template`

Mengunduh template Excel kosong (`template-import-pendaftar.xlsx`: sheet "Data Pendaftar" dengan header baris ke-3 dan sheet "Petunjuk"). Setiap unduhan dicatat di audit log (`pendaftaran_import_download_template`).

### Response 200

Berkas biner `.xlsx` dengan header `Content-Disposition: attachment; filename=template-import-pendaftar.xlsx` dan `Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`.

### Response 401

~~~json
{
  "status": "error",
  "message": "Token tidak valid atau sesi telah berakhir."
}
~~~

### Response 403

~~~json
{
  "status": "error",
  "message": "Unauthorized access. Menu ini belum diaktifkan untuk role Anda di database."
}
~~~

## [POST] `/api/spmb/pendaftaran/import/preview`

Memvalidasi seluruh baris tanpa menyimpan data.

### Response 200

~~~json
{
  "status": "success",
  "message": "Pratinjau import berhasil dibuat. Belum ada data yang disimpan.",
  "data": {
    "summary": {
      "total": 2,
      "valid": 1,
      "error": 1
    },
    "warnings": [
      "Gelombang dan Jalur Masuk akan dikosongkan untuk seluruh data import. Tetapkan secara manual setelah import.",
      "Akun login dibuat otomatis untuk setiap pendaftar dengan password default. Minta pendaftar segera menggantinya."
    ],
    "rows": [
      {
        "row": 4,
        "no_daftar": "202720001",
        "nama": "RISKA RACHMAWATI",
        "email": "rrachmawati26@gmail.com",
        "program_studi": "D3-Farmasi",
        "status": "valid",
        "errors": []
      },
      {
        "row": 5,
        "no_daftar": "202720002",
        "nama": "RISKA RACHMAWATI",
        "email": "b@test.com",
        "program_studi": "D3-Tidak Ada",
        "status": "error",
        "errors": [
          "Program Studi \"D3-Tidak Ada\" tidak ditemukan atau tidak aktif."
        ]
      }
    ]
  }
}
~~~

### Response 401

~~~json
{
  "status": "error",
  "message": "Token tidak valid atau sesi telah berakhir."
}
~~~

### Response 403

~~~json
{
  "status": "error",
  "message": "Unauthorized access. Menu ini belum diaktifkan untuk role Anda di database."
}
~~~

### Response 422 (file tidak diunggah / bukan Excel / header tidak dikenali)

~~~json
{
  "status": "error",
  "message": "File Excel wajib diunggah.",
  "errors": {
    "file": [
      "File Excel wajib diunggah."
    ]
  }
}
~~~

~~~json
{
  "status": "error",
  "message": "Header tidak dikenali. Kolom wajib: No.Daftar, Nama, Email, Program Studi.",
  "errors": { "file": ["Header tidak dikenali. Kolom wajib: No.Daftar, Nama, Email, Program Studi."] }
}
~~~

## [POST] `/api/spmb/pendaftaran/import`

Menyimpan baris valid dan membuat akun `calon_mhs` per pendaftar. Baris bermasalah dilewati dan dilaporkan pada `failed`.

### Response 201

~~~json
{
  "status": "success",
  "message": "1 dari 2 pendaftar berhasil diimpor.",
  "data": {
    "summary": {
      "total": 2,
      "imported": 1,
      "failed": 1
    },
    "failed": [
      {
        "row": 5,
        "no_daftar": "202720002",
        "nama": "RISKA RACHMAWATI",
        "errors": [
          "Program Studi \"D3-Tidak Ada\" tidak ditemukan atau tidak aktif."
        ]
      }
    ],
    "default_password_used": true
  }
}
~~~

### Response 401

~~~json
{
  "status": "error",
  "message": "Token tidak valid atau sesi telah berakhir."
}
~~~

### Response 403

~~~json
{
  "status": "error",
  "message": "Unauthorized access. Menu ini belum diaktifkan untuk role Anda di database."
}
~~~

### Response 422

~~~json
{
  "status": "error",
  "message": "File Excel wajib diunggah.",
  "errors": {
    "file": [
      "File Excel wajib diunggah."
    ]
  }
}
~~~

## Aturan Validasi Baris

| Kondisi | Hasil |
|---|---|
| `No.Daftar` kosong / duplikat di file / sudah ada di `spmb_pendaftaran_calon_mhs` | Ditolak |
| `Email` kosong / tidak valid / duplikat di file / sudah ada di `core_users` | Ditolak |
| `Nama` kosong | Ditolak |
| `Program Studi` (`<jenjang>-<nama>`) tidak cocok dengan prodi aktif | Ditolak |
| `Jenis Kelamin` selain `L`/`P`, `Tgl.Lahir` tidak terbaca atau di masa depan | Ditolak |

## Pemetaan Data

- `no_pendaftaran` = `No.Daftar`; `sumber_pendaftaran` = `import`; `status` = `submitted`; `asal_lulusan` = `sekolah`.
- `status_pembayaran` = `lunas` jika `Validasi Pembayaran` = "Sudah Membayar", selain itu `belum_bayar`.
- `gelombang_id` dan `nik` dikosongkan (nullable). `created_at` diisi dari `Tgl. Daftar`.
- `Kelas`, `Beasiswa`, `Keterangan Seleksi`, `Bayar Pendaftaran`, `Validasi Pembayaran`, `On/Off-Line` → JSON `data_import`; `Pekerjaan OrgTua` → `pekerjaan_ortu`.
- Akun `core_users` dibuat per pendaftar (role `calon_mhs`, aktif, email terverifikasi) dengan password default dari `config('spmb.import.default_password')` (env `SPMB_IMPORT_DEFAULT_PASSWORD`; nilai bawaan ada di `config/spmb.php`).
- Audit log `pendaftaran_import` memuat ringkasan jumlah dan `imported_ids`, tanpa password.

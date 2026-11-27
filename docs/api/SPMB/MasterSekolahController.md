# MasterSekolahController

> **Modul**: SPMB / **Base URL**: `/api/spmb/master-sekolah` / **Autentikasi**: Bearer Token / **Dibuat/Diperbarui**: 2026-11-27

Mengelola **master sekolah asal** (`spmb_master_sekolah`): sekolah yang belum ada di API eksternal sekolah dan ditambahkan lewat opsi **Create** pada kolom Asal Sekolah (input manual, edit pendaftaran, dan formulir registrasi). `asal_sekolah` pada pendaftaran tetap berupa teks nama sekolah.

## Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ untuk POST |

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/spmb/master-sekolah` | Cari sekolah asal (berpaginasi) | ✅ `spmb.manage` atau `spmb.pendaftaran.read` |
| POST | `/api/spmb/master-sekolah` | Tambah sekolah asal (idempoten) | ✅ `spmb.manage` atau `spmb.pendaftaran.create` |

Kedua endpoint terdaftar pada `PUBLIC_PATHS` middleware `CheckMenuAccess` (tidak memerlukan menu), sehingga admin SPMB non-superadmin dan calon mahasiswa dapat memakainya; autentikasi tetap wajib dan izin dicek di controller (`403` bila tidak memiliki izin).

Aksi `POST` dicatat pada audit log (`SPMB` / `master_sekolah_dibuat`, `master_sekolah_alamat_diperbarui`).

## GET `/api/spmb/master-sekolah`

| Query | Tipe | Default | Keterangan |
|---|---|---|---|
| `search` | string | - | Cocok sebagian pada `nama` |
| `page` | integer | 1 | Nomor halaman |
| `per_page` | integer | 15 | Jumlah per halaman, maksimal 100 |
| `sort_order` | `asc` \| `desc` | `asc` | Urutan menurut `nama` (abjad dipilih agar hasil pencarian mudah dipindai) |

**200 OK**
```json
{
    "status": "success",
    "message": "Daftar sekolah berhasil diambil",
    "data": [{ "id": 1, "nama": "SD MALAKA", "alamat": null, "jenis": null }],
    "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1 }
}
```

## POST `/api/spmb/master-sekolah`

Body: `nama` (wajib, maks 255), `alamat` (opsional, maks 1000), `jenis` (opsional, maks 50).

Idempoten: nama yang sama (tanpa membedakan huruf besar/kecil dan spasi ganda) tidak membuat baris baru; `alamat` hanya diisi bila sebelumnya kosong.

**201 Created** (baru) / **200 OK** (sudah ada)
```json
{
    "status": "success",
    "message": "Sekolah berhasil ditambahkan",
    "data": { "id": 1, "nama": "SD MALAKA", "alamat": null, "jenis": null, "created": true }
}
```

## Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

**403 Forbidden** (tidak memiliki `spmb.manage` maupun `spmb.pendaftaran.create`)
```json
{
    "status": "error",
    "message": "Akses ditolak."
}
```

**422 Unprocessable Entity** (validasi gagal, mis. `nama` kosong)
```json
{
    "status": "error",
    "message": "The nama field is required.",
    "errors": { "nama": ["The nama field is required."] }
}
```

**400 Bad Request / 404 Not Found**: tidak berlaku untuk endpoint ini. Tidak ada parameter jalur (`{id}`) yang dapat tidak ditemukan, dan kesalahan input selalu dikembalikan sebagai `422`. Alamat yang tidak dikenal dibalas `404` standar oleh framework.

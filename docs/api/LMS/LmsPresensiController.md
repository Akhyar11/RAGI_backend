# LmsPresensiController

> **Modul**: LMS (standalone, pisah dari SIAKAD)  
> **Base URL**: `/api/v1/lms`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-10-22  
> **Diperbarui**: 2026-10-22

Controller panel administrasi integrasi presensi LMS ↔ aplikasi PresensiV2 (LMS-03).
Menyediakan monitoring rekap kehadiran lintas kelas, jejak sinkronisasi, percobaan sinkron
ulang, tarik riwayat dari PresensiV2, serta export rekap dalam format CSV/XLSX.

Integrasi bersifat **opt-in** dan **fail-safe**: bila PresensiV2 belum dikonfigurasi
(`PRESENSI_API_URL` kosong **dan** koneksi database `presensiv2` tidak diisi), seluruh aksi
sinkronisasi berstatus `skipped`/`422` dan **data lokal LMS tidak berubah**.

Otorisasi memakai permission granular modul LMS:

| Permission | Akses |
|---|---|
| `lms.presensi.read` | Baca status integrasi, daftar kelas, rekap kehadiran, dan log sinkronisasi |
| `lms.presensi.manage` | Sinkron ulang, tarik riwayat, dan export rekap (mewarisi hak `lms.presensi.read`) |

Kedua permission hanya diberikan kepada role `admin` dan `superadmin`.

## Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ untuk POST |

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/lms/admin/presensi/status` | Status integrasi PresensiV2 + feature flag + ringkasan sinkronisasi | ✅ `lms.presensi.read` |
| GET | `/api/v1/lms/admin/presensi/kelas` | Daftar kelas yang memiliki sesi presensi + ringkasan jumlah sesi/rekap/hadir | ✅ `lms.presensi.read` |
| GET | `/api/v1/lms/admin/presensi/rekap` | Rekap kehadiran (satu baris per mahasiswa per pertemuan) | ✅ `lms.presensi.read` |
| GET | `/api/v1/lms/admin/presensi/log` | Jejak percobaan sinkronisasi ke PresensiV2 | ✅ `lms.presensi.read` |
| GET | `/api/v1/lms/admin/presensi/export` | Export rekap kehadiran lintas kelas (CSV/XLSX) | ✅ `lms.presensi.manage` |
| POST | `/api/v1/lms/admin/presensi/sync` | Sinkronkan ulang jurnal & kehadiran ke PresensiV2 | ✅ `lms.presensi.manage` |
| POST | `/api/v1/lms/admin/presensi/tarik` | Tarik riwayat jurnal + rekap dari PresensiV2 ke LMS | ✅ `lms.presensi.manage` |

## Query Parameters

### `GET /admin/presensi/kelas`

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Pencarian `kode_kelas` atau `nama_kelas` |
| `tahun_akademik_id` | integer | ❌ | — | Filter ID tahun akademik |
| `sort_by` | string | ❌ | `kode_kelas` | `kode_kelas`, `nama_kelas`, `jumlah_sesi`, `created_at` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Nomor halaman |

### `GET /admin/presensi/rekap`

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `kelas_id` | integer | ❌ | — | Filter ID kelas |
| `pertemuan_ke` | integer | ❌ | — | Filter urutan pertemuan |
| `status` | string | ❌ | — | Filter status kehadiran (`hadir`, `sakit`, `izin`, `alfa`) |
| `sync_status` | string | ❌ | — | Filter status sinkronisasi (`local`, `pending`, `synced`, `failed`) |
| `search` | string | ❌ | — | Pencarian NIM, nama mahasiswa, atau materi pertemuan |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Nomor halaman |

### `GET /admin/presensi/log`

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `status` | string | ❌ | — | Filter status percobaan sinkronisasi (`success`, `failed`, `skipped`) |
| `kelas_id` | integer | ❌ | — | Filter ID kelas |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Nomor halaman |

### `GET /admin/presensi/export`

Seluruh parameter filter `rekap` di atas berlaku (tanpa pagination). Tambahan:

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `format` | string | ❌ | `csv` | Format export: `csv` atau `xlsx` |

---

## Request Body

### `POST /admin/presensi/sync`

```json
{
  "pertemuan_id": 12
}
```

Atau cakup satu kelas:

```json
{
  "kelas_id": 3
}
```

| Field | Type | Required | Rules |
|---|---|---|---|
| `pertemuan_id` | integer | ❌* | `exists:siakad_pertemuan,id` |
| `kelas_id` | integer | ❌* | `exists:siakad_kelas,id` |

\* Minimal salah satu diisi. Bila keduanya kosong → `422`.

### `POST /admin/presensi/tarik`

```json
{
  "kelas_id": 3
}
```

| Field | Type | Required | Rules |
|---|---|---|---|
| `kelas_id` | integer | ✅ | `exists:siakad_kelas,id` |

---

## Contoh Response

### [GET] `/api/v1/lms/admin/presensi/status` — 200

```json
{
  "status": "success",
  "message": "Status integrasi presensi berhasil diambil.",
  "data": {
    "configured": false,
    "mode": "auto",
    "via": "none",
    "api_url": "",
    "timeout": 15,
    "db_connection": "presensiv2",
    "api_reachable": false,
    "db_reachable": false,
    "message": "Integrasi PresensiV2 belum dikonfigurasi (PRESENSI_API_URL / koneksi database PresensiV2 kosong).",
    "fitur_presensi_aktif": true,
    "total_pertemuan": 16,
    "total_gagal_sync": 0,
    "total_tersinkron": 0,
    "sinkron_terakhir": null
  }
}
```

### [GET] `/api/v1/lms/admin/presensi/kelas` — 200

```json
{
  "status": "success",
  "message": "Daftar kelas presensi berhasil diambil.",
  "data": [
    {
      "id": 3,
      "kode_kelas": "IF-PRES-A",
      "nama_kelas": "Kelas Presensi A",
      "mata_kuliah": {
        "id": 7,
        "nama": "Sistem Informasi Presensi"
      },
      "program_studi": {
        "id": 2,
        "nama": "Informatika",
        "kode_prodi": "IF"
      },
      "jumlah_sesi": 16,
      "jumlah_gagal_sync": 0,
      "jumlah_tersinkron": 0,
      "jumlah_rekap": 320,
      "jumlah_hadir": 298
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 1,
    "last_page": 1,
    "from": 1,
    "to": 1
  },
  "filters": {
    "search": null,
    "sort_by": "kode_kelas",
    "sort_order": "desc",
    "tahun_akademik_id": null
  }
}
```

### [GET] `/api/v1/lms/admin/presensi/rekap` — 200

```json
{
  "status": "success",
  "message": "Rekap kehadiran berhasil diambil.",
  "data": [
    {
      "id": 91,
      "status": "hadir",
      "catatan": null,
      "sync_status": "local",
      "synced_at": null,
      "pertemuan_id": 12,
      "pertemuan_ke": 1,
      "tanggal": "2026-10-01",
      "materi": "Pengantar Presensi",
      "kelas_id": 3,
      "kode_kelas": "IF-PRES-A",
      "nama_kelas": "Kelas Presensi A",
      "nama_mk": "Sistem Informasi Presensi",
      "nim": "2026003001",
      "nama_lengkap": "Mahasiswa Presensi"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 320,
    "last_page": 22,
    "from": 1,
    "to": 15
  },
  "filters": {
    "search": null,
    "kelas_id": 3,
    "pertemuan_ke": null,
    "status": null,
    "sync_status": null
  }
}
```

### [GET] `/api/v1/lms/admin/presensi/log` — 200

```json
{
  "status": "success",
  "message": "Log sinkronisasi presensi berhasil diambil.",
  "data": [
    {
      "id": 5,
      "entitas": "pertemuan",
      "entitas_id": 12,
      "kelas_id": 3,
      "pertemuan_ke": 1,
      "aksi": "buka_jurnal",
      "status": "success",
      "via": "api",
      "message": "Jurnal perkuliahan tersinkron.",
      "payload": null,
      "user_id": 1,
      "created_at": "2026-10-22 09:12:00",
      "updated_at": "2026-10-22 09:12:00",
      "user": {
        "id": 1,
        "name": "Admin LMS",
        "username": "admin.lms"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 5,
    "last_page": 1,
    "from": 1,
    "to": 5
  },
  "filters": {
    "status": null,
    "kelas_id": null
  }
}
```

### [GET] `/api/v1/lms/admin/presensi/export?format=csv` — 200

Header respons:

```http
Content-Type: text/csv; charset=UTF-8
Content-Disposition: attachment; filename=Rekap_Presensi_LMS_20261022_091200.csv
```

Isi (diawali BOM UTF-8 agar Excel di Windows membaca karakter Indonesia dengan benar):

```csv
Kode Kelas,Nama Kelas,Mata Kuliah,Pertemuan Ke,Tanggal,Topik Pertemuan,NIM,Nama Mahasiswa,Status Kehadiran,Catatan,Status Sinkron,Sinkron Terakhir
IF-PRES-A,Kelas Presensi A,Sistem Informasi Presensi,1,2026-10-01,Pengantar Presensi,2026003001,Mahasiswa Presensi,Hadir,-,Hanya di LMS,-
```

### [POST] `/api/v1/lms/admin/presensi/sync` — 200

```json
{
  "status": "success",
  "message": "Sinkronisasi selesai.",
  "data": {
    "success": true,
    "message": "Sinkronisasi selesai.",
    "pertemuan": {
      "12": {
        "success": true,
        "message": "Kehadiran pertemuan 1 tersinkron.",
        "via": "api"
      }
    }
  }
}
```

### [POST] `/api/v1/lms/admin/presensi/tarik` — 200

```json
{
  "status": "success",
  "message": "Riwayat presensi berhasil ditarik dari PresensiV2.",
  "data": {
    "success": true,
    "message": "Riwayat presensi berhasil ditarik dari PresensiV2.",
    "via": "api",
    "jurnal_ditarik": 4,
    "rekap_ditarik": 96
  }
}
```

---

## Error Response

### 401 — Token tidak valid / kedaluwarsa

```json
{
  "status": "error",
  "message": "Unauthenticated."
}
```

### 403 — Tidak memiliki permission

```json
{
  "status": "error",
  "message": "This action is unauthorized."
}
```

### 404 — Kelas / pertemuan tidak ditemukan

```json
{
  "status": "error",
  "message": "Kelas yang dipilih tidak ditemukan."
}
```

### 422 — Validasi gagal

```json
{
  "status": "error",
  "message": "Isi pertemuan_id atau kelas_id untuk menentukan cakupan sinkronisasi.",
  "errors": {
    "pertemuan_id": [
      "Isi pertemuan_id atau kelas_id untuk menentukan cakupan sinkronisasi."
    ]
  }
}
```

### 422 — Integrasi belum dikonfigurasi / sinkronisasi gagal

```json
{
  "status": "error",
  "message": "Integrasi PresensiV2 belum dikonfigurasi.",
  "data": {
    "success": false,
    "message": "Integrasi PresensiV2 belum dikonfigurasi.",
    "pertemuan": []
  }
}
```

---

## Konfigurasi Environment

| Key | Default | Deskripsi |
|---|---|---|
| `PRESENSI_API_URL` | — | Base URL API PresensiV2 (kosong = integrasi nonaktif) |
| `PRESENSI_API_KEY` | — | API key yang dikirim pada header `X-Api-Key` |
| `PRESENSI_SYNC_MODE` | `auto` | Transport sinkronisasi: `auto`, `api`, atau `db` |
| `PRESENSI_DB_HOST` | — | Host MySQL PresensiV2 (wajib untuk transport `db`) |
| `PRESENSI_DB_DATABASE` | — | Nama basis data PresensiV2 (wajib untuk transport `db`) |
| `PRESENSI_ID_PTK_SOURCE` | `nidn` | Sumber `id_ptk`: `nidn` atau `nip` |
| `PRESENSI_RUANG_DEFAULT` | — | Nama ruang cadangan bila kelas tidak memiliki ruang |
| `LMS_FITUR_PRESENSI` | aktif | Feature flag tampilan presensi (disimpan di `core_system_settings`, key `lms_fitur_presensi`) |

> **Feature flag**: ketika `lms_fitur_presensi` dimatikan, dosen & mahasiswa menerima `403`
> pada endpoint presensi. Admin (`lms.presensi.manage`) tetap dapat diakses untuk keperluan support.

---

## Audit Log

| Aksi | Modul | Tabel | Keterangan |
|---|---|---|---|
| `export` | `LMS` | `siakad_absensi_mahasiswa` | Record filter & jumlah baris export rekap |
| `sync_presensi` | `LMS` | `siakad_pertemuan` | Cakupan sinkronisasi (kelas/pertemuan) & hasil |
| `tarik_presensi` | `LMS` | `siakad_kelas` | ID kelas yang ditarik & transport (`via`) |

# PaController

> **Modul**: SIAKAD / **Base URL**: `/api/v1/siakad/bimbingan` / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat**: 2026-09-26 / **Diperbarui**: 2026-10-20

Manajemen Pembimbing Akademik (PA): rekap bimbingan dosen, penugasan mahasiswa bimbingan, pencatatan log sesi jurnal per pertemuan, pelaporan berkala per kelas/angkatan (Model SIMPA), dan cetak laporan PDF resmi.

### Hak Akses (Gate / Permission)

Seluruh endpoint di bawah ini dilindungi `Gate::authorize` (bukan sekadar autentikasi). Permission granular bimbingan PA (`siakad.pa.read` / `siakad.pa.create` / `siakad.pa.update` / `siakad.pa.delete`) memastikan **Dosen PA dapat menulis catatan bimbingannya** (sebelumnya terkunci karena Gate lama menuntut `siakad.krs.approve` yang tidak dimiliki role dosen).

| Gate | Permission utama | Fallback (kompatibilitas) | Diberikan ke role |
|---|---|---|---|
| `siakad.pa.read` | `siakad.pa.read` | `siakad.mahasiswa.read`, `siakad.krs.read`, `siakad.pa.manage` | dosen, mahasiswa, admin, admin_siakad |
| `siakad.pa.create` | `siakad.pa.create` | `siakad.krs.create`, `siakad.krs.approve`, `siakad.mahasiswa.manage` | dosen, mahasiswa, admin, admin_siakad |
| `siakad.pa.update` | `siakad.pa.update` | `siakad.krs.approve`, `siakad.mahasiswa.manage` | dosen, admin, admin_siakad |
| `siakad.pa.delete` | `siakad.pa.delete` | `siakad.krs.approve`, `siakad.mahasiswa.manage` | dosen, admin, admin_siakad |
| `siakad.pa.catatan.create` | `siakad.pa.create` | `siakad.krs.approve`, `siakad.mahasiswa.manage`, `siakad.krs.create` | dosen, mahasiswa, admin, admin_siakad |

> `admin` & `superadmin` mendapat seluruh permission otomatis via `PermissionSeeder` (auto-all). Kewenangan **Kaprodi & Wakil Prodi bersifat struktural** (dinamis berbasis penugasan prodi / `Gate::before`), bukan role statis. Pengecekan *ownership* (milik PA sendiri) tetap berlaku di sisi controller.

## Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ untuk POST/PUT/PATCH |

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/siakad/bimbingan/rekap` | Rekapitulasi bimbingan seluruh PA / dosen bersangkutan | ✅ (scoping `PaService`) |
| GET | `/api/v1/siakad/bimbingan/advisees` | Daftar mahasiswa bimbingan PA + riwayat sesi log (paginated) | ✅ `siakad.mahasiswa.read` |
| GET | `/api/v1/siakad/bimbingan/kehadiran` | Monitoring kehadiran bimbingan (persentase, status, flag kritis) | ✅ `siakad.pa.read` |
| GET | `/api/v1/siakad/bimbingan/prestasi` | Monitoring prestasi: trend IPS per semester + deteksi IPS menurun | ✅ `siakad.pa.read` |
| GET | `/api/v1/siakad/bimbingan/risiko` | Skor risiko "cuti/berhenti" per advisee (sinyal + band) | ✅ `siakad.pa.read` |
| GET | `/api/v1/siakad/bimbingan/alerts` | Ringkasan "Perlu Perhatian" (advisees berisiko, urut skor) | ✅ `siakad.pa.read` |
| GET | `/api/v1/siakad/bimbingan/jadwal` | Daftar slot jadwal pertemuan/konsultasi PA dosen | ✅ `siakad.pa.read` |
| POST | `/api/v1/siakad/bimbingan/jadwal` | Buat slot jadwal pertemuan/konsultasi PA | ✅ `siakad.pa.create` |
| PUT | `/api/v1/siakad/bimbingan/jadwal/{id}` | Perbarui slot jadwal PA | ✅ `siakad.pa.update` |
| DELETE | `/api/v1/siakad/bimbingan/jadwal/{id}` | Hapus slot jadwal PA | ✅ `siakad.pa.delete` |
| GET | `/api/v1/siakad/bimbingan/booking` | Daftar pengajuan janji konsultasi pada jadwal dosen | ✅ `siakad.pa.read` |
| PATCH | `/api/v1/siakad/bimbingan/booking/{id}/proses` | Proses pengajuan konsultasi (setujui/tolak/selesai) | ✅ `siakad.pa.update` |
| GET | `/api/v1/siakad/bimbingan/my-jadwal` | Jadwal PA + riwayat pengajuan milik mahasiswa login | ✅ `siakad.pa.read` |
| POST | `/api/v1/siakad/bimbingan/jadwal/{jadwalId}/ajukan` | Mahasiswa mengajukan janji konsultasi | ✅ `siakad.pa.create` |
| GET | `/api/v1/siakad/bimbingan/pkl` | Daftar status PKL/PL/Magang mahasiswa bimbingan | ✅ `siakad.pa.read` |
| POST | `/api/v1/siakad/bimbingan/pkl` | Catat status PKL/PL/Magang | ✅ `siakad.pa.create` |
| PUT | `/api/v1/siakad/bimbingan/pkl/{id}` | Perbarui status PKL/PL/Magang | ✅ `siakad.pa.update` |
| DELETE | `/api/v1/siakad/bimbingan/pkl/{id}` | Hapus status PKL/PL/Magang | ✅ `siakad.pa.delete` |
| GET | `/api/v1/siakad/bimbingan/insiden` | Daftar laporan insiden (kekerasan/pelecehan), anonim disamarkan | ✅ `siakad.pa.read` |
| POST | `/api/v1/siakad/bimbingan/insiden` | Kirim laporan insiden (mendukung anonim) | ✅ `siakad.pa.create` |
| PATCH | `/api/v1/siakad/bimbingan/insiden/{id}/eskalasi` | Eskalasi laporan ke Kaprodi/Kemahasiswaan/Satgas PPKS | ✅ `siakad.pa.update` |
| PATCH | `/api/v1/siakad/bimbingan/insiden/{id}/konseling` | Tandai rujukan konseling korban/pelapor | ✅ `siakad.pa.update` |
| PUT | `/api/v1/siakad/bimbingan/insiden/{id}` | Perbarui status & tindak lanjut insiden | ✅ `siakad.pa.update` |
| GET | `/api/v1/siakad/bimbingan/laporan-bulanan` | Laporan monitoring bulanan PA (agregat) | ✅ `siakad.pa.read` |
| GET | `/api/v1/siakad/bimbingan/my-pa` | Data dosen PA mahasiswa login beserta riwayat catatan & KRS aktif | ✅ `siakad.pa.read` |
| GET | `/api/v1/siakad/bimbingan/catatan` | Daftar catatan bimbingan per mahasiswa | ✅ `siakad.pa.read` |
| POST | `/api/v1/siakad/bimbingan/catatan` | Simpan catatan bimbingan baru | ✅ `siakad.pa.catatan.create` |
| PUT | `/api/v1/siakad/bimbingan/catatan/{id}` | Perbarui catatan bimbingan | ✅ `siakad.pa.update` |
| DELETE | `/api/v1/siakad/bimbingan/catatan/{id}` | Hapus catatan bimbingan | ✅ `siakad.pa.delete` |
| GET | `/api/v1/siakad/bimbingan/laporan` | Ambil laporan akhir PA per tahun akademik | ✅ `siakad.pa.read` |
| POST | `/api/v1/siakad/bimbingan/laporan` | Simpan/finalisasi laporan PA | ✅ `siakad.pa.create` |
| GET | `/api/v1/siakad/bimbingan/aktivitas` | Daftar aktivitas bimbingan PA per kelas (SIMPA) | ✅ `siakad.pa.read` |
| GET | `/api/v1/siakad/bimbingan/aktivitas/komposisi` | Hitung komposisi kelas (A/N/C/K) dinamis dari DB BAAK | ✅ `siakad.pa.read` |
| POST | `/api/v1/siakad/bimbingan/aktivitas` | Simpan / perbarui sesi aktivitas bimbingan PA | ✅ `siakad.pa.create` |
| DELETE | `/api/v1/siakad/bimbingan/aktivitas/{id}` | Hapus sesi aktivitas bimbingan PA | ✅ `siakad.pa.delete` |
| GET | `/api/v1/siakad/bimbingan/aktivitas/cetak` | Cetak laporan resmi aktivitas bimbingan PDF | ✅ `siakad.pa.read` |

---

## Query Parameters Pagination (Baku — Berlaku untuk Semua Endpoint List)

Seluruh endpoint list (kehadiran, prestasi, risiko, alerts, jadwal, booking, pkl, insiden, catatan, aktivitas) memakai paginasi baku berikut:

| Parameter | Tipe | Default | Keterangan |
|---|---|---|---|
| `page` | integer | `1` | Nomor halaman |
| `per_page` | integer | `15` | Jumlah item per halaman (maksimum `100`) |
| `sort_by` | string | lihat per-endpoint | Kolom pengurutan (whitelist per endpoint) |
| `sort_order` | string | `desc` | Arah pengurutan: `asc` atau `desc` |

Struktur `meta` paginasi pada respons sukses (200) selalu:
```json
{
  "status": "success",
  "message": "...",
  "data": [ { "...": "..." } ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 42,
    "last_page": 3,
    "from": 1,
    "to": 15,
    "sort_by": "created_at",
    "sort_order": "desc"
  }
}
```

## Standar Respons Error (Berlaku Umum)

Semua endpoint menggunakan envelope error seragam berikut (tanpa `data`):

| Kode | Skenario | Contoh Body |
|---|---|---|
| 400 | Precondition gagal (mis. jadwal ditutup) | `{"status":"error","message":"Jadwal sudah ditutup."}` |
| 401 | Token tidak ada/kedaluwarsa | `{"status":"error","message":"Unauthenticated."}` |
| 403 | Tanpa izin / bukan pemilik data | `{"status":"error","message":"Jadwal ini milik PA lain."}` |
| 404 | Data tidak ditemukan | `{"status":"error","message":"Data dosen tidak ditemukan untuk akun ini."}` |
| 422 | Validasi gagal | `{"status":"error","message":"The given data was invalid.","errors":{"jenis":["Jenis insiden tidak valid."]}}` |
| 500 | Kesalahan server | `{"status":"error","message":"Terjadi kesalahan pada server."}` |

---

## [GET] /api/v1/siakad/bimbingan/rekap

Rekapitulasi per dosen PA: komposisi status mahasiswa bimbingan (point-in-time) +
aktivitas sesi bimbingan. Aktivitas (`total_bimbingan`, `butuh_khusus_aktif`,
`terakhir_bimbingan_at`) dapat dibatasi rentang tanggal.

#### Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `dosen_id` | integer | ❌ | — | Hanya dosen tertentu (kaprodi/admin) |
| `program_studi_id` | integer | ❌ | — | Filter homebase prodi dosen |
| `dari_tanggal` | date | ❌ | — | Batas awal aktivitas (`YYYY-MM-DD`) |
| `sampai_tanggal` | date | ❌ | — | Batas akhir aktivitas (`YYYY-MM-DD`) |
| `search` | string | ❌ | — | Pencarian nama dosen atau NIDN |
| `sort_by` | string | ❌ | `nama_lengkap` | Kolom pengurutan (`nama_lengkap`, `nidn`) |
| `sort_order` | string | ❌ | `asc` | Arah pengurutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah rekaman data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Nomor halaman yang diminta |

**200 OK**
```json
{
    "status": "success",
    "message": "Rekap bimbingan PA berhasil dimuat",
    "data": [
        {
            "dosen_id": 3,
            "nama_lengkap": "Dr. Budi Santoso",
            "nidn": "0011223344",
            "program_studi": "Informatika",
            "komposisi": { "aktif": 12, "cuti": 1, "mangkir": 0, "keluar": 0, "lulus": 0, "total": 13 },
            "butuh_khusus_aktif": 1,
            "total_bimbingan": 8,
            "terakhir_bimbingan_at": "2026-09-20",
            "belum_bimbingan": false
        }
    ]
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
    "message": "Anda tidak memiliki izin untuk melihat data rekap ini."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "The given data was invalid.",
    "errors": {
        "dari_tanggal": [
            "Format tanggal dari_tanggal tidak valid."
        ],
        "dosen_id": [
            "Dosen tidak ditemukan."
        ]
    }
}
```

> Catatan: filter tanggal hanya membatasi agregat aktivitas sesi
> (`tanggal_bimbingan`, fallback `created_at` bila kosong); komposisi status
> mahasiswa selalu kondisi terkini.

---

## [GET] /api/v1/siakad/bimbingan/advisees

Mengambil daftar mahasiswa bimbingan PA beserta rekap catatan dan status KRS (paginated).

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `dosen_id` | integer | ❌ | — | ID Dosen PA (otomatis diambil dari user login jika role dosen) |
| `status_mahasiswa` | string | ❌ | — | Filter status (aktif, cuti, mangkir, lulus, keluar) |
| `search` | string | ❌ | — | Pencarian nama atau NIM mahasiswa |
| `per_page` | integer | ❌ | 15 | Jumlah data per halaman |
| `page` | integer | ❌ | 1 | Nomor halaman |

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Daftar mahasiswa bimbingan PA berhasil dimuat",
    "data": [
        {
            "id": 14,
            "nim": "202401001",
            "nama_lengkap": "Ahmad Dani",
            "telepon": "081234567890",
            "email": "ahmad.dani@kampus.ac.id",
            "angkatan": "2024",
            "status": "aktif",
            "program_studi": "Teknik Informatika",
            "total_catatan": 2,
            "butuh_khusus": false
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1,
        "from": 1,
        "to": 1
    }
}
```

---

## [GET] /api/v1/siakad/bimbingan/kehadiran

> Monitoring kehadiran bimbingan per mahasiswa: total pertemuan (dari KRS detail aktif), jumlah per status kehadiran, persentase hadir, kelayakan UAS, dan flag kehadiran kritis (< 75%).

**Query Opsional**: `dosen_id` (int) — hanya untuk kaprodi/wakil/admin/superadmin; dosen murni selalu melihat bimbingannya sendiri.

**Query Pagination**: `page`, `per_page`, `sort_by` (`nama_lengkap`|`nim`|`persentase_hadir`|`flag_kritis`), `sort_order` (`asc`|`desc`).

Respons `200`:
```json
{
  "status": "success",
  "message": "Monitoring kehadiran bimbingan berhasil dimuat",
  "data": [
    {
      "id": 14, "nim": "202401001", "nama_lengkap": "Ahmad Dani", "status": "aktif",
      "total_pertemuan": 14, "hadir": 9, "sakit": 1, "izin": 2, "alfa": 2,
      "persentase_hadir": 64.3, "eligible_uas": false, "kehadiran_kritis": true
    }
  ],
  "meta": {
    "current_page": 1, "per_page": 15, "total": 1, "last_page": 1,
    "from": 1, "to": 1, "sort_by": "nama_lengkap", "sort_order": "desc", "dosen_id": 3
  }
}
```

## [GET] /api/v1/siakad/bimbingan/prestasi

> Monitoring prestasi: trend IPS per semester (dari `siakad_khs`) + deteksi IPS menurun dan IPS rendah (< 2.00).

**Query Opsional**: `dosen_id` (int) — sama seperti endpoint kehadiran.
**Query Pagination**: `page`, `per_page`, `sort_by` (`nama_lengkap`|`nim`), `sort_order` (`asc`|`desc`).

Respons `200`:
```json
{
  "status": "success",
  "message": "Monitoring prestasi bimbingan berhasil dimuat",
  "data": [
    {
      "id": 14, "nim": "202401001", "nama_lengkap": "Ahmad Dani", "ipk": 3.25,
      "ips_terakhir": 3.0, "ips_sebelumnya": 3.5, "ips_menurun": true, "ips_rendah": false,
      "ips_trend": [
        { "tahun_akademik_id": 1, "kode": "20251", "ips": 3.5, "total_sks_semester": 20 },
        { "tahun_akademik_id": 2, "kode": "20252", "ips": 3.0, "total_sks_semester": 21 }
      ]
    }
  ],
  "meta": {
    "current_page": 1, "per_page": 15, "total": 1, "last_page": 1,
    "from": 1, "to": 1, "sort_by": "nama_lengkap", "sort_order": "desc", "dosen_id": 3
  }
}
```

## [GET] /api/v1/siakad/bimbingan/risiko

> Skor risiko "cuti/berhenti" per advisee. Sinyal yang diperiksa: kehadiran kritis, IPS menurun, IPS/IPK rendah, status tidak aktif, KRS belum disetujui, dan penanganan khusus terbuka. `skor` = jumlah sinyal aktif; `band_risiko` = `tinggi` (≥3), `sedang` (1–2), `rendah` (0).

**Query Pagination**: `page`, `per_page`, `sort_by` (`nama_lengkap`|`nim`|`skor`), `sort_order` (`asc`|`desc`).

Respons `200`:
```json
{
  "status": "success",
  "message": "Skor risiko bimbingan berhasil dimuat",
  "data": [
    {
      "id": 14, "nim": "202401001", "nama_lengkap": "Ahmad Dani", "status": "aktif", "ipk": 3.25,
      "persentase_hadir": 64.3, "ips_terakhir": 3.0, "ips_menurun": true,
      "sinyal": [
        { "kode": "kehadiran_kritis", "label": "Kehadiran di bawah 75.0%" },
        { "kode": "ips_menurun", "label": "IPS semester terakhir menurun" }
      ],
      "skor": 2, "band_risiko": "sedang"
    }
  ],
  "meta": {
    "current_page": 1, "per_page": 15, "total": 1, "last_page": 1,
    "from": 1, "to": 1, "sort_by": "skor", "sort_order": "desc", "dosen_id": 3
  }
}
```

## [GET] /api/v1/siakad/bimbingan/alerts

> Ringkasan "Perlu Perhatian": advisees dengan skor risiko ≥ sedang atau status tidak aktif, diurutkan skor tertinggi. `summary` berisi hitungan ringkas untuk kartu dashboard.

Respons `200`:
```json
{
  "status": "success",
  "message": "Ringkasan perlu perhatian berhasil dimuat",
  "data": [ { "...": "objek sama seperti respons risiko" } ],
  "summary": {
    "total_advisees": 20, "risiko_tinggi": 2, "risiko_sedang": 3,
    "kehadiran_kritis": 4, "perlu_perhatian": 5
  },
  "meta": {
    "current_page": 1, "per_page": 15, "total": 5, "last_page": 1,
    "from": 1, "to": 5, "sort_by": null, "sort_order": "desc", "dosen_id": 3
  }
}
```

---

## Fase 2 — Jadwal & Konsultasi PA

### [GET] /api/v1/siakad/bimbingan/jadwal

> Daftar slot jadwal pertemuan/konsultasi PA milik dosen (scope). Kaprodi/admin boleh memilih `dosen_id`; dosen murni selalu dirinya sendiri.

**Query Opsional**: `dosen_id` (int), `dari` (date), `sampai` (date).
**Query Pagination**: `page`, `per_page`, `sort_by` (`tanggal`|`jam_mulai`|`status`), `sort_order` (`asc`|`desc`).

Respons `200`:
```json
{
  "status": "success",
  "message": "Jadwal bimbingan PA berhasil dimuat",
  "data": [
    {
      "id": 1, "dosen_id": 3, "tahun_akademik_id": 2,
      "tanggal": "2026-10-25", "jam_mulai": "09:00", "jam_selesai": "10:00",
      "mode": "tatap_muka", "lokasi": "R. 201", "kuota": 5,
      "catatan": "Bimbingan KRS", "status": "dibuka", "bookings_count": 2
    }
  ],
  "meta": {
    "current_page": 1, "per_page": 15, "total": 1, "last_page": 1,
    "from": 1, "to": 1, "sort_by": "tanggal", "sort_order": "desc", "dosen_id": 3
  }
}
```

Respons Error:
```json
{ "status": "error", "message": "Data dosen tidak ditemukan untuk akun ini." }
```
Kode `403` bila bukan pemilik: `{"status":"error","message":"Jadwal ini milik PA lain."}`.

### [POST] /api/v1/siakad/bimbingan/jadwal

Request body:
```json
{
  "tanggal": "2026-10-25", "jam_mulai": "09:00", "jam_selesai": "10:00",
  "mode": "tatap_muka", "lokasi": "R. 201", "kuota": 5, "catatan": "Bimbingan KRS"
}
```
> `mode` & `status` divalidasi via master referensi (`pa_jadwal_mode`, `pa_jadwal_status`) — bukan enum statis.

Respons `201`:
```json
{ "status": "success", "message": "Jadwal bimbingan PA berhasil dibuat", "data": { "id": 1, "status": "dibuka" } }
```

Respons Error (422 Unprocessable Entity):
```json
{ "status": "error", "message": "The given data was invalid.", "errors": { "jam_selesai": ["The jam selesai must be a date after jam mulai."] } }
```

### [PUT] /api/v1/siakad/bimbingan/jadwal/{id}

Perbarui slot jadwal. Hanya dosen pemilik jadwal atau privileged.

Request body (parsial):
```json
{ "kuota": 10, "status": "ditutup" }
```
Respons `200`:
```json
{ "status": "success", "message": "Jadwal bimbingan PA berhasil diperbarui", "data": { "id": 1, "kuota": 10, "status": "ditutup" } }
```
Respons Error: `403` `"Jadwal ini milik PA lain."`, `404` `"No query results for model [App\\Models\\Siakad\\PaJadwal] 999."`.

### [DELETE] /api/v1/siakad/bimbingan/jadwal/{id}

Hapus slot jadwal. Hanya dosen pemilik jadwal atau privileged.
Respons `200`:
```json
{ "status": "success", "message": "Jadwal bimbingan PA berhasil dihapus", "data": null }
```

### [GET] /api/v1/siakad/bimbingan/booking

> Daftar pengajuan janji konsultasi pada seluruh jadwal seorang dosen (scope). Filter opsional `status` (`pa_booking_status`).

**Query Pagination**: `page`, `per_page`, `sort_by` (`status`|`created_at`), `sort_order` (`asc`|`desc`).

Respons `200`:
```json
{
  "status": "success",
  "message": "Daftar pengajuan konsultasi berhasil dimuat",
  "data": [
    {
      "id": 4, "jadwal_id": 1, "mahasiswa_id": 14, "topik": "Konsultasi KRS",
      "status": "diajukan", "catatan_mahasiswa": "Ingin tambah 2 SKS",
      "catatan_dosen": null, "diproses_pada": null,
      "mahasiswa": { "id": 14, "nim": "202401001", "nama_lengkap": "Ahmad Dani" }
    }
  ],
  "meta": {
    "current_page": 1, "per_page": 15, "total": 1, "last_page": 1,
    "from": 1, "to": 1, "sort_by": "created_at", "sort_order": "desc", "dosen_id": 3
  }
}
```

### [PATCH] /api/v1/siakad/bimbingan/booking/{id}/proses

Request body:
```json
{ "status": "disetujui", "catatan_dosen": "Hadir tepat waktu ya." }
```
> `status` divalidasi via `pa_booking_status`. Hanya dosen pemilik jadwal.

### [GET] /api/v1/siakad/bimbingan/my-jadwal

> Mahasiswa: jadwal PA-nya + riwayat pengajuan pribadi.
```json
{
  "status": "success",
  "message": "Jadwal konsultasi PA berhasil dimuat",
  "data": { "jadwal": [ "..." ], "riwayat": [ "..." ] }
}
```

### [POST] /api/v1/siakad/bimbingan/jadwal/{jadwalId}/ajukan

Mahasiswa mengajukan janji pada slot jadwal Dosen PA-nya. Ditolak `422` bila slot ditutup, kuota penuh, atau sudah pernah mengajukan. `403` bila jadwal bukan milik Dosen PA mahasiswa.

Request body:
```json
{ "topik": "Konsultasi rencana studi", "catatan_mahasiswa": "Butuh saran SKS" }
```

---

## Fase 2 — Status PKL / PL / Magang

### [GET] /api/v1/siakad/bimbingan/pkl

> Daftar status PKL/PL/Magang mahasiswa bimbingan (scope dosen).

**Query Pagination**: `page`, `per_page`, `sort_by` (`status`|`created_at`), `sort_order` (`asc`|`desc`).

Respons `200`:
```json
{
  "status": "success",
  "message": "Status PKL/PL/Magang bimbingan berhasil dimuat",
  "data": [
    {
      "id": 2, "mahasiswa_id": 14, "jenis": "magang", "instansi": "PT Teknologi Nusantara",
      "lokasi": "Jakarta", "pembimbing_lapangan": "Bpk. Andi",
      "tanggal_mulai": "2026-07-01", "tanggal_selesai": "2026-12-31",
      "status": "berjalan", "catatan": "",
      "mahasiswa": { "id": 14, "nim": "202401001", "nama_lengkap": "Ahmad Dani" }
    }
  ],
  "meta": {
    "current_page": 1, "per_page": 15, "total": 1, "last_page": 1,
    "from": 1, "to": 1, "sort_by": "created_at", "sort_order": "desc", "dosen_id": 3
  }
}
```

### [POST] /api/v1/siakad/bimbingan/pkl

Request body:
```json
{
  "mahasiswa_id": 14, "jenis": "magang", "instansi": "PT Teknologi Nusantara",
  "lokasi": "Jakarta", "pembimbing_lapangan": "Bpk. Andi",
  "tanggal_mulai": "2026-07-01", "tanggal_selesai": "2026-12-31", "status": "rencana"
}
```
> `jenis` (`pa_pkl_jenis`) & `status` (`pa_pkl_status`) via master referensi. Dosen murni hanya boleh mencatat mahasiswa bimbingannya (`403` bila bukan).

Respons `201`:
```json
{ "status": "success", "message": "Status PKL/PL/Magang berhasil dicatat", "data": { "id": 2, "jenis": "magang", "status": "rencana" } }
```

### [PUT] /api/v1/siakad/bimbingan/pkl/{id}

Perbarui catatan PKL. Hanya dosen pemilik atau privileged.

Request body (parsial):
```json
{ "status": "selesai" }
```
Respons `200`:
```json
{ "status": "success", "message": "Status PKL/PL/Magang berhasil diperbarui", "data": { "id": 2, "status": "selesai" } }
```
Respons Error: `403` `"Data PKL ini bukan bimbingan Anda."`, `404` bila data tidak ada.

### [DELETE] /api/v1/siakad/bimbingan/pkl/{id}

Hapus catatan PKL. Hanya dosen pemilik atau privileged.
Respons `200`:
```json
{ "status": "success", "message": "Status PKL/PL/Magang berhasil dihapus", "data": null }
```
Respons Error: `403` `"Data PKL ini bukan bimbingan Anda."`, `404` bila data tidak ada.

---

## Fase 2 — Insiden (Kekerasan/Pelecehan), Konseling & Eskalasi

### [GET] /api/v1/siakad/bimbingan/insiden

> Daftar laporan insiden. Privileged (kaprodi/wakil/admin/superadmin) melihat semua; dosen dibatasi laporan penanganannya. Baris **anonim** disamarkan (`mahasiswa_id`, `dosen_id`, `pelapor_user_id` → `null`). Filter opsional `status` (`pa_insiden_status`) & `jenis` (`pa_insiden_jenis`).

**Query Pagination**: `page`, `per_page`, `sort_by` (`status`|`created_at`), `sort_order` (`asc`|`desc`).

Respons `200`:
```json
{
  "status": "success",
  "message": "Daftar laporan insiden berhasil dimuat",
  "data": [
    {
      "id": 7, "mahasiswa_id": null, "dosen_id": 3, "anonim": true,
      "jenis": "pelecehan_seksual", "tingkat": "berat",
      "deskripsi": "…", "tanggal_kejadian": "2026-10-05", "lokasi": "Area parkir",
      "status": "dilaporkan", "eskalasi_tujuan": null,
      "rujukan_konseling": false, "konseling_ke": null, "tindak_lanjut": null
    }
  ],
  "meta": {
    "current_page": 1, "per_page": 15, "total": 1, "last_page": 1,
    "from": 1, "to": 1, "sort_by": "created_at", "sort_order": "desc"
  }
}
```

### [POST] /api/v1/siakad/bimbingan/insiden

Request body:
```json
{
  "jenis": "pelecehan_seksual", "tingkat": "berat",
  "deskripsi": "Kronologi kejadian…", "tanggal_kejadian": "2026-10-05",
  "lokasi": "Area parkir", "anonim": true
}
```
> Bila `anonim: true`, identitas pelapor tidak disimpan. Mahasiswa hanya boleh melapor untuk dirinya. `jenis` divalidasi via `pa_insiden_jenis`. Status awal `dilaporkan`.

### [PATCH] /api/v1/siakad/bimbingan/insiden/{id}/eskalasi

Eskalasi ke unit tujuan (Kaprodi/Kemahasiswaan/Satgas PPKS/Dekan/Rektorat). `eskalasi_tujuan` divalidasi via `pa_eskalasi_tujuan`. Status otomatis naik `dilaporkan → diverifikasi`.

Request body:
```json
{ "eskalasi_tujuan": "satgas_ppks", "catatan": "Sudah dikoordinasikan dengan Satgas PPKS." }
```

### [PATCH] /api/v1/siakad/bimbingan/insiden/{id}/konseling

Tandai rujukan konseling. `konseling_ke` wajib (unit/konselor). Status otomatis naik menjadi `ditangani` bila belum.

Request body:
```json
{ "konseling_ke": "Unit Layanan Konseling", "catatan": "Sesi pertama dijadwalkan." }
```

### [PUT] /api/v1/siakad/bimbingan/insiden/{id}

Perbarui `status` (`pa_insiden_status`), `tingkat`, dan `tindak_lanjut`.

---

## Fase 2 — Laporan Monitoring Bulanan

### [GET] /api/v1/siakad/bimbingan/laporan-bulanan

> Ringkasan monitoring bulanan seorang Dosen PA: komposisi risiko, kehadiran kritis, status PKL, aktivitas konsultasi, dan rekap insiden pada bulan tersebut. Agregasi memakai `PaMonitoringService` (konsisten dengan monitoring harian).

**Query Wajib**: `bulan` (format `YYYY-MM`). **Query Opsional**: `dosen_id`.

Respons `200`:
```json
{
  "status": "success",
  "message": "Laporan monitoring bulanan berhasil dimuat",
  "data": {
    "periode": { "bulan": "2026-10", "dari": "2026-10-01", "sampai": "2026-10-31" },
    "dosen_id": 3,
    "kehadiran": { "total_advisees": 20, "kehadiran_kritis": 4, "rata_persentase_hadir": 82.4 },
    "risiko": {
      "tinggi": 2, "sedang": 3, "rendah": 15,
      "daftar_perlu_perhatian": [
        { "mahasiswa_id": 14, "nim": "202401001", "nama_lengkap": "Ahmad Dani",
          "skor": 2, "band_risiko": "sedang", "sinyal": [ { "kode": "ips_menurun", "label": "…" } ] }
      ]
    },
    "konsultasi": { "total_pengajuan": 5, "disetujui": 3, "menunggu": 2 },
    "pkl": { "total": 4, "berjalan": 3 },
    "insiden": { "total": 1, "keluar_eskalasi": 1, "rujukan_konseling": 1, "belum_selesai": 0 }
  }
}
```

---

## [GET] /api/v1/siakad/bimbingan/my-pa

Mengambil data dosen PA bagi mahasiswa yang sedang login beserta riwayat catatan bimbingan dan KRS semester aktif.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Data dosen PA berhasil dimuat",
    "data": {
        "mahasiswa": {
            "id": 14,
            "nim": "202401001",
            "nama_lengkap": "Ahmad Dani",
            "angkatan": "2024",
            "program_studi": "Teknik Informatika"
        },
        "dosen_pa": {
            "id": 5,
            "nama_lengkap": "Dr. Ir. Hendra Gunawan",
            "nidn": "0011223344",
            "nip": "198001012005011001",
            "email": "hendra@kampus.ac.id",
            "telepon": "081234567890",
            "program_studi": "Teknik Informatika",
            "jabatan_akademik": "Lektor Kepala"
        },
        "krs_aktif": {
            "id": 22,
            "status": "disetujui",
            "total_sks": 20
        },
        "catatan": [],
        "total_bimbingan": 0
    }
}
```

### Response Error
**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data mahasiswa tidak ditemukan untuk akun ini."
}
```

---

## [GET] /api/v1/siakad/bimbingan/catatan

Mengambil daftar riwayat catatan bimbingan untuk mahasiswa tertentu.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Data catatan bimbingan berhasil diambil.",
    "data": [
        {
            "id": 1,
            "mahasiswa_id": 14,
            "dosen_id": 5,
            "tanggal_bimbingan": "2026-10-01",
            "kategori": "akademik",
            "isi": "Konsultasi pengambilan sks semester ganjil.",
            "kesimpulan": "Disetujui 20 SKS.",
            "butuh_penanganan_khusus": false
        }
    ]
}
```

### Response Error (401 / 403)
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

---

## [POST] /api/v1/siakad/bimbingan/catatan

Menyimpan catatan bimbingan baru untuk mahasiswa bimbingan.

### Request Body
```json
{
    "mahasiswa_id": 14,
    "tanggal_bimbingan": "2026-10-01",
    "kategori": "akademik",
    "isi": "Konsultasi rencana studi.",
    "kesimpulan": "Fokus pada mata kuliah prasyarat.",
    "butuh_penanganan_khusus": false
}
```

### Response Sukses (201 Created)
```json
{
    "status": "success",
    "message": "Catatan bimbingan berhasil disimpan.",
    "data": {
        "id": 1,
        "mahasiswa_id": 14,
        "dosen_id": 5,
        "tanggal_bimbingan": "2026-10-01",
        "kategori": "akademik",
        "isi": "Konsultasi rencana studi.",
        "kesimpulan": "Fokus pada mata kuliah prasyarat.",
        "butuh_penanganan_khusus": false
    }
}
```

### Response Error (422 Unprocessable Entity)
```json
{
    "status": "error",
    "message": "Validation failed",
    "errors": {
        "isi": ["The isi field is required."]
    }
}
```

---

## [PUT] /api/v1/siakad/bimbingan/catatan/{id}

Memperbarui isi catatan bimbingan yang telah dicatat sebelumnya.

### Request Body
```json
{
    "tanggal_bimbingan": "2026-10-01",
    "kategori": "akademik",
    "isi": "Revisi konsultasi rencana studi.",
    "kesimpulan": "Disetujui 22 SKS.",
    "butuh_penanganan_khusus": false
}
```

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Catatan bimbingan berhasil diperbarui.",
    "data": {
        "id": 1,
        "mahasiswa_id": 14,
        "dosen_id": 5,
        "tanggal_bimbingan": "2026-10-01",
        "kategori": "akademik",
        "isi": "Revisi konsultasi rencana studi.",
        "kesimpulan": "Disetujui 22 SKS.",
        "butuh_penanganan_khusus": false
    }
}
```

### Response Error (403 Forbidden / 404 Not Found)
```json
{
    "status": "error",
    "message": "Catatan ini milik PA lain."
}
```

---

## [DELETE] /api/v1/siakad/bimbingan/catatan/{id}

Menghapus catatan bimbingan PA.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Catatan bimbingan berhasil dihapus.",
    "data": null
}
```

### Response Error (403 Forbidden / 404 Not Found)
```json
{
    "status": "error",
    "message": "Catatan ini milik PA lain."
}
```

## [GET] /api/v1/siakad/bimbingan/aktivitas

Mengambil riwayat sesi aktivitas bimbingan PA yang dicatat per kelas dan per tanggal.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tahun_akademik_id` | integer | ❌ | — | Filter ID Tahun Akademik |
| `kelas` | string | ❌ | — | Filter kelas atau angkatan bimbingan |
| `dosen_id` | integer | ❌ | — | ID Dosen (otomatis diambil dari user login jika role dosen) |

### Response Sukses (200 OK)

```json
{
  "success": true,
  "message": "Daftar aktivitas bimbingan PA berhasil diambil",
  "data": [
    {
      "id": 1,
      "dosen_id": 2,
      "tahun_akademik_id": 1,
      "kelas": "Angkatan 2024",
      "tanggal": "2026-09-26",
      "pertemuan_ke": 1,
      "mhs_aktif": 3,
      "mhs_nonaktif": 0,
      "mhs_cuti": 0,
      "mhs_keluar": 0,
      "kondisi_mahasiswa": "Mahasiswa aktif mengikuti perkuliahan",
      "penanganan_mahasiswa": "Tidak ada kendala khusus",
      "kesimpulan": "Perkuliahan semester berjalan lancar",
      "status": "final"
    }
  ]
}
```

---

## [GET] /api/v1/siakad/bimbingan/aktivitas/komposisi

Menghitung statistik komposisi mahasiswa (Aktif, Non-Aktif/Mangkir, Cuti, Keluar/Lulus) secara dinamis dari tabel `siakad_mahasiswa` tanpa hardcode.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `kelas` | string | ❌ | — | Filter kelas atau angkatan bimbingan |
| `dosen_id` | integer | ❌ | — | ID Dosen |

### Response Sukses (200 OK)

```json
{
  "success": true,
  "message": "Komposisi mahasiswa kelas berhasil dihitung",
  "data": {
    "kelas": "Angkatan 2024",
    "total_mahasiswa": 3,
    "mhs_aktif": 3,
    "mhs_nonaktif": 0,
    "mhs_cuti": 0,
    "mhs_keluar": 0
  }
}
```

---

## [POST] /api/v1/siakad/bimbingan/aktivitas

Menyimpan atau memperbarui laporan sesi aktivitas bimbingan PA per kelas.

### Request Body

```json
{
  "id": 1,
  "tahun_akademik_id": 1,
  "kelas": "Angkatan 2024",
  "tanggal": "2026-09-26",
  "mhs_aktif": 3,
  "mhs_nonaktif": 0,
  "mhs_cuti": 0,
  "mhs_keluar": 0,
  "kondisi_mahasiswa": "Perkuliahan berjalan lancar",
  "penanganan_mahasiswa": "Pendampingan 1 mahasiswa transfer",
  "kesimpulan": "KRS dan konversi selesai disetujui"
}
```

### Response Sukses (200 OK)

```json
{
  "success": true,
  "message": "Aktivitas bimbingan PA berhasil disimpan",
  "data": {
    "id": 1,
    "dosen_id": 2,
    "tahun_akademik_id": 1,
    "kelas": "Angkatan 2024",
    "tanggal": "2026-09-26",
    "mhs_aktif": 3
  }
}
```

---

## [GET] /api/v1/siakad/bimbingan/aktivitas/cetak

Menghasilkan format cetak printable PDF resmi laporan aktivitas bimbingan PA per kelas lengkap dengan kop surat, tabel pertemuan (Per, Tanggal, Aktifitas Kuliah A/N/C/K, Kondisi Mahasiswa, Penanganan Khusus, Kesimpulan), dan tanda tangan dosen PA / pimpinan.

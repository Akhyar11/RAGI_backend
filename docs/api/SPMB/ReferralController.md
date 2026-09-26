# ReferralController

> **Modul**: SPMB  
> **Base URL**: `/api/spmb`  
> **Autentikasi**: Bearer Token (Sanctum) — kecuali dinyatakan lain  
> **Dibuat**: 2026-09-24  
> **Diperbarui**: 2026-09-26

Controller ini mengelola fitur **Kode Referral (Rujukan) Mahasiswa Baru**. Setiap pengguna (`core_users`) memiliki `referral_code` unik yang dapat dibagikan kepada calon mahasiswa. Saat calon mahasiswa mendaftar (register akun atau mengisi wizard SPMB), kode referensi dapat diterapkan pada `spmb_pendaftaran_calon_mhs` dan dicatat pada `spmb_referral_usages`.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/spmb/referral/validate` | Validasi kode referral & tampilkan nama pemilik (tersamar) | ❌ Publik (throttle 20/menit) |
| GET | `/api/spmb/referral/saya` | Kode referral milik user aktif + statistik + nominal bisa dicairkan | ✅ Bearer |
| GET | `/api/spmb/referral/saya/usages` | Daftar referral milik user (paginasi + filter) | ✅ Bearer |
| POST | `/api/spmb/referral/payout` | Membuat bukti pencairan (JSON) | ✅ Bearer |
| GET | `/api/spmb/referral/payout/{payout}/download` | Unduh bukti pencairan (PDF) | ✅ Bearer |
| GET | `/api/spmb/laporan/referral` | Rekap laporan penggunaan referral (paginasi) | ✅ `spmb.laporan.read` |
| GET | `/api/spmb/laporan/referral-summary` | Ringkasan agregat referral per status | ✅ `spmb.laporan.read` |

---

## GET /api/spmb/referral/validate

> Memvalidasi sebuah kode referral. Endpoint ini publik agar dapat dipakai pada halaman registrasi akun sebelum login. Nama pemilik dikembalikan dalam bentuk tersamar (contoh: `B*d* S******o`).

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `code` | string | ✅ | — | Kode referral yang dicek (contoh: `REF-A1B2C3`) |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Kode referral valid.",
    "data": {
        "referral_code": "REF-A1B2C3",
        "referrer_user_id": 12,
        "referrer_name": "B*d* S******o",
        "is_valid": true
    }
}
```

### Response Error

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "referral_code": ["Kode referral tidak ditemukan atau tidak aktif."]
    }
}
```

> Catatan: kode milik pengguna yang sedang login juga ditolak dengan pesan `Anda tidak dapat menggunakan kode referral milik sendiri.`

---

## GET /api/spmb/referral/saya

> Mengambil kode referral milik pengguna yang sedang login beserta ringkasan statistik dan riwayat 50 penggunaan terakhir.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data referral berhasil diambil.",
    "data": {
        "summary": {
            "referral_code": "REF-A1B2C3",
            "total": 3,
            "claimed": 2,
            "qualified": 1,
            "rewarded": 0,
            "cancelled": 0
        },
        "usages": [
            {
                "id": 7,
                "referral_code": "REF-A1B2C3",
                "status": "qualified",
                "qualified_at": "2026-09-24T08:00:00.000000Z",
                "rewarded_at": null,
                "referee_name": "Calon Mahasiswa",
                "no_pendaftaran": "REG-20260924-1234",
                "nama_pendaftar": "Calon Mahasiswa",
                "status_pendaftaran": "lulus_administrasi",
                "created_at": "2026-09-20T10:00:00.000000Z"
            }
        ]
    }
}
```

### Status Penggunaan

| Status | Arti |
|---|---|
| `claimed` | Kode sudah diterapkan, menunggu verifikasi administrasi |
| `qualified` | Calon mahasiswa lolos administrasi |
| `rewarded` | Reward referral telah diberikan |
| `cancelled` | Pendaftaran gagal administrasi (referral dibatalkan) |

---

## GET /api/spmb/laporan/referral

> Rekap seluruh penggunaan referral untuk panitia/admin SPMB. Mendukung paginasi, filter, dan sorting server-side.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari kode / nama referrer / no pendaftaran |
| `status` | string | ❌ | — | Filter status `claimed`, `qualified`, `rewarded`, `cancelled` |
| `referral_code` | string | ❌ | — | Filter kode referral (like) |
| `referrer` | string | ❌ | — | Filter nama/username/email referrer (like) |
| `pendaftar` | string | ❌ | — | Filter nama/no pendaftaran (like) |
| `gelombang_id` | integer | ❌ | — | Filter gelombang penerimaan (ID) |
| `start_date` | date | ❌ | — | Filter dibuat sejak tanggal (Y-m-d) |
| `end_date` | date | ❌ | — | Filter dibuat sampai tanggal (Y-m-d) |
| `sort_by` | string | ❌ | `created_at` | `created_at`, `status`, `referral_code`, `referrer`, `pendaftar`, atau `gelombang` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data retrieved successfully",
    "data": [
        {
            "id": 7,
            "referral_code": "REF-A1B2C3",
            "status": "qualified",
            "referrer": {
                "id": 12,
                "username": "alumni01",
                "name": "Alumni Satu",
                "email": "alumni@kampus.ac.id",
                "referral_code": "REF-A1B2C3"
            },
            "pendaftaran": {
                "id": 33,
                "no_pendaftaran": "REG-20260924-1234",
                "nama_lengkap": "Calon Mahasiswa",
                "status": "lulus_administrasi",
                "status_pembayaran": "lunas"
            }
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
    "summary": {
        "claimed": 2,
        "qualified": 1,
        "rewarded": 0,
        "cancelled": 0
    }
}
```

### Response Error

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

---

## Catatan Integrasi

- Kolom `used_referral_code`, `referrer_user_id`, dan `referral_validated_at` tersimpan di `spmb_pendaftaran_calon_mhs`.
- Setiap penggunaan kode tercatat di tabel `spmb_referral_usages` (satu baris per pendaftaran, unik).
- Penggunaan referral otomatis menjadi `qualified` saat pendaftaran berstatus `lulus_administrasi`, dan `cancelled` saat `gagal_administrasi`.
- Kode hanya dapat diubah selama status pendaftaran `draft` atau `submitted`.

---

## GET /api/spmb/referral/saya/usages

> Daftar referral milik pengguna yang login (untuk tab **Referral** di `/profile`).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari kode / nama pendaftar / no pendaftaran |
| `status` | string | ❌ | — | `claimed` / `qualified` / `rewarded` / `cancelled` |
| `start_date` | date | ❌ | — | Filter dibuat sejak tanggal |
| `end_date` | date | ❌ | — | Filter dibuat sampai tanggal |
| `sort_by` | string | ❌ | `created_at` | `created_at` / `status` / `referral_code` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |

### Response Sukses (200 OK)
Envelope standar (`data`, `meta`, `filters`). Statistik & nominal bisa dicairkan tersedia di `GET /api/spmb/referral/saya`.

```json
{
    "status": "success",
    "data": [
        { "id": 1, "referral_code": "REF-A1B2C3", "status": "qualified", "reward_nominal": 50000, "nama_pendaftar": "Budi", "no_pendaftaran": "REG-20260926-0001", "gelombang": "Gelombang 1", "created_at": "2026-09-26T08:00:00.000000Z" }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1 },
    "filters": { "search": null, "status": null, "start_date": null, "end_date": null, "sort_by": "created_at", "sort_order": "desc" }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

---

## POST /api/spmb/referral/payout

> Membuat **bukti pencairan (payout)** untuk referral yang layak dicairkan. Status referral **tidak diubah** (tetap `qualified`); hanya ditandai `payout_id`.

**Kriteria layak cair:** status `qualified`, belum ditandai payout, pendaftar "selesai" (lulus/mahasiswa baru atau daftar ulang lunas), dan sudah membayar biaya daftar ulang minimal `config('spmb.referral.min_daftar_ulang_payment')`.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

| Field | Type | Required | Deskripsi |
|---|---|---|---|
| `keterangan` | string | ❌ | Catatan (maks 255) |

```json
{
    "keterangan": "Pencairan reward referral periode September 2026"
}
```

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Bukti pencairan referral berhasil dibuat.",
    "data": { "id": 3, "nomor_bukti": "PAYOUT-20260926-AB12CD", "referral_count": 1, "total_nominal": 50000, "generated_at": "2026-09-26T09:00:00.000000Z" }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**422 Unprocessable Content**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "keterangan": ["Keterangan maksimal 255 karakter."]
    }
}
```

---

## GET /api/spmb/laporan/referral-summary

> Ringkasan agregat penggunaan referral per status (seluruh data, bukan per halaman). Otorisasi: `can:spmb.laporan.read`.

### Query Parameters
Tidak ada (tanpa paginasi/filter).

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Data retrieved successfully",
    "data": { "claimed": 12, "qualified": 7, "rewarded": 0, "cancelled": 3 }
}
```

### Response Error

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

---

## GET /api/spmb/referral/payout/{payout}/download

> Mengunduh **bukti pencairan (PDF)** milik pengguna yang login.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |

### Response Sukses (200 OK)
Binary `application/pdf` (`Content-Disposition: attachment`).

### Response Error

**404 Not Found**
```json
{
    "status": "error",
    "message": "Bukti pencairan tidak ditemukan."
}
```

---

## Reward Referral (Mapping per Role)

- Nominal reward diambil dari **master komponen biaya SPMB** yang ditandai `is_referral_reward = true`, melalui mapping role → nominal: tabel `spmb_komponen_biaya_role_reward (komponen_biaya_id, role_id, nominal)`.
- Reward per referral milik referrer = **jumlah nominal mapping** untuk role referrer (mis. role Mahasiswa bisa berbeda dari Dosen).
- Konfigurasi mapping dilakukan via endpoint Master Komponen Biaya (`POST/PUT /api/spmb/master/komponen-biaya` dengan `is_referral_reward` + `role_rewards[]`).
- Bukti pencairan tercatat di tabel `spmb_referral_payouts` (nomor bukti, jumlah referral, total nominal).

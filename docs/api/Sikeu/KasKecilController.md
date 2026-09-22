# KasKecilController

> **Modul**: SIKEU (Keuangan) — Kas Kecil / Petty Cash  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (JWT/Sanctum)  
> **Dibuat**: 2026-09-21  
> **Diperbarui**: 2026-09-21

## Gambaran Singkat

Fitur **Kas Kecil (Petty Cash)** menghubungkan 3 peran:

1. **Admin Keuangan Akuntansi** — membuat unit kas kecil (dipetakan per fakultas) & menyetujui/menolak pengajuan kas langsung (top-up).
2. **Petugas Kas Kecil** — mencatat transaksi pengeluaran (saldo berkurang + jurnal otomatis), melihat saldo, dan mengajukan kas langsung.
3. **Pimpinan (read-only)** — memantau saldo & transaksi unit kas kecil.

Permission RBAC:
| Slug | Pemilik | Kegunaan |
|---|---|---|
| `sikeu.kaskecil.read` | Petugas, Admin Keuangan, Pimpinan | Lihat unit/saldo/transaksi/pengajuan |
| `sikeu.kaskecil.transaksi` | Petugas | Catat transaksi pengeluaran |
| `sikeu.kaskecil.pengajuan` | Petugas | Ajukan kas langsung |
| `sikeu.kaskecil.approve` | Admin Keuangan Akuntansi | Setuju/tolak pengajuan |
| `sikeu.kas.manage` | Admin Keuangan Akuntansi | Buat unit kas kecil |

> **Catatan bisnis**
> - Transaksi keluar → saldo unit berkurang, direkam di `sikeu_transaksi_kas_unit` (`kredit_pengeluaran`), dan jurnal otomatis **Dr Beban (502.01) / Cr Akun Kas Unit**.
> - Persetujuan pengajuan → saldo unit bertambah, direkam di `sikeu_transaksi_kas_unit` (`debet_pemasukan`), dan jurnal otomatis **Dr Akun Kas Unit / Cr Kas Utama (101.01)**.
> - Petugas Kas Kecil **hanya** dapat mengakses unit di mana dia menjadi `penanggung_jawab_id` (403 jika mencoba unit milik petugas lain).
> - Kategori transaksi wajib diambil dinamis dari `spmb_master_referensi` tipe `kategori_kas_kecil` (no-hardcode).
> - Unit kas kecil **wajib** dipetakan ke akun COA kelompok aset (mis. `101.02 Kas Unit Fakultas / Petty Cash`) agar jurnal pengisian tidak netral.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/kas-kecil` | Daftar unit kas kecil (paginasi + filter) | ✅ `sikeu.kaskecil.read` |
| POST | `/api/v1/sikeu/kas-kecil` | Buat unit kas kecil baru | ✅ `sikeu.kas.manage` |
| GET | `/api/v1/sikeu/kas-kecil/{id}` | Detail unit kas kecil + 10 transaksi & pengajuan terbaru | ✅ `sikeu.kaskecil.read` |
| PUT | `/api/v1/sikeu/kas-kecil/{id}` | Update data unit kas kecil | ✅ `sikeu.kas.manage` |
| GET | `/api/v1/sikeu/kas-kecil/{id}/transaksi` | Daftar transaksi keluar unit | ✅ `sikeu.kaskecil.read` |
| POST | `/api/v1/sikeu/kas-kecil/{id}/transaksi` | Catat transaksi pengeluaran (saldo-jurnal otomatis) | ✅ `sikeu.kaskecil.transaksi` |
| GET | `/api/v1/sikeu/kas-kecil/{id}/pengajuan` | Daftar pengajuan kas langsung unit | ✅ `sikeu.kaskecil.read` |
| POST | `/api/v1/sikeu/kas-kecil/{id}/pengajuan` | Ajukan kas langsung / top-up | ✅ `sikeu.kaskecil.pengajuan` |
| POST | `/api/v1/sikeu/kas-kecil/pengajuan/{id}/approve` | Setujui pengajuan (saldo + jurnal) | ✅ `sikeu.kaskecil.approve` |
| POST | `/api/v1/sikeu/kas-kecil/pengajuan/{id}/reject` | Tolak pengajuan (tanpa mutasi) | ✅ `sikeu.kaskecil.approve` |
| GET | `/api/v1/sikeu/kas-kecil/referensi/kategori` | Referensi kategori transaksi (master) | ✅ `sikeu.kaskecil.read` |
| GET | `/api/v1/sikeu/kas-kecil/referensi/petugas` | Referensi user ber-role `petugas_kas_kecil` | ✅ `sikeu.kaskecil.read` |

> **Urutan route penting**: route literal (`referensi/kategori`, `referensi/petugas`, `pengajuan/{id}/approve|reject`) didefinisikan **sebelum** `kas-kecil/{id}` agar tidak tertutup oleh route `{id}`.
>
> **CheckMenuAccess**: `referensi/kategori` & `referensi/petugas` berada di `PUBLIC_PATHS` middleware CheckMenuAccess (tetap wajib login via `auth:api`, hanya melewati cek menu) karena fallback basename `kas-kecil` tidak menjangkau segment `referensi`.

---

## GET /api/v1/sikeu/kas-kecil

> Menampilkan daftar unit kas kecil. Petugas Kas Kecil hanya melihat unit yang dipegangnya (scoping otomatis di `KasKecilService`).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari berdasarkan `nama_kas` / `penanggung_jawab` |
| `fakultas_id` | integer | ❌ | — | Filter fakultas |
| `status` | boolean | ❌ | — | Filter aktif/nonaktif (`1`/`0`) |
| `sort_by` | string | ❌ | `nama_kas` | Kolom pengurutan |
| `sort_dir` | string | ❌ | `asc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses — 200 OK

```json
{
    "status": "success",
    "message": "Data unit kas kecil berhasil dimuat",
    "data": [
        {
            "id": 8,
            "fakultas_id": 1,
            "nama_kas": "Kas Kecil FTI",
            "tipe_kas": "petty_cash",
            "kanal": "tunai",
            "akun_keuangan_id": 2,
            "penanggung_jawab_id": 56,
            "saldo_awal": "2500000.00",
            "saldo_saat_ini": "2115000.00",
            "deskripsi": "Kas kecil unit Fakultas Teknologi Informasi & Sains Data",
            "status": true,
            "fakultas": {
                "id": 1,
                "nama": "Fakultas Teknologi Informasi & Sains Data"
            },
            "akun_keuangan": {
                "id": 2,
                "kode_akun": "101.02",
                "nama_akun": "Kas Unit Fakultas / Petty Cash"
            },
            "penanggung_jawab": {
                "id": 56,
                "username": "petugas_kas_kecil",
                "email": "petugas.kaskecil@kampus.ac.id",
                "is_superadmin": false,
                "is_admin": false,
                "pegawai": {
                    "id": 110,
                    "user_id": 56,
                    "unit_kerja_id": 1,
                    "nama_lengkap": "Petugas Kas Kecil"
                }
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
    }
}
```

> **Objek penanggung jawab**: `penanggung_jawab` dikembalikan sebagai objek user **ringkas** — hanya field publik (`id`, `username`, `email`, `is_superadmin`, `is_admin`) + `pegawai` (SIMPEG, untuk label). Field sensitif user (token/recovery 2FA, dsb.) **tidak** diserialisasi. Pola yang sama berlaku untuk `dibuat_oleh`, `pemohon`, dan `approver`.

### Response Error

**403 — tidak punya permission `sikeu.kaskecil.read`**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki akses ke modul kas kecil."
}
```

---

## POST /api/v1/sikeu/kas-kecil

> Membuat unit kas kecil baru (role: Admin Keuangan dengan permission `sikeu.kas.manage`).

### Request Body

```json
{
    "nama_kas": "Kas Kecil Fasilkom",
    "fakultas_id": 1,
    "penanggung_jawab_id": 56,
    "akun_keuangan_id": 2,
    "saldo_awal": 2500000,
    "deskripsi": "Kas kecil operation unit"
}
```

| Field | Type | Required | Validasi |
|---|---|---|---|
| `nama_kas` | string | ✅ | max 191 |
| `fakultas_id` | integer | ✅ | `exists:siakad_fakultas,id` |
| `penanggung_jawab_id` | integer | ✅ | `exists:core_users,id` |
| `akun_keuangan_id` | integer | ✅ | `exists:sikeu_akun_keuangan,id` + harus kelompok `aset` |
| `saldo_awal` | numeric | ✅ | min 0 |
| `deskripsi` | string | ❌ | — |

> Unit dibuat otomatis dengan `tipe_kas='petty_cash'`, `kanal='tunai'`, `status=true`, dan `saldo_saat_ini = saldo_awal`.

### Response Sukses — 201 Created

```json
{
    "status": "success",
    "message": "Unit kas kecil berhasil dibuat",
    "data": {
        "id": 8,
        "fakultas_id": 1,
        "nama_kas": "Kas Kecil FTI",
        "tipe_kas": "petty_cash",
        "kanal": "tunai",
        "akun_keuangan_id": 2,
        "penanggung_jawab_id": 56,
        "saldo_awal": "2500000.00",
        "saldo_saat_ini": "2500000.00",
        "deskripsi": "Kas kecil unit Fakultas Teknologi Informasi & Sains Data",
        "status": true,
        "is_kabag_kas": false,
        "akun_keuangan": {
            "id": 2,
            "kode_akun": "101.02",
            "nama_akun": "Kas Unit Fakultas / Petty Cash"
        },
        "penanggung_jawab": {
            "id": 56,
            "username": "petugas_kas_kecil",
            "email": "petugas.kaskecil@kampus.ac.id",
            "pegawai": { "id": 110, "user_id": 56, "nama_lengkap": "Petugas Kas Kecil" }
        }
    }
}
```

### Response Error

**422 — akun COA bukan kelompok aset**
```json
{
    "status": "error",
    "message": "Akun pemetaan kas kecil harus akun kelompok aset (kas-bank 101/102)."
}
```

**403 — bukan admin keuangan**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki izin membuat unit kas kecil."
}
```

---

## PUT /api/v1/sikeu/kas-kecil/{id}

> Memperbarui konfigurasi unit kas kecil (nama, fakultas, penanggung jawab, akun COA, deskripsi, dan status aktif/non-aktif). Aksi ini dicatat dalam sistem audit log (`sikeu_unit_kas`). Saldo saat ini tidak diubah lewat endpoint ini demi integritas pembukuan kas.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "nama_kas": "Kas Kecil Fakultas Teknik & Komputer",
    "fakultas_id": 1,
    "penanggung_jawab_id": 56,
    "akun_keuangan_id": 2,
    "deskripsi": "Kas kecil operasional harian",
    "status": true
}
```

### Response Sukses — 200 OK

```json
{
    "status": "success",
    "message": "Unit kas kecil berhasil diperbarui",
    "data": {
        "id": 8,
        "fakultas_id": 1,
        "nama_kas": "Kas Kecil Fakultas Teknik & Komputer",
        "tipe_kas": "petty_cash",
        "kanal": "tunai",
        "akun_keuangan_id": 2,
        "penanggung_jawab_id": 56,
        "saldo_awal": "2500000.00",
        "saldo_saat_ini": "2115000.00",
        "deskripsi": "Kas kecil operasional harian",
        "status": true,
        "fakultas": {
            "id": 1,
            "nama": "Fakultas Teknologi Informasi & Sains Data"
        },
        "akun_keuangan": {
            "id": 2,
            "kode_akun": "101.02",
            "nama_akun": "Kas Unit Fakultas / Petty Cash"
        },
        "penanggung_jawab": {
            "id": 56,
            "username": "petugas_kas_kecil",
            "email": "petugas.kaskecil@kampus.ac.id",
            "pegawai": { "id": 110, "user_id": 56, "nama_lengkap": "Petugas Kas Kecil" }
        }
    }
}
```

---

## GET /api/v1/sikeu/kas-kecil/{id}

> Menampilkan detail unit kas kecil beserta 10 transaksi & 10 pengajuan terbaru.
> Petugas hanya boleh membuka unit yang dipegangnya.

### Response Sukses — 200 OK

```json
{
    "status": "success",
    "data": {
        "id": 8,
        "nama_kas": "Kas Kecil FTI",
        "saldo_saat_ini": "2115000.00",
        "penanggung_jawab_id": 56,
        "penanggung_jawab": {
            "id": 56,
            "username": "petugas_kas_kecil",
            "email": "petugas.kaskecil@kampus.ac.id",
            "pegawai": { "id": 110, "user_id": 56, "nama_lengkap": "Petugas Kas Kecil" }
        },
        "fakultas": { "id": 1, "nama": "Fakultas Teknologi Informasi & Sains Data" },
        "kasKecil_transaksis": [
            {
                "id": 1,
                "nomor_transaksi": "KK-TRX-20260921124745-STPE",
                "uraian": "Pembelian ATK (kertas A4, tinta printer)",
                "nominal": "385000.00",
                "tanggal_transaksi": "2026-09-21",
                "kategori": { "id": 31, "nama": "Alat Tulis Kantor (ATK)", "kode": "atk" }
            }
        ],
        "kasKecil_pengajuans": [
            {
                "id": 1,
                "nomor_pengajuan": "KK-PGJ-20260921124800-ED3S",
                "judul_pengajuan": "Pengisian kas kecil periode September",
                "nominal_diajukan": "2000000.00",
                "status": "disetujui"
            }
        ]
    }
}
```

> **Catatan**: Nama relasi di atas bersifat indikatif sesuai model `UnitKas` (`kasKecilTransaksis`, `kasKecilPengajuans`).

### Response Error

**403 — unit milik petugas lain**
```json
{
    "status": "error",
    "message": "Anda hanya dapat mengakses unit kas kecil yang Anda pegang."
}
```

---

## GET /api/v1/sikeu/kas-kecil/{id}/transaksi

> Daftar transaksi pengeluaran unit kas kecil (paginasi + filter).

### Query Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `search` | string | ❌ | `nomor_transaksi` / `uraian` / `penerima` |
| `kategori_id` | integer | ❌ | Filter kategori (`referensi_kategori_id`) |
| `tanggal_awal` | date | ❌ | Filter tanggal transaksi ≥ |
| `tanggal_akhir` | date | ❌ | Filter tanggal transaksi ≤ |
| `per_page` | integer | ❌ | Default 15, maks 100 |

### Response Sukses — 200 OK

```json
{
    "status": "success",
    "data": [
        {
            "id": 1,
            "unit_kas_id": 8,
            "transaksi_kas_unit_id": 1,
            "nomor_transaksi": "KK-TRX-20260921124745-STPE",
            "referensi_kategori_id": 31,
            "uraian": "Pembelian ATK (kertas A4, tinta printer)",
            "penerima": "Toko Sinar Jaya",
            "nominal": "385000.00",
            "tanggal_transaksi": "2026-09-21",
            "file_bukti_path": null,
            "created_by": 56,
            "dibuat_oleh": {
                "id": 56,
                "username": "petugas_kas_kecil",
                "email": "petugas.kaskecil@kampus.ac.id",
                "pegawai": { "id": 110, "user_id": 56, "nama_lengkap": "Petugas Kas Kecil" }
            },
            "kategori": { "id": 31, "kode": "atk", "nama": "Alat Tulis Kantor (ATK)" }
        }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1 },
    "saldo_saat_ini": "2115000.00"
}
```

---

## POST /api/v1/sikeu/kas-kecil/{id}/transaksi

> Mencatat transaksi pengeluaran kas kecil. Proses di `KasKecilService::storeTransaksi` dalam satu transaksi DB:
> 1. Cek saldo cukup (`nominal <= saldo_saat_ini`).
> 2. Simpan bukti file (opsional) ke `storage/app/public/sikeu/kas_kecil/{Y/m}/`.
> 3. Rekam `sikeu_transaksi_kas_unit` (`kredit_pengeluaran`) + kurangi `saldo_saat_ini`.
> 4. Simpan `sikeu_kas_kecil_transaksi`.
> 5. Jurnal otomatis **Dr Beban (502.01) / Cr Akun Kas Unit** via `JurnalSikeuService::jurnalKeluarKasKecil`.
> 6. Audit log `keluar_kas_kecil`.

### Request Body (multipart/form-data — bila ada file)

| Field | Type | Required | Validasi |
|---|---|---|---|
| `referensi_kategori_id` | integer | ❌ | `exists:spmb_master_referensi,id` |
| `uraian` | string | ✅ | min 3, max 2000 |
| `penerima` | string | ❌ | max 191 |
| `nominal` | numeric | ✅ | min 1 |
| `tanggal_transaksi` | date | ✅ | `before_or_equal:today` |
| `file_bukti` | file | ❌ | `mimes:pdf,jpg,jpeg,png`, max 5120 KB |
| `keterangan` | string | ❌ | — |

```json
{
    "referensi_kategori_id": 31,
    "uraian": "Pembelian ATK (kertas A4, tinta printer)",
    "penerima": "Toko Sinar Jaya",
    "nominal": 385000,
    "tanggal_transaksi": "2026-09-21"
}
```

### Response Sukses — 201 Created

```json
{
    "status": "success",
    "message": "Transaksi kas kecil berhasil dicatat",
    "data": {
        "id": 1,
        "unit_kas_id": 8,
        "nomor_transaksi": "KK-TRX-20260921124745-STPE",
        "referensi_kategori_id": 31,
        "uraian": "Pembelian ATK (kertas A4, tinta printer)",
        "penerima": "Toko Sinar Jaya",
        "nominal": "385000.00",
        "tanggal_transaksi": "2026-09-21",
        "file_bukti_path": null
    }
}
```

### Response Error

**422 — saldo tidak mencukupi**
```json
{
    "status": "error",
    "message": "Saldo kas kecil tidak mencukupi untuk transaksi ini."
}
```

**422 — validasi gagal**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "uraian": ["Uraian transaksi wajib diisi."],
        "nominal": ["The nominal field is required."]
    }
}
```

---

## GET /api/v1/sikeu/kas-kecil/{id}/pengajuan

> Daftar pengajuan kas langsung unit (paginasi + filter).

### Query Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `search` | string | ❌ | `nomor_pengajuan` / `judul_pengajuan` |
| `status` | string | ❌ | `pending_keuangan` / `disetujui` / `ditolak` |
| `per_page` | integer | ❌ | Default 15, maks 100 |

### Response Sukses — 200 OK

```json
{
    "status": "success",
    "data": [
        {
            "id": 1,
            "unit_kas_id": 8,
            "nomor_pengajuan": "KK-PGJ-20260921124800-ED3S",
            "judul_pengajuan": "Pengisian kas kecil periode September",
            "keperluan": "Stok kas kecil menipis setelah pembelian ATK, perlu top-up Rp 2.000.000",
            "nominal_diajukan": "2000000.00",
            "nominal_disetujui": "2000000.00",
            "status": "disetujui",
            "catatan_penolakan": null,
            "pemohon_id": 56,
            "approved_by": 54,
            "approved_at": "2026-09-21T12:49:00.000000Z",
            "pemohon": {
                "id": 56,
                "username": "petugas_kas_kecil",
                "email": "petugas.kaskecil@kampus.ac.id",
                "is_superadmin": false,
                "is_admin": false,
                "pegawai": { "id": 110, "user_id": 56, "nama_lengkap": "Petugas Kas Kecil" }
            },
            "approver": {
                "id": 54,
                "username": "admin_keu_akuntansi",
                "email": "admin.keu.akuntansi@kampus.ac.id",
                "is_superadmin": false,
                "is_admin": false,
                "pegawai": null
            }
        }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1 }
}
```

---

## POST /api/v1/sikeu/kas-kecil/{id}/pengajuan

> Mengajukan kas langsung / top-up oleh Petugas Kas Kecil. Status awal `pending_keuangan`.

### Request Body

```json
{
    "judul_pengajuan": "Pengisian kas kecil periode September",
    "keperluan": "Stok kas kecil menipis setelah pembelian ATK, perlu top-up Rp 2.000.000",
    "nominal_diajukan": 2000000
}
```

| Field | Type | Required | Validasi |
|---|---|---|---|
| `judul_pengajuan` | string | ✅ | min 3, max 255 |
| `keperluan` | string | ❌ | — |
| `nominal_diajukan` | numeric | ✅ | min 1 |

### Response Sukses — 201 Created

```json
{
    "status": "success",
    "message": "Pengajuan kas langsung berhasil dibuat dan menunggu persetujuan",
    "data": {
        "id": 1,
        "unit_kas_id": 8,
        "nomor_pengajuan": "KK-PGJ-20260921124800-ED3S",
        "judul_pengajuan": "Pengisian kas kecil periode September",
        "nominal_diajukan": "2000000.00",
        "nominal_disetujui": "0.00",
        "status": "pending_keuangan",
        "pemohon_id": 56,
        "pemohon": {
            "id": 56,
            "username": "petugas_kas_kecil",
            "email": "petugas.kaskecil@kampus.ac.id",
            "pegawai": { "id": 110, "user_id": 56, "nama_lengkap": "Petugas Kas Kecil" }
        }
    }
}
```

---

## POST /api/v1/sikeu/kas-kecil/pengajuan/{id}/approve

> Menyetujui pengajuan kas langsung (role Admin Keuangan Akuntansi). Proses `KasKecilService::approvePengajuan`:
> 1. Validasi status masih `pending_keuangan` & nominal disetujui ≤ diajukan.
> 2. Update status → `disetujui`, isi `approved_by/at`.
> 3. Tambah `saldo_saat_ini` unit.
> 4. Rekam `sikeu_transaksi_kas_unit` (`debet_pemasukan`).
> 5. Jurnal **Dr Akun Kas Unit / Cr Kas Utama (101.01)** via `jurnalPengisianKasKecil`.
> 6. Audit log `setujui_kas_kecil`.

### Request Body

```json
{
    "nominal_disetujui": 2000000
}
```

`nominal_disetujui` opsional (default = `nominal_diajukan`), wajib ≤ `nominal_diajukan`.

### Response Sukses — 200 OK

```json
{
    "status": "success",
    "message": "Pengajuan kas langsung disetujui. Saldo kas kecil bertambah & jurnal otomatis dibuat.",
    "data": {
        "id": 1,
        "unit_kas_id": 8,
        "nomor_pengajuan": "KK-PGJ-20260921124800-ED3S",
        "status": "disetujui",
        "nominal_diajukan": "2000000.00",
        "nominal_disetujui": "2000000.00",
        "approved_by": 54,
        "approved_at": "2026-09-21T12:49:00.000000Z",
        "pemohon": {
            "id": 56,
            "username": "petugas_kas_kecil",
            "email": "petugas.kaskecil@kampus.ac.id",
            "pegawai": { "id": 110, "user_id": 56, "nama_lengkap": "Petugas Kas Kecil" }
        },
        "approver": {
            "id": 54,
            "username": "admin_keu_akuntansi",
            "email": "admin.keu.akuntansi@kampus.ac.id",
            "pegawai": null
        },
        "unitKas": {
            "id": 8,
            "nama_kas": "Kas Kecil FTI",
            "saldo_saat_ini": "4115000.00"
        }
    }
}
```

### Response Error

**422 — status sudah diproses**
```json
{
    "status": "error",
    "message": "Pengajuan kas kecil ini sudah disetujui."
}
```

**422 — unit belum dipetakan akun kas tersendiri**
```json
{
    "status": "error",
    "message": "Unit kas kecil belum dipetakan ke akun COA kas tersendiri (mis. 101.02 Kas Unit / Petty Cash). Jurnal pengisian kas kecil dibatalkan."
}
```

---

## POST /api/v1/sikeu/kas-kecil/pengajuan/{id}/reject

> Menolak pengajuan kas langsung (tetap status `pending_keuangan` → `ditolak`). **Tidak ada** mutasi saldo / jurnal.

### Request Body

```json
{
    "catatan_penolakan": "Saldo kas utama sedang dalam proses rekonsiliasi."
}
```

### Response Sukses — 200 OK

```json
{
    "status": "success",
    "message": "Pengajuan kas langsung ditolak.",
    "data": {
        "id": 3,
        "unit_kas_id": 8,
        "nomor_pengajuan": "KK-PGJ-20260921124925-BE6F",
        "status": "ditolak",
        "catatan_penolakan": "Saldo kas utama sedang dalam proses rekonsiliasi.",
        "approved_by": 54,
        "approved_at": "2026-09-21T12:50:00.000000Z",
        "pemohon": {
            "id": 56,
            "username": "petugas_kas_kecil",
            "email": "petugas.kaskecil@kampus.ac.id",
            "pegawai": { "id": 110, "user_id": 56, "nama_lengkap": "Petugas Kas Kecil" }
        },
        "approver": {
            "id": 54,
            "username": "admin_keu_akuntansi",
            "email": "admin.keu.akuntansi@kampus.ac.id",
            "pegawai": null
        }
    }
}
```

### Response Error

**422 — catatan penolakan wajib**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "catatan_penolakan": ["The catatan penolakan field is required."]
    }
}
```

**422 — status tidak valid**
```json
{
    "status": "error",
    "message": "Hanya pengajuan berstatus pending_keuangan yang dapat ditolak."
}
```

---

## DELETE /api/v1/sikeu/kas-kecil/pengajuan/{id}

> Menghapus / membatalkan pengajuan kas langsung. Hanya dapat dilakukan pada pengajuan yang berstatus `pending_keuangan`.
> Pengajuan yang sudah disetujui atau ditolak tidak dapat dihapus.
> Dapat diakses oleh Pemohon itu sendiri (petugas kas kecil yang membuat) atau user dengan hak akses admin / `sikeu.kaskecil.pengajuan` / `sikeu.kas.manage` / `sikeu.kaskecil.approve`.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses — 200 OK

```json
{
    "status": "success",
    "message": "Pengajuan kas langsung berhasil dihapus/dibatalkan."
}
```

### Response Error

**422 — Status bukan pending**
```json
{
    "status": "error",
    "message": "Hanya pengajuan dengan status menunggu persetujuan (pending) yang dapat dihapus/dibatalkan."
}
```

**403 — Bukan pemohon dan tidak memiliki izin**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki izin menghapus pengajuan ini."
}
```

---

## GET /api/v1/sikeu/kas-kecil/referensi/kategori

> Menampilkan kategori transaksi kas kecil dari master referensi tipe `kategori_kas_kecil` (dinamis, no-hardcode).

### Response Sukses — 200 OK

```json
{
    "status": "success",
    "data": [
        { "id": 31, "tipe": "kategori_kas_kecil", "kode": "atk", "nama": "Alat Tulis Kantor (ATK)", "urutan": 0, "is_active": true },
        { "id": 32, "tipe": "kategori_kas_kecil", "kode": "konsumsi_rapat", "nama": "Konsumsi Rapat", "urutan": 0, "is_active": true },
        { "id": 33, "tipe": "kategori_kas_kecil", "kode": "transportasi", "nama": "Transportasi / Perjalanan Dinas", "urutan": 0, "is_active": true },
        { "id": 34, "tipe": "kategori_kas_kecil", "kode": "penggandaan", "nama": "Fotokopi & Penggandaan", "urutan": 0, "is_active": true },
        { "id": 35, "tipe": "kategori_kas_kecil", "kode": "perawatan", "nama": "Perawatan / Perbaikan Kecil", "urutan": 0, "is_active": true },
        { "id": 36, "tipe": "kategori_kas_kecil", "kode": "lainnya", "nama": "Lainnya", "urutan": 0, "is_active": true }
    ]
}
```

---

## GET /api/v1/sikeu/kas-kecil/referensi/petugas

> Menampilkan referensi user ber-role `petugas_kas_kecil` untuk dipilih sebagai penanggung jawab unit (support `?q=`). Label di-compose dari `nama_lengkap` SIMPEG + `username`.

### Query Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `q` | string | ❌ | Cari `username`, `email`, atau `nama_lengkap` pegawai |

### Response Sukses — 200 OK

```json
{
    "status": "success",
    "data": [
        {
            "id": 56,
            "label": "Petugas Kas Kecil (petugas_kas_kecil)",
            "username": "petugas_kas_kecil",
            "email": "petugas.kaskecil@kampus.ac.id"
        }
    ]
}
```

---

## Catatan Tambahan

> - Semua mutasi saldo (keluar & pengisian) **ATOMIC** dalam `DB::transaction` — jika jurnal atau audit gagal, seluruh perubahan di-rollback.
> - Jurnal selalu **seimbang** (Dr = Cr) sesuai kebijakan Buku Besar & Akuntansi.
> - Setiap aksi tercatat di tabel `audit_logs` (modul `Sikeu`, action `keluar_kas_kecil`, `ajukan_kas_kecil`, `setujui_kas_kecil`, `tolak_kas_kecil`).
> - Objek relasi user (`penanggung_jawab`, `dibuat_oleh`, `pemohon`, `approver`) selalu dikembalikan **ringkas** — hanya field publik (`id`, `username`, `email`, `is_superadmin`, `is_admin`) + `pegawai` SIMPEG. Field sensitif user (token/recovery 2FA, dsb.) tidak diserialisasi.
> - Tabel transaksi & pengajuan memakai `softDeletes`.
> - Frontend memakai endpoint `transaksiIndex` yang mengembalikan `saldo_saat_ini` untuk menampilkan saldo langsung di halaman transaksi.
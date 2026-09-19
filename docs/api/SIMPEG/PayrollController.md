# PayrollController

> **Modul**: SIMPEG / **Base URL**: /api/simpeg/payroll / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-09-19

Modul ini mengelola siklus penggajian fleksibel terintegrasi: Master Komponen Gaji dinamis, kalkulator payroll terpadu (mengakomodasi Tunjangan Fungsional, Honor SKS Mengajar SIAKAD, Insentif Kehadiran Presensi, dan PPh 21 TER), rincian slip gaji, pengajuan ke SIKEU, hingga pencairan kas dan posting jurnal akuntansi seimbang otomatis di modul Keuangan (SIKEU).

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/simpeg/payroll` | Daftar rekapan payroll bulanan | ✅ Bearer |
| GET | `/api/simpeg/payroll/{id}` | Detail mendalam slip gaji & rincian butir | ✅ Bearer |
| POST | `/api/simpeg/payroll/generate` | Hitung kalkulasi payroll dari master, presensi & SIAKAD | ✅ Bearer |
| POST | `/api/simpeg/payroll/submit-to-sikeu` | Mengajukan rekapan payroll ke modul SIKEU | ✅ Bearer |
| POST | `/api/simpeg/payroll/{id}/process-payment` | Eksekusi pencairan gaji, posting jurnal & pajak SIKEU | ✅ Bearer |
| GET | `/api/simpeg/payroll/komponen` | Daftar master komponen gaji (Pendapatan & Potongan) | ✅ Bearer |
| POST | `/api/simpeg/payroll/komponen` | Tambah master komponen gaji baru | ✅ Bearer |
| PUT | `/api/simpeg/payroll/komponen/{id}` | Ubah master komponen gaji | ✅ Bearer |

---

## 1. GET /api/simpeg/payroll

> Mengambil daftar payroll bulanan pegawai dengan pagination dan filter.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari nama pegawai, NIP, atau NIDN |
| `periode` | string | ❌ | — | Filter periode format `YYYY-MM` (contoh: 2026-09) |
| `status_transfer` | string | ❌ | — | Filter status: `draft`, `submitted_to_sikeu`, `paid` |
| `pegawai_id` | integer | ❌ | — | Filter ID pegawai tertentu (admin) |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan (`created_at`, `periode_bulan_tahun`, `gaji_bersih`, `total_tunjangan`, `total_potongan`, `id`) |
| `sort_order` | string | ❌ | `desc` | Arah urutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Data payroll berhasil dimuat",
    "data": [
        {
            "id": 1,
            "pegawai_id": 1,
            "periode_bulan_tahun": "2026-09",
            "gaji_pokok": "4500000.00",
            "tunjangan_tetap": "2050000.00",
            "gaji_bersih": "7730000.00",
            "status_transfer": "draft",
            "pegawai": {
                "id": 1,
                "nama_lengkap": "Dr. Budi Santoso",
                "nip": "198001012005011001"
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
    "filters": {
        "search": "",
        "periode": "",
        "status_transfer": "",
        "sort_by": "periode_bulan_tahun",
        "sort_order": "desc"
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

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki hak akses (permission) untuk melihat Slip Gaji / Payroll."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data payroll tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Parameter query tidak valid.",
    "errors": {
        "per_page": [
            "Nilai per_page harus berupa angka."
        ]
    }
}
```

---

## 2. GET /api/simpeg/payroll/{id}

> Rincian mendalam satu slip gaji beserta butir detail penerimaan & potongan.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Rincian slip gaji berhasil dimuat",
    "data": {
        "id": 1,
        "pegawai_id": 1,
        "periode_bulan_tahun": "2026-09",
        "gaji_pokok": "4500000.00",
        "tunjangan_tetap": "2050000.00",
        "gaji_bersih": "7730000.00",
        "status_transfer": "draft",
        "pegawai": {
            "id": 1,
            "nama_lengkap": "Dr. Budi Santoso",
            "nip": "198001012005011001"
        },
        "details": [
            {
                "id": 1,
                "komponen_gaji_id": 1,
                "nominal": "4500000.00",
                "komponen": {
                    "id": 1,
                    "nama": "Gaji Pokok",
                    "jenis": "pendapatan"
                }
            }
        ]
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

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda hanya berhak melihat Slip Gaji milik sendiri."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data slip gaji tidak ditemukan."
}
```

---

## 3. POST /api/simpeg/payroll/generate

> Menjalankan kalkulasi payroll bulanan terpadu untuk periode tertentu (YYYY-MM). Mengkombinasikan gaji pokok, tunjangan fungsional dari riwayat jabatan dosen, honor SKS dari jadwal kelas aktif SIAKAD, insentif kehadiran presensi tepat waktu, potongan BPJS, dan estimasi pajak PPh 21.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

| Field | Type | Required | Validasi | Deskripsi |
|---|---|---|---|---|
| `periode` | string | ✅ | `required|regex:/^\d{4}-\d{2}$/` | Periode payroll format `YYYY-MM` |
| `pegawai_id` | integer | ❌ | `nullable|exists:simpeg_pegawai,id` | ID Pegawai spesifik (opsional) |

```json
{
    "periode": "2026-09",
    "pegawai_id": 1
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Kalkulasi payroll periode 2026-09 berhasil diproses untuk 1 pegawai!",
    "data": {
        "periode": "2026-09",
        "total_pegawai": 1,
        "payrolls": [
            {
                "id": 1,
                "pegawai_id": 1,
                "periode_bulan_tahun": "2026-09",
                "gaji_pokok": 4500000,
                "tunjangan_tetap": 2050000,
                "total_biaya_transport": 1000000,
                "total_honor_sks": 600000,
                "total_sks_diampu": 12,
                "total_tunjangan_fungsional": 1000000,
                "total_tunjangan": 3650000,
                "total_potongan": 420000,
                "total_pph21": 120000,
                "total_bpjs": 250000,
                "gaji_bersih": 7730000,
                "status_transfer": "draft"
            }
        ]
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

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki hak akses (permission) untuk membuat kalkulasi payroll."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data pegawai atau master komponen gaji aktif tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "periode": [
            "Format periode harus YYYY-MM."
        ]
    }
}
```

---

## 4. POST /api/simpeg/payroll/submit-to-sikeu

> Mengajukan rekapan payroll satu periode ke modul SIKEU untuk diproses pencairannya.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

| Field | Type | Required | Validasi | Deskripsi |
|---|---|---|---|---|
| `periode` | string | ✅ | `required|string` | Periode payroll format `YYYY-MM` |

```json
{
    "periode": "2026-09"
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Pengajuan payroll periode 2026-09 (1 pegawai) berhasil dikirimkan ke modul SIKEU untuk proses pembayaran!"
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

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki hak akses (permission) untuk mengajukan payroll ke SIKEU."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data rekapan payroll pada periode tersebut tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "periode": [
            "Bidang periode wajib diisi."
        ]
    }
}
```

---

## 5. POST /api/simpeg/payroll/{id}/process-payment

> Mengeksekusi pencairan gaji pegawai dan menerbitkan jurnal akuntansi seimbang otomatis di SIKEU.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Pembayaran gaji Dr. Budi Santoso periode 2026-09 berhasil dibayarkan dan jurnal SIKEU diterbitkan!",
    "data": {
        "id": 1,
        "status_transfer": "paid",
        "tanggal_transfer": "2026-09-16T11:15:00.000000Z",
        "jurnal_id": 14,
        "pengeluaran_kampus_id": 8
    },
    "sikeu_journal": {
        "nomor_jurnal": "JRN-SIMPEG-20260916-0001",
        "jurnal_id": 14,
        "pengeluaran_kampus_id": 8,
        "total_debet": 8150000,
        "status": "POSTED_TO_SIKEU",
        "integrated_at": "2026-09-16T11:15:00+07:00"
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

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki hak akses (permission) untuk memproses pembayaran gaji di SIKEU."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data slip gaji tidak ditemukan."
}
```

---

## 6. GET /api/simpeg/payroll/komponen

> Daftar master komponen gaji fleksibel (Pendapatan & Potongan).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari nama atau kode komponen |
| `jenis` | string | ❌ | — | Filter jenis: `pendapatan`, `potongan` |
| `sort_by` | string | ❌ | `urutan` | Kolom pengurutan |
| `sort_order` | string | ❌ | `asc` | Arah pengurutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Data master komponen gaji berhasil dimuat",
    "data": [
        {
            "id": 1,
            "kode": "GP",
            "nama": "Gaji Pokok",
            "jenis": "pendapatan",
            "tipe_nilai": "nominal_tetap",
            "nilai_default": 4500000,
            "urutan": 1,
            "is_active": true
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 20,
        "total": 1,
        "last_page": 1
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

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki hak akses untuk melihat master komponen gaji."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Parameter query tidak valid.",
    "errors": {
        "per_page": [
            "Nilai per_page harus berupa angka."
        ]
    }
}
```

---

## 7. POST /api/simpeg/payroll/komponen

> Menambah master komponen gaji baru.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

| Field | Type | Required | Validasi | Deskripsi |
|---|---|---|---|---|
| `kode` | string | ✅ | `required|string|max:50|unique:simpeg_master_komponen_gaji,kode` | Kode unik komponen |
| `nama` | string | ✅ | `required|string|max:150` | Nama komponen |
| `jenis` | string | ✅ | `required|in:pendapatan,potongan` | Tipe jenis komponen |
| `tipe_nilai` | string | ✅ | `required|in:nominal_tetap,rumus,persentase` | Formula perhitungan |
| `nilai_default` | numeric | ❌ | `nullable|numeric|min:0` | Nilai bawaan |
| `is_active` | boolean | ❌ | `boolean` | Status aktif |
| `urutan` | integer | ❌ | `integer` | Urutan penataan |

```json
{
    "kode": "TJ_TRANSPORT",
    "nama": "Tunjangan Transportasi",
    "jenis": "pendapatan",
    "tipe_nilai": "nominal_tetap",
    "nilai_default": 500000,
    "is_active": true,
    "urutan": 2
}
```

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Komponen gaji 'Tunjangan Transportasi' berhasil ditambahkan.",
    "data": {
        "id": 2,
        "kode": "TJ_TRANSPORT",
        "nama": "Tunjangan Transportasi",
        "jenis": "pendapatan",
        "tipe_nilai": "nominal_tetap",
        "nilai_default": 500000,
        "is_active": true,
        "urutan": 2
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

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki hak akses untuk menambah komponen gaji."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "kode": [
            "Kode komponen gaji sudah terdaftar."
        ]
    }
}
```

---

## 8. PUT /api/simpeg/payroll/komponen/{id}

> Mengubah konfigurasi master komponen gaji.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

| Field | Type | Required | Validasi | Deskripsi |
|---|---|---|---|---|
| `nama` | string | ❌ | `sometimes|string|max:150` | Nama komponen |
| `nilai_default` | numeric | ❌ | `nullable|numeric|min:0` | Nilai bawaan |
| `is_active` | boolean | ❌ | `boolean` | Status aktif |

```json
{
    "nama": "Tunjangan Transportasi Jabatan",
    "nilai_default": 750000,
    "is_active": true
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Komponen gaji 'Tunjangan Transportasi Jabatan' berhasil diperbarui.",
    "data": {
        "id": 2,
        "kode": "TJ_TRANSPORT",
        "nama": "Tunjangan Transportasi Jabatan",
        "jenis": "pendapatan",
        "nilai_default": 750000,
        "is_active": true
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

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki hak akses untuk mengubah komponen gaji."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Komponen gaji tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "nama": [
            "Nama komponen gaji maksimal 150 karakter."
        ]
    }
}
```

---

## Catatan Khusus & Integritas Data
- **Posting SIKEU**: Pembayaran gaji otomatis menjurnal kas keluar dan beban gaji di modul SIKEU secara ganda (double-entry).
- **Soft Delete**: Data riwayat payroll menggunakan soft delete (`deleted_at`), bukti pembayaran gaji tidak dapat dihapus permanen demi audit perbankan dan perpajakan.
- **Password & Token**: Password, hashed password, dan token autentikasi tidak pernah dikembalikan dalam response API ini.

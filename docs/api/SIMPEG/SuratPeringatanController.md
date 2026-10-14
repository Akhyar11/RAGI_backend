# SuratPeringatanController

> **Modul**: SIMPEG (Surat Peringatan Keterlambatan Presensi Pegawai)
> **Base URL**: `/api/simpeg/surat-peringatan`
> **Autentikasi**: Bearer Token (Passport) — seluruh endpoint terproteksi `auth:api`
> **Dibuat**: 2026-10-14
> **Diperbarui**: 2026-10-14

Manajemen dan penyajian data Surat Peringatan (SP) kedisiplinan pegawai, diterbitkan secara otomatis oleh sistem saat siklus penggajian bulanan (payroll) tuntas dan terbayarkan (status `paid`) bagi pegawai yang jumlah keterlambatan presensinya melampaui parameter toleransi `max_late_per_month`.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/simpeg/surat-peringatan/my` | Mengambil daftar riwayat SP pegawai yang sedang login | ✅ Bearer |
| GET | `/api/simpeg/surat-peringatan/monitoring-tahunan` | Matriks monitoring SP tahunan (12 bulan) seluruh pegawai | ✅ Bearer |
| GET | `/api/simpeg/surat-peringatan` | Daftar seluruh SP pegawai untuk Admin SIMPEG (filter & pagination) | ✅ Bearer |
| GET | `/api/simpeg/surat-peringatan/{id}` | Rincian lengkap isi dokumen surat peringatan | ✅ Bearer |

---

## GET /api/simpeg/surat-peringatan/my

> Mengambil daftar riwayat surat peringatan yang dialamatkan khusus kepada pegawai yang sedang login (dosen atau tendik). Digunakan pada dashboard portal mandiri pegawai.

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
    "message": "Daftar surat peringatan pegawai berhasil diambil",
    "data": [
        {
            "id": 1,
            "pegawai_id": 12,
            "gaji_pegawai_id": 45,
            "presensi_periode_id": 8,
            "nomor_sp": "SP/SIMPEG/2026/10/0001",
            "jenis_sp": "SP 1",
            "judul": "Surat Peringatan Keterlambatan Presensi Periode Oktober 2026",
            "periode_bulan_tahun": "2026-10",
            "total_terlambat": 6,
            "batas_maksimal": 5,
            "keterangan": "Surat peringatan diterbitkan secara otomatis oleh sistem kepegawaian karena akumulasi keterlambatan presensi Anda pada periode Oktober 2026 tercatat sebanyak 6 kali, melebihi batas toleransi maksimal yang ditetapkan kampus (5 kali). Harap meningkatkan kedisiplinan dan kepatuhan jam kerja.",
            "tanggal_terbit": "2026-10-14",
            "status": "aktif",
            "created_at": "2026-10-14T09:30:00.000000Z",
            "updated_at": "2026-10-14T09:30:00.000000Z"
        }
    ]
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

---

## GET /api/simpeg/surat-peringatan

> Mengambil daftar seluruh surat peringatan untuk pengelola kepegawaian (Admin SIMPEG) dilengkapi filter pencarian nama/NIP/nomor SP, filter periode, filter status, dan pagination.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Kata kunci pencarian (Nomor SP, Judul, NIP, atau Nama Pegawai) |
| `periode` | string | ❌ | — | Filter periode bulan (format: `YYYY-MM`) |
| `status` | string | ❌ | — | Filter status SP (`aktif`, `selesai`, `diarsipkan`) |
| `pegawai_id` | integer | ❌ | — | Filter berdasarkan ID pegawai spesifik |
| `sort_by` | string | ❌ | `tanggal_terbit` | Kolom pengurutan (`id`, `nomor_sp`, `tanggal_terbit`, `total_terlambat`) |
| `sort_order` | string | ❌ | `desc` | Arah pengurutan (`asc`, `desc`) |
| `per_page` | integer | ❌ | 15 | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | 1 | Nomor halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Daftar surat peringatan berhasil diambil",
    "data": [
        {
            "id": 1,
            "pegawai_id": 12,
            "gaji_pegawai_id": 45,
            "presensi_periode_id": 8,
            "nomor_sp": "SP/SIMPEG/2026/10/0001",
            "jenis_sp": "SP 1",
            "judul": "Surat Peringatan Keterlambatan Presensi Periode Oktober 2026",
            "periode_bulan_tahun": "2026-10",
            "total_terlambat": 6,
            "batas_maksimal": 5,
            "keterangan": "Surat peringatan diterbitkan secara otomatis...",
            "tanggal_terbit": "2026-10-14",
            "status": "aktif",
            "pegawai": {
                "id": 12,
                "nama_lengkap": "Naufal Hanif Putra Aji",
                "nip": "202603001",
                "unit_kerja": {
                    "id": 1,
                    "nama_unit": "Rektorat & Pimpinan Universitas"
                }
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
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
    "message": "Anda tidak memiliki hak akses untuk melihat data surat peringatan."
}
```

---

## GET /api/simpeg/surat-peringatan/{id}

> Mengambil rincian lengkap dari dokumen surat peringatan tertentu berdasarkan ID. Dapat diakses oleh pegawai bersangkutan maupun Admin SIMPEG.

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
    "message": "Rincian surat peringatan berhasil diambil",
    "data": {
        "id": 1,
        "pegawai_id": 12,
        "gaji_pegawai_id": 45,
        "presensi_periode_id": 8,
        "nomor_sp": "SP/SIMPEG/2026/10/0001",
        "jenis_sp": "SP 1",
        "judul": "Surat Peringatan Keterlambatan Presensi Periode Oktober 2026",
        "periode_bulan_tahun": "2026-10",
        "total_terlambat": 6,
        "batas_maksimal": 5,
        "keterangan": "Surat peringatan diterbitkan secara otomatis oleh sistem kepegawaian...",
        "tanggal_terbit": "2026-10-14",
        "status": "aktif",
        "pegawai": {
            "id": 12,
            "nama_lengkap": "Naufal Hanif Putra Aji",
            "nip": "202603001",
            "unit_kerja": {
                "id": 1,
                "nama_unit": "Rektorat & Pimpinan Universitas"
            }
        }
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
    "message": "Anda tidak memiliki hak akses untuk melihat rincian surat peringatan ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Surat Peringatan tidak ditemukan."
}
```

---

## GET /api/simpeg/surat-peringatan/monitoring-tahunan

> Mengambil matriks evaluasi dan monitoring Surat Peringatan (SP) serta keterlambatan kehadiran presensi pegawai dalam format kalender 12 bulan (Januari s/d Desember) untuk Admin SIMPEG. Dilengkapi indikator status warna visual, deteksi pegawai rawan/sering SP, filter risiko, dan statistik tahunan.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tahun` | integer | ❌ | Tahun berjalan (mis. `2026`) | Tahun evaluasi kalender 12 bulan |
| `search` | string | ❌ | — | Kata kunci pencarian nama atau NIP pegawai |
| `unit_kerja_id` | integer | ❌ | — | Filter berdasarkan unit kerja pegawai |
| `risk_level` | string | ❌ | `all` | Filter tingkat risiko (`all`, `rawan`, `perhatian`, `disiplin`, `has_sp`) |
| `sort_by` | string | ❌ | `total_sp` | Kolom pengurutan (`total_sp`, `total_terlambat`, `nama_lengkap`, `nip`) |
| `sort_order` | string | ❌ | `desc` | Arah pengurutan (`asc`, `desc`) |
| `per_page` | integer | ❌ | 15 | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | 1 | Nomor halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Matriks monitoring surat peringatan tahun 2026 berhasil diambil",
    "data": [
        {
            "pegawai_id": 12,
            "nip": "202603001",
            "nama_lengkap": "Naufal Hanif Putra Aji",
            "unit_kerja": "Rektorat & Pimpinan Universitas",
            "months": {
                "01": {
                    "month": 1,
                    "month_code": "01",
                    "month_name": "Jan",
                    "periode": "2026-01",
                    "has_data": true,
                    "is_future": false,
                    "total_terlambat": 1,
                    "batas_maksimal": 5,
                    "has_sp": false,
                    "sp_id": null,
                    "nomor_sp": null,
                    "jenis_sp": null,
                    "judul_sp": null,
                    "status_sp": null,
                    "status_color": "warning"
                },
                "10": {
                    "month": 10,
                    "month_code": "10",
                    "month_name": "Okt",
                    "periode": "2026-10",
                    "has_data": true,
                    "is_future": false,
                    "total_terlambat": 6,
                    "batas_maksimal": 5,
                    "has_sp": true,
                    "sp_id": 1,
                    "nomor_sp": "SP/SIMPEG/2026/10/0001",
                    "jenis_sp": "SP 1",
                    "judul_sp": "Surat Peringatan Keterlambatan Presensi Periode Oktober 2026",
                    "status_sp": "aktif",
                    "status_color": "danger"
                }
            },
            "summary": {
                "total_sp": 1,
                "total_terlambat": 7,
                "risk_level": "perhatian",
                "risk_label": "Pernah SP (1x)"
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1,
        "tahun": 2026,
        "max_late_per_month": 5,
        "stats": {
            "total_pegawai": 24,
            "pegawai_pernah_sp": 1,
            "total_sp_terbit": 1
        }
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
    "message": "Anda tidak memiliki hak akses untuk melihat monitoring surat peringatan."
}
```


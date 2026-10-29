# AnalitikPegawaiController

> **Modul**: SIMPEG / **Base URL**: /api/simpeg/analitik / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat**: 2026-10-29

Modul ini mengelola analitik data kepegawaian, distribusi jabatan fungsional akademik dosen (JAD), filter rentang tanggal TMT, status sertifikasi dosen, serta ekspor data rekapitulasi ke file Excel (.xlsx).

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth / Permission |
|---|---|---|---|
| GET | `/api/simpeg/analitik/ringkasan` | Mengambil ringkasan metrik statistik agregat | ✅ Bearer (`simpeg.pegawai.read`) |
| GET | `/api/simpeg/analitik/pegawai` | Daftar pegawai terfilter dengan pagination | ✅ Bearer (`simpeg.pegawai.read`) |
| GET | `/api/simpeg/analitik/export-excel` | Mengunduh berkas rekapitulasi Excel (.xlsx) | ✅ Bearer (`simpeg.pegawai.read`) |

---

## 1. GET /api/simpeg/analitik/ringkasan

> Mengambil ringkasan metrik statistik agregat data kepegawaian berdasarkan filter yang dipilih.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Pencarian nama, NIP, NIDN, atau nomor SK |
| `tmt_start` | date | ❌ | — | Batas awal rentang tanggal TMT JAD (`YYYY-MM-DD`) |
| `tmt_end` | date | ❌ | — | Batas akhir rentang tanggal TMT JAD (`YYYY-MM-DD`) |
| `jabatan_fungsional_id` | integer | ❌ | — | Filter ID jabatan fungsional |
| `unit_kerja_id` | integer | ❌ | — | Filter ID unit kerja / program studi |
| `jenis_pegawai` | string | ❌ | — | Filter jenis pegawai: `dosen` / `tendik` |
| `status_kepegawaian` | string | ❌ | — | Filter status kepegawaian (tetap, kontrak, dll.) |
| `is_serdos` | boolean | ❌ | — | Filter status kepemilikan Serdos (`true`/`false`) |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Ringkasan analitik pegawai berhasil dimuat.",
    "data": {
        "total_pegawai": 139,
        "total_dosen": 139,
        "total_tendik": 0,
        "kategori_jad": {
            "tenaga_pengajar": 15,
            "asisten_ahli": 60,
            "lektor": 45,
            "lektor_kepala": 15,
            "guru_besar": 4,
            "belum_ada": 0
        },
        "jad_detail": [
            {
                "id": 1,
                "nama": "Asisten Ahli (100)",
                "angka_kredit": 100,
                "total": 35
            },
            {
                "id": 6,
                "nama": "Lektor Kepala (400)",
                "angka_kredit": 400,
                "total": 15
            }
        ],
        "unit_kerja_distribusi": [
            {
                "id": 1,
                "nama": "Teknik Informatika",
                "kode": "TI",
                "tipe": "prodi",
                "total": 24
            }
        ],
        "serdos": {
            "sudah_serdos": 85,
            "belum_serdos": 54
        },
        "periode_tmt": {
            "start": "2024-01-01",
            "end": "2025-12-31"
        }
    }
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

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki hak akses untuk melihat analitik pegawai."
}
```

---

## 2. GET /api/simpeg/analitik/pegawai

> Mengambil daftar pegawai terfilter dengan pagination untuk preview tabel.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Pencarian teks (nama, NIP, NIDN, No SK) |
| `tmt_start` | date | ❌ | — | Batas awal tanggal TMT JAD (`YYYY-MM-DD`) |
| `tmt_end` | date | ❌ | — | Batas akhir tanggal TMT JAD (`YYYY-MM-DD`) |
| `jabatan_fungsional_id` | integer | ❌ | — | Filter ID jabatan fungsional |
| `unit_kerja_id` | integer | ❌ | — | Filter ID unit kerja / program studi |
| `jenis_pegawai` | string | ❌ | — | Filter `dosen` / `tendik` |
| `status_kepegawaian` | string | ❌ | — | Filter status kepegawaian |
| `is_serdos` | boolean | ❌ | — | Filter Serdos |
| `sort_by` | string | ❌ | `created_at` | Urutan kolom (`created_at`, `nama`, `nip`) |
| `sort_order` | string | ❌ | `desc` | Arah urutan (`asc`, `desc`) |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Data analitik pegawai berhasil diambil.",
    "data": [
        {
            "id": 5,
            "nama_lengkap": "Fauzi Sahono",
            "nama_gelar": "Fauzi Sahono, M.Kom",
            "nip": "198801012015011002",
            "nidn": "0601018801",
            "jenis_pegawai": "dosen",
            "status_kepegawaian": "tetap",
            "status_aktif": "aktif",
            "is_active": true,
            "unit_kerja": {
                "id": 1,
                "nama": "Teknik Informatika",
                "kode": "TI",
                "tipe": "prodi"
            },
            "jabatan_fungsional": {
                "id": 1,
                "nama": "Asisten Ahli (100)",
                "angka_kredit": 100
            },
            "jad_aktif": {
                "tmt": "2026-01-15",
                "sk_nomor": "SK/123/123/2026",
                "sk_tanggal": "2026-01-15",
                "file_sk_url": "https://api.domain.ac.id/api/files/view?path=..."
            },
            "is_serdos": true,
            "nomor_serdos": "1234567890"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1,
        "from": 1,
        "to": 1,
        "filters": {
            "search": null,
            "tmt_start": "2026-01-01",
            "tmt_end": "2026-12-31"
        }
    }
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

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki hak akses untuk melihat data analitik pegawai."
}
```

---

## 3. GET /api/simpeg/analitik/export-excel

> Mengunduh berkas laporan rekapitulasi data pegawai dan JAD dalam format Excel (.xlsx) dengan styling resmi.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |

### Query Parameters
Menerima seluruh parameter filter yang sama dengan endpoint list (`search`, `tmt_start`, `tmt_end`, `jabatan_fungsional_id`, `unit_kerja_id`, `jenis_pegawai`, `status_kepegawaian`, `is_serdos`, `sort_by`, `sort_order`).

### Response Sukses (200 OK)
Mengembalikan binary file stream dengan headers:
- `Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`
- `Content-Disposition: attachment; filename="Analitik_Pegawai_YYYYMMDD_HHMMSS.xlsx"`

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki hak akses untuk mengekspor data analitik pegawai."
}
```

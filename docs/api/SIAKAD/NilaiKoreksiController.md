# NilaiKoreksiController

> **Modul**: SIAKAD
> **Base URL**: `/api/v1/siakad`
> **Autentikasi**: Bearer token (Passport)
> **Diperbarui**: 2026-11-09

Mengelola pengajuan koreksi nilai final untuk penilaian konvensional, komponen OBE, dan capaian CPMK OBE. Nilai aktif tidak berubah saat diajukan; perubahan hanya diterapkan setelah disetujui reviewer yang memiliki kewenangan pada program studi terkait.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/siakad/perkuliahan/nilai-koreksi` | Daftar pengajuan milik dosen atau dalam lingkup reviewer | ✅ |
| POST | `/api/v1/siakad/perkuliahan/nilai-koreksi` | Mengajukan koreksi nilai final | ✅ `siakad.nilai.koreksi.create` |
| PATCH | `/api/v1/siakad/perkuliahan/nilai-koreksi/{id}/approve` | Menyetujui dan menerapkan koreksi | ✅ reviewer prodi |
| PATCH | `/api/v1/siakad/perkuliahan/nilai-koreksi/{id}/reject` | Menolak pengajuan dengan alasan | ✅ reviewer prodi |

Semua endpoint memerlukan header:

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ untuk POST/PATCH |

## GET `/api/v1/siakad/perkuliahan/nilai-koreksi`

Reviewer melihat pengajuan dalam lingkup program studi yang berwenang. Dosen melihat pengajuannya sendiri.
Setiap item juga menyertakan relasi `krs_detail` (KRS/mahasiswa dan kelas/mata kuliah), target nilai OBE jika ada, serta `diajukan_oleh` dan `diputuskan_oleh`.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari alasan/catatan, nama/NIM mahasiswa, atau kode/nama mata kuliah |
| `status` | string | ❌ | — | `menunggu`, `disetujui`, atau `ditolak` |
| `sort_by` | string | ❌ | `created_at` | Kolom urut: `id`, `created_at`, `updated_at`, `status`, atau `diputuskan_at` |
| `sort_order` | string | ❌ | `desc` | Arah urut: `asc` atau `desc`; nilai lain memakai `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman, maksimum `100` |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar pengajuan koreksi nilai berhasil diambil.",
    "data": [
        {
            "id": 18,
            "krs_detail_id": 41,
            "jenis_nilai": "konvensional",
            "nilai_mahasiswa_id": 12,
            "nilai_komponen_mahasiswa_id": null,
            "ketercapaian_cpmk_mahasiswa_id": null,
            "nilai_lama": {
                "nilai_harian": "70.00",
                "nilai_uts": "70.00",
                "nilai_uas": "70.00",
                "nilai_praktik": "70.00"
            },
            "nilai_baru": {
                "nilai_uas": 90
            },
            "alasan_koreksi": "Perbaikan hasil input ujian akhir.",
            "status": "menunggu",
            "diajukan_oleh": 7,
            "diputuskan_oleh": null,
            "diputuskan_at": null,
            "catatan_reviewer": null,
            "created_at": "2026-11-09T16:00:00.000000Z",
            "updated_at": "2026-11-09T16:00:00.000000Z"
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
            "status": null,
            "sort_by": "created_at",
            "sort_order": "desc"
        }
    }
}
```

## POST `/api/v1/siakad/perkuliahan/nilai-koreksi`

Hanya dosen yang memiliki izin pengajuan dan benar-benar ditugaskan pada kelas tersebut dapat mengajukan koreksi. `nilai_baru` berisi field yang dikoreksi saja.

### Request Body — Nilai Konvensional

```json
{
    "jenis_nilai": "konvensional",
    "nilai_mahasiswa_id": 12,
    "nilai_baru": {
        "nilai_uas": 90
    },
    "alasan_koreksi": "Perbaikan hasil input ujian akhir."
}
```

Field konvensional yang dapat dikoreksi: `nilai_harian`, `nilai_uts`, `nilai_uas`, `nilai_praktik` (angka 0–100).

### Request Body — Komponen OBE

```json
{
    "jenis_nilai": "komponen_obe",
    "nilai_komponen_mahasiswa_id": 27,
    "nilai_baru": {
        "nilai_angka": 90
    },
    "alasan_koreksi": "Perbaikan hasil penilaian proyek."
}
```

### Request Body — Capaian CPMK OBE Penuh

```json
{
    "jenis_nilai": "cpmk_obe",
    "ketercapaian_cpmk_mahasiswa_id": 8,
    "nilai_baru": {
        "nilai_angka": 85
    },
    "alasan_koreksi": "Koreksi evaluasi capaian CPMK."
}
```

`alasan_koreksi` wajib 10–1000 karakter. Pengajuan hanya dapat dibuat untuk nilai final; satu target tidak dapat memiliki lebih dari satu pengajuan berstatus menunggu.

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Pengajuan koreksi nilai berhasil dikirim untuk ditinjau.",
    "data": {
        "id": 18,
        "krs_detail_id": 41,
        "jenis_nilai": "konvensional",
        "nilai_mahasiswa_id": 12,
        "nilai_lama": {
            "nilai_harian": "70.00",
            "nilai_uts": "70.00",
            "nilai_uas": "70.00",
            "nilai_praktik": "70.00"
        },
        "nilai_baru": {
            "nilai_uas": 90
        },
        "alasan_koreksi": "Perbaikan hasil input ujian akhir.",
        "status": "menunggu",
        "diajukan_oleh": 7,
        "diputuskan_oleh": null,
        "diputuskan_at": null,
        "catatan_reviewer": null,
        "created_at": "2026-11-09T16:00:00.000000Z",
        "updated_at": "2026-11-09T16:00:00.000000Z"
    }
}
```

## PATCH `/api/v1/siakad/perkuliahan/nilai-koreksi/{id}/approve`

Reviewer harus memiliki izin `siakad.nilai.koreksi.approve` dan kewenangan pada program studi sasaran. Pengaju tidak dapat memutuskan pengajuannya sendiri. Nilai akhir, nilai huruf/mutu, capaian CPMK terkait, dan KHS dihitung ulang setelah persetujuan. KHS yang terkunci harus dibuka melalui alur akademik sebelum koreksi dapat disetujui.

### Request Body

```json
{
    "catatan_reviewer": "Disetujui setelah verifikasi dokumen."
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Koreksi nilai disetujui dan nilai terkait berhasil disinkronkan.",
    "data": {
        "id": 18,
        "krs_detail_id": 41,
        "jenis_nilai": "konvensional",
        "nilai_mahasiswa_id": 12,
        "nilai_komponen_mahasiswa_id": null,
        "ketercapaian_cpmk_mahasiswa_id": null,
        "nilai_lama": {
            "nilai_harian": "70.00",
            "nilai_uts": "70.00",
            "nilai_uas": "70.00",
            "nilai_praktik": "70.00"
        },
        "nilai_baru": {
            "nilai_uas": 90
        },
        "alasan_koreksi": "Perbaikan hasil input ujian akhir.",
        "status": "disetujui",
        "diajukan_oleh": 7,
        "diputuskan_oleh": 3,
        "diputuskan_at": "2026-11-09T16:30:00.000000Z",
        "catatan_reviewer": "Disetujui setelah verifikasi dokumen.",
        "created_at": "2026-11-09T16:00:00.000000Z",
        "updated_at": "2026-11-09T16:30:00.000000Z"
    }
}
```

## PATCH `/api/v1/siakad/perkuliahan/nilai-koreksi/{id}/reject`

Menolak pengajuan tanpa mengubah nilai aktif. Catatan reviewer wajib 5–1000 karakter.

### Request Body

```json
{
    "catatan_reviewer": "Dokumen pendukung belum mencukupi."
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Pengajuan koreksi nilai ditolak.",
    "data": {
        "id": 18,
        "krs_detail_id": 41,
        "jenis_nilai": "konvensional",
        "nilai_mahasiswa_id": 12,
        "nilai_komponen_mahasiswa_id": null,
        "ketercapaian_cpmk_mahasiswa_id": null,
        "nilai_lama": {
            "nilai_harian": "70.00",
            "nilai_uts": "70.00",
            "nilai_uas": "70.00",
            "nilai_praktik": "70.00"
        },
        "nilai_baru": {
            "nilai_uas": 90
        },
        "alasan_koreksi": "Perbaikan hasil input ujian akhir.",
        "status": "ditolak",
        "diajukan_oleh": 7,
        "diputuskan_oleh": 3,
        "diputuskan_at": "2026-11-09T16:30:00.000000Z",
        "catatan_reviewer": "Dokumen pendukung belum mencukupi.",
        "created_at": "2026-11-09T16:00:00.000000Z",
        "updated_at": "2026-11-09T16:30:00.000000Z"
    }
}
```

## Response Error

**400 Bad Request**

```json
{
    "status": "error",
    "message": "Permintaan tidak valid atau parameter tidak sesuai."
}
```

**401 Unauthorized**

```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden** — tidak memiliki izin, dosen bukan pengampu kelas, reviewer di luar lingkup prodi, atau pengaju mencoba memutuskan pengajuannya sendiri.

```json
{
    "status": "error",
    "message": "Hanya dosen yang benar-benar ditugaskan pada kelas ini yang dapat mengajukan koreksi."
}
```

**404 Not Found**

```json
{
    "status": "error",
    "message": "KoreksiNilaiMahasiswa tidak ditemukan."
}
```

**422 Unprocessable Entity** — input tidak valid, nilai bukan final, pengajuan sudah diputuskan/menunggu, nilai atau mode penilaian berubah sejak pengajuan, atau KHS masih terkunci.

```json
{
    "status": "error",
    "message": "Koreksi hanya dapat diajukan untuk nilai akhir yang sudah dipublikasikan.",
    "errors": {
        "nilai": [
            "Koreksi hanya dapat diajukan untuk nilai akhir yang sudah dipublikasikan."
        ]
    }
}
```

Semua pengajuan dan perubahan nilai dicatat dalam audit log. Data nilai dan alasan koreksi disimpan di SIAKAD RAG; endpoint ini tidak menyinkronkan perubahan ke sistem akademik legacy.

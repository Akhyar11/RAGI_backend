# PerubahanProdiController

> **Modul**: SIKEU
> **Base URL**: `/api/v1/sikeu/perubahan-prodi`
> **Autentikasi**: Bearer Token (Passport) ✅
> **Dibuat**: 2026-10-12
> **Diperbarui**: 2026-10-12
> **Status**: ⚠️ DEPRECATED — alur kanonis pindah ke **SIAKAD** (`/api/v1/siakad/pindah-prodi`, lihat `docs/api/SIAKAD/PindahProdiController.md`). Endpoint di bawah dipertahankan hanya untuk darurat/backdate. Pengajuan baru WAJIB lewat SIAKAD agar mutasi prodi, sign-off keuangan, dan proyeksi penagihan (via event `Siakad/MahasiswaPindahProdi`) berjalan berurutan.

Histori perpindahan program studi / perubahan jenis pendaftaran.
Tagihan lama TIDAK diubah; perubahan hanya menentukan konteks efektif
(TA + semester) untuk penagihan berikutnya.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/perubahan-prodi` | Daftar pengajuan + pagination | ✅ |
| GET | `/api/v1/sikeu/perubahan-prodi/dampak?mahasiswa_id=` | Preview tagihan aktif terdampak | ✅ |
| POST | `/api/v1/sikeu/perubahan-prodi` | Buat pengajuan pending | ✅ |
| GET | `/api/v1/sikeu/perubahan-prodi/{id}` | Detail + dampak | ✅ |
| POST | `/api/v1/sikeu/perubahan-prodi/{id}/approve` | Setujui | ✅ |
| POST | `/api/v1/sikeu/perubahan-prodi/{id}/reject` | Tolak | ✅ |
| POST | `/api/v1/sikeu/perubahan-prodi/{id}/apply` | Terapkan (SIAKAD prodi + versioning tipe) | ✅ |

---

## Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | Hanya POST |

---

## GET /api/v1/sikeu/perubahan-prodi

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari alasan / No. SK |
| `status` | string | ❌ | `all` | `pending\|approved\|applied\|rejected\|cancelled` |
| `mahasiswa_id` | integer | ❌ | — | Filter milik mahasiswa |
| `sort_by` | string | ❌ | `created_at` | `id\|created_at\|status` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses (200, pagination)

```json
{
    "status": "success",
    "message": "Daftar perubahan prodi berhasil dimuat",
    "data": [
        {
            "id": 1,
            "mahasiswa_id": 101,
            "status": "pending"
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

## POST /api/v1/sikeu/perubahan-prodi

### Request Body

```json
{
    "mahasiswa_id": "integer, required, exists:siakad_mahasiswa,id",
    "new_program_studi_id": "integer, required, exists:siakad_program_studi,id",
    "tahun_akademik_id": "integer, nullable",
    "semester_efektif": "integer, nullable (1-14)",
    "tanggal_efektif": "date, nullable",
    "jenis_pendaftaran_lama": "string, nullable",
    "jenis_pendaftaran_baru": "string, nullable",
    "alasan": "string, required, min:10",
    "referensi_surat": "string, nullable (No. SK)",
    "dokumen_path": "string, nullable (path relatif, terisi otomatis bila upload `dokumen`)",
    "dokumen": "file, nullable (pdf/jpg/jpeg/png, maks 5MB, multipart/form-data; disimpan privat via FileStorageService)"
}
```

### Response Sukses (201)

```json
{
    "status": "success",
    "message": "Pengajuan perubahan prodi berhasil dibuat.",
    "data": { "id": 1, "status": "pending" }
}
```

### Catatan Tambahan

- `apply` mengupdate `siakad_mahasiswa.program_studi_id` + versioning
  `sikeu_mahasiswa_tipe_tagihan` (version+1, efektif TA/semester, link perubahan).
- Penagihan masa depan (generate tagihan) wajib memakai prodi efektif
  per TA/semester mulai periode efektif pengajuan yang sudah `applied`.

---

## Response Error

**400 Bad Request**
```json
{
    "status": "error",
    "message": "Hanya pengajuan yang sudah disetujui yang bisa diterapkan."
}
```

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
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "PerubahanProdi tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Validasi pengajuan gagal.",
    "errors": {
        "alasan": ["Alasan wajib diisi minimal 10 karakter"]
    }
}
```

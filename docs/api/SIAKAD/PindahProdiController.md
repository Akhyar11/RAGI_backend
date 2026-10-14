# PindahProdiController

> **Modul**: SIAKAD (pemilik mutasi prodi)
> **Base URL**: `/api/v1/siakad/pindah-prodi`
> **Autentikasi**: Bearer Token (Passport) ✅
> **Dibuat**: 2026-10-12
> **Diperbarui**: 2026-10-12

Pengajuan pindah program studi. SIAKAD adalah satu-satunya penulis mutasi
`program_studi_id`. Persetujuan dua sisi: **sign-off keuangan** (cek tunggakan
SIKEU) wajib sebelum **approval akademik**. Saat `apply`, SIAKAD mengubah prodi
mahasiswa lalu dispatch event `Siakad/MahasiswaPindahProdi` yang didengar SIKEU
(`CatatKonteksPenagihanPindahProdi`, queued) untuk mencatat konteks penagihan
efektif. Pengajuan baru WAJIB lewat endpoint ini, bukan via SIKEU
(`docs/api/SIKEU/PerubahanProdiController.md` berstatus deprecated).

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/siakad/pindah-prodi` | Daftar pengajuan + pagination/filter | ✅ |
| GET | `/api/v1/siakad/pindah-prodi/dampak?mahasiswa_id=` | Pratinjau tunggakan SIKEU (bahan sign-off) | ✅ |
| POST | `/api/v1/siakad/pindah-prodi` | Buat pengajuan pending | ✅ |
| GET | `/api/v1/siakad/pindah-prodi/{id}` | Detail pengajuan | ✅ |
| POST | `/api/v1/siakad/pindah-prodi/{id}/cek-keuangan` | Sign-off keuangan (`layak`/`ada_tunggakan`) | ✅ |
| POST | `/api/v1/siakad/pindah-prodi/{id}/approve` | Approval akademik (syarat: sudah dicek keuangan) | ✅ |
| POST | `/api/v1/siakad/pindah-prodi/{id}/reject` | Tolak | ✅ |
| POST | `/api/v1/siakad/pindah-prodi/{id}/apply` | Terapkan mutasi + dispatch event ke SIKEU | ✅ |

---

## Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | Hanya POST |

---

## GET /api/v1/siakad/pindah-prodi

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari alasan / No. SK / NIM / nama |
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
    "message": "Daftar pindah prodi berhasil dimuat",
    "data": [
        {
            "id": 1,
            "mahasiswa_id": 101,
            "status": "pending",
            "hasil_cek_keuangan": "layak"
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

## POST /api/v1/siakad/pindah-prodi

### Request Body

```json
{
    "mahasiswa_id": "integer, required, exists:siakad_mahasiswa,id",
    "new_program_studi_id": "integer, required, exists:siakad_program_studi,id",
    "tahun_akademik_id": "integer, nullable, exists:siakad_tahun_akademik,id",
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
    "message": "Pengajuan pindah prodi berhasil dibuat. Menunggu sign-off keuangan.",
    "data": { "id": 1, "status": "pending" }
}
```

---

## POST /api/v1/siakad/pindah-prodi/{id}/cek-keuangan

### Request Body

```json
{
    "hasil": "enum: layak|ada_tunggakan, required",
    "catatan": "string, nullable"
}
```

Gunakan `GET /dampak?mahasiswa_id=` untuk melihat tagihan aktif SIKEU
sebelum mengisi hasil cek.

---

## POST /api/v1/siakad/pindah-prodi/{id}/apply

Menerapkan mutasi `program_studi_id` + dispatch
`Siakad/MahasiswaPindahProdi`. Listener SIKEU mencatat proyeksi
(`sikeu_mahasiswa_perubahan_prodi`, status `applied`) + versioning
`sikeu_mahasiswa_tipe_tagihan`. Tagihan lama tidak diubah; redistribusi
alokasi lewat menu Koreksi Alokasi SIKEU. Listener SPMB menyelaraskan
`program_studi_id` pada pendaftaran asal (riwayat jalur/gelombang/status
seleksi utuh; jejak di audit log SPMB) sehingga laporan SPMB selaras
dengan SIAKAD. Sinkron SPMB berjalan dalam request yang sama; proyeksi
SIKEU via antrian `sikeu` (pastikan worker berjalan di produksi).

### Response Error

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Hanya pengajuan yang sudah disetujui yang bisa diterapkan."
}
```

### Catatan Tambahan

- Seluruh transisi tercatat di audit log (`SIAKAD/siakad_mahasiswa_pindah_prodi`) via Observer.
- Event diproses di queue `sikeu`; kegagalan listener menandai job failed tanpa menggagalkan response apply.

---

## Response Error

**400 Bad Request** — urutan alur dilanggar
```json
{
    "status": "error",
    "message": "Approval akademik mensyaratkan sign-off keuangan terlebih dahulu."
}
```

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden** — tanpa izin
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
    "message": "PindahProdi tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Validasi pengajuan gagal.",
    "errors": {
        "new_program_studi_id": ["The selected new program studi id is invalid."],
        "alasan": ["Alasan wajib diisi minimal 10 karakter"]
    }
}
```

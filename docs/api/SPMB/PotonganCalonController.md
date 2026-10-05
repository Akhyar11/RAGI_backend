# PotonganCalonController

> **Modul**: SPMB / **Base URL**: `/api/spmb` / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-10-01

Mengelola **potongan biaya kustom per calon mahasiswa** (bukan per jalur/prodi). Setiap potongan menunjuk **tepat satu komponen biaya** (`spmb_master_komponen_biaya`). Data keputusan potongan berada di modul SPMB; SIKEU hanya menerima hasilnya saat tagihan (pendaftaran/daftar ulang) dibuat. Semua endpoint memerlukan permission `spmb.manage`.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/spmb/pendaftaran/{id}/potongan` | Daftar potongan milik satu pendaftaran (berpaginasi) | ✅ Admin SPMB |
| POST | `/api/spmb/pendaftaran/{id}/potongan` | Tambah potongan untuk calon | ✅ Admin SPMB |
| PUT | `/api/spmb/potongan-calon/{id}` | Perbarui potongan | ✅ Admin SPMB |
| DELETE | `/api/spmb/potongan-calon/{id}` | Hapus potongan (soft delete) | ✅ Admin SPMB |

> Satu calon hanya boleh punya **satu potongan per komponen** (unique `pendaftaran_id` + `komponen_biaya_id`).

---

## [GET] /api/spmb/pendaftaran/{id}/potongan

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari `nama_potongan` / `nomor_sk` |
| `status` | string | ❌ | — | `draft`, `aktif`, `dibatalkan` |
| `tahap` | string | ❌ | — | `pendaftaran`, `daftar_ulang`, `keduanya` |
| `sort_by` | string | ❌ | `created_at` | `created_at`, `updated_at`, `nama_potongan`, `nilai_potongan`, `id` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data potongan calon berhasil dimuat.",
    "data": [
        {
            "id": 1,
            "pendaftaran_id": 10,
            "komponen_biaya_id": 7,
            "nama_komponen": "Dana Pengembangan Institusi",
            "nama_potongan": "Keringanan Rektorat",
            "tipe_potongan": "persen",
            "nilai_potongan": "50.00",
            "tahap": "daftar_ulang",
            "nomor_sk": "SK/012/2026",
            "keterangan": "Keringanan khusus atas persetujuan Rektor",
            "berlaku_mulai": "2026-10-01",
            "berlaku_sampai": null,
            "status": "aktif",
            "komponen_biaya": { "id": 7, "kode": "DPI", "nama": "Dana Pengembangan Institusi" },
            "pembuat": { "id": 5, "name": "Admin SPMB", "username": "admin.spmb" }
        }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1 },
    "filters": { "search": null, "status": null, "tahap": null, "sort_by": "created_at", "sort_order": "desc" }
}
```

---

## [POST] /api/spmb/pendaftaran/{id}/potongan

### Request Body

```json
{
    "komponen_biaya_id": "integer, required, exists:spmb_master_komponen_biaya,id (unik per pendaftaran)",
    "nama_potongan": "string, required, max 150",
    "tipe_potongan": "enum: nominal | persen",
    "nilai_potongan": "numeric, required, min 0 (persen maks 100)",
    "tahap": "enum: pendaftaran | daftar_ulang | keduanya",
    "nomor_sk": "string, nullable, max 100",
    "keterangan": "string, nullable",
    "berlaku_mulai": "date, nullable (YYYY-MM-DD)",
    "berlaku_sampai": "date, nullable, after_or_equal:berlaku_mulai",
    "status": "enum: draft | aktif | dibatalkan (default aktif)"
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Potongan calon berhasil ditambahkan.",
    "data": {
        "id": 1,
        "pendaftaran_id": 10,
        "komponen_biaya_id": 7,
        "nama_komponen": "Dana Pengembangan Institusi",
        "nama_potongan": "Keringanan Rektorat",
        "tipe_potongan": "persen",
        "nilai_potongan": "50.00",
        "tahap": "daftar_ulang",
        "status": "aktif"
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
        "komponen_biaya_id": ["The komponen biaya id has already been taken."],
        "nilai_potongan": ["Potongan persen maksimal 100."]
    }
}
```

---

## [PUT] /api/spmb/potongan-calon/{id}

> Body mengikuti aturan POST (semua field `sometimes`). `pendaftaran_id` tidak dapat diubah.

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Potongan calon berhasil diperbarui.",
    "data": { "id": 1, "nama_potongan": "Keringanan Rektorat (Revisi)", "nilai_potongan": "60.00" }
}
```

**404 Not Found**
```json
{ "status": "error", "message": "No query results for model [App\\Models\\Spmb\\PotonganCalon] 99." }
```

---

## [DELETE] /api/spmb/potongan-calon/{id}

> Soft delete (`deleted_at` diisi).

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Potongan calon berhasil dihapus (soft delete).",
    "data": { "deleted_at": "2026-10-01T10:00:00.000000Z" }
}
```

---

### Response Error Umum (berlaku untuk semua endpoint)

**401 Unauthorized**
```json
{ "status": "error", "message": "Unauthenticated." }
```

**403 Forbidden** (tanpa permission `spmb.potongan.read/create/update/delete`)
```json
{ "status": "error", "message": "Anda tidak memiliki hak akses untuk menghapus potongan." }
```

**404 Not Found** (`{id}` pendaftaran/potongan tidak ditemukan)
```json
{ "status": "error", "message": "No query results for model [App\\Models\\Spmb\\PotonganCalon] 99." }
```

---

### Catatan Tambahan

> - Potongan hanya diterapkan pada tagihan **baru** saat generate (pendaftaran/daftar ulang). Tagihan yang sudah terbit bersifat snapshot.
> - `persen` dihitung dari nominal komponen target pada rincian biaya calon; `nominal` dipakai apa adanya (di-cap maksimal sebesar nominal komponen).
> - Potongan hanya efektif bila `status=aktif`, `berlaku_mulai/sampai` cocok dengan tanggal, dan `tahap` cocok dengan tahap tagihan.
> - Potongan yang menargetkan komponen yang tidak ada pada rincian calon akan diabaikan saat perhitungan tagihan.

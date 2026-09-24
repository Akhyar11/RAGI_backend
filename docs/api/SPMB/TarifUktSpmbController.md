# TarifUktSpmbController (DIHAPUS — DEPRECATED)

> **Modul**: SPMB / **Base URL**: `/api/spmb/master/tarif-ukt` (tidak lagi tersedia) / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-09-25 (dihapus)

Controller `TarifUktSpmbController` beserta seluruh endpoint `/api/spmb/master/tarif-ukt` **telah dihapus** pada 2026-09-25. Tabel `spmb_tarif_ukt` juga di-drop. Fungsinya digantikan sepenuhnya oleh **MasterBiayaSpmbController** (biaya per gelombang + program studi dengan penanda `dibebankan_saat_pendaftaran`).

## Headers (historis)

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ (historis) |
| `Accept` | `application/json` | ✅ (historis) |
| `Content-Type` | `application/json` | ✅ (historis) |

## Daftar Endpoint (TIDAK LAGI TERSEDIA)

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/spmb/master/tarif-ukt` | Daftar tarif UKT SPMB (dihapus) | ✅ |
| POST | `/api/spmb/master/tarif-ukt` | Buat tarif UKT SPMB (dihapus) | ✅ |
| PUT | `/api/spmb/master/tarif-ukt/{id}` | Perbarui tarif UKT SPMB (dihapus) | ✅ |
| DELETE | `/api/spmb/master/tarif-ukt/{id}` | Hapus tarif UKT SPMB (dihapus) | ✅ |

## Query Parameters (historis)

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari nama biaya |
| `sort_by` | string | ❌ | `created_at` | `created_at`, `nama`, `id` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

## Request Body (historis)

```json
{
    "nama": "Biaya Daftar Ulang",
    "deskripsi": "UKT semester 1",
    "master_sikeu_biaya_id": 101,
    "master_program_studi_id": 7
}
```

## Response

**410 Gone** (endpoint dihapus)
```json
{
    "status": "error",
    "message": "Endpoint /api/spmb/master/tarif-ukt telah dihapus. Gunakan /api/spmb/master/biaya."
}
```

**401 Unauthorized**
```json
{ "status": "error", "message": "Unauthenticated." }
```
**403 Forbidden**
```json
{ "status": "error", "message": "This action is unauthorized." }
```
**404 Not Found**
```json
{ "status": "error", "message": "Route /api/spmb/master/tarif-ukt not found." }
```
**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": { "master_program_studi_id": ["The selected master program studi id is invalid."] }
}
```

## Catatan Tambahan

> - Pengganti: `MasterBiayaSpmbController` (`/api/spmb/master/biaya`).
> - Soft-delete tidak berlaku karena controller/tabel telah dihapus.
> - Password/token tidak pernah dikembalikan pada response.

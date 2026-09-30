# ReferralPencairanController

> **Modul**: SIKEU (Keuangan)
> **Base URL**: `/api/v1/sikeu`
> **Autentikasi**: Bearer Token (`auth:api` + `can:sikeu.pengeluaran.read`)
> **Dibuat**: 2026-09-27
> **Diperbarui**: 2026-09-27

Invoice payout reward referral SPMB (`spmb_referral_payouts`) yang masuk ke admin keuangan:
verifikasi bukti → bayar (otomatis tercatat sebagai `PengeluaranKampus` kategori `honorarium` + jurnal) → atau tolak.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/referral-pencairan` | Daftar invoice referral (filter `search`, `status`, `sort_by` [`created_at`,`updated_at`,`total_nominal`,`nomor_bukti`], `sort_order`, paginasi) | ✅ Staf Keuangan |
| GET | `/api/v1/sikeu/referral-pencairan/{id}` | Detail invoice + referrer + usages | ✅ Staf Keuangan |
| POST | `/api/v1/sikeu/referral-pencairan/{id}/verify` | Verifikasi invoice (`menunggu_verifikasi`/`ditolak` → `terverifikasi`) | ✅ Staf Keuangan |
| POST | `/api/v1/sikeu/referral-pencairan/{id}/pay` | Bayar invoice: buat pengeluaran + jurnal, payout → `dibayar` | ✅ Staf Keuangan |
| POST | `/api/v1/sikeu/referral-pencairan/{id}/reject` | Tolak invoice (`menunggu_verifikasi`/`terverifikasi` → `ditolak`) | ✅ Staf Keuangan |

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ (POST) |

## Query Params (GET list)

| Param | Type | Default | Deskripsi |
|---|---|---|---|
| `search` | string | — | Cari `nomor_bukti`, `sikeu_reference`, nama/username referrer |
| `status` | string | — | `menunggu_verifikasi` / `terverifikasi` / `dibayar` / `ditolak` |
| `sort_by` | string | `created_at` | `created_at` / `updated_at` / `total_nominal` / `nomor_bukti` |
| `sort_order` | string | `desc` | `asc` / `desc` |
| `page` | integer | `1` | Halaman |
| `per_page` | integer | `15` (maks 100) | Jumlah per halaman |

## [GET] /api/v1/sikeu/referral-pencairan

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Daftar invoice referral berhasil dimuat.",
    "data": [
        { "id": 3, "nomor_bukti": "PAYOUT-20260927-AB12CD", "status": "menunggu_verifikasi", "referral_count": 2, "total_nominal": 100000, "referrer": { "id": 7, "name": "Budi", "username": "budi", "email": "budi@kampus.ac.id" }, "usages_count": 2 }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1 },
    "filters": { "search": null, "status": null, "sort_by": "created_at", "sort_order": "desc" }
}
```

## [GET] /api/v1/sikeu/referral-pencairan/{id}

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Detail invoice referral berhasil dimuat.",
    "data": { "id": 3, "nomor_bukti": "PAYOUT-20260927-AB12CD", "status": "menunggu_verifikasi", "nama_bank": "BCA", "nomor_rekening": "1234567890", "nama_pemilik_rekening": "Budi Santoso", "referrer": { "id": 7, "name": "Budi" }, "usages": [{ "id": 11, "referral_code": "REF-XYZ", "status": "qualified" }] }
}
```

## [POST] /api/v1/sikeu/referral-pencairan/{id}/verify

> Tanpa body. Mengubah `menunggu_verifikasi`/`ditolak` → `terverifikasi` (audit action `approve`).

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Invoice referral berhasil diverifikasi.",
    "data": { "id": 3, "status": "terverifikasi" }
}
```

### Response Error Umum

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
{ "status": "error", "message": "Data tidak ditemukan." }
```

---

## [POST] /api/v1/sikeu/referral-pencairan/{id}/pay

### Request Body

```json
{
    "unit_kas_id": 1,
    "akun_beban_id": null,
    "tanggal_bayar": "2026-09-27",
    "nomor_referensi_transfer": "TRF-123456",
    "catatan": "Transfer via bank"
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Payout referral berhasil dibayar dan dicatat sebagai pengeluaran.",
    "data": { "id": 3, "nomor_bukti": "PAYOUT-20260927-AB12CD", "status": "dibayar", "sikeu_reference": "EXP-HON-20260927-XY12", "total_nominal": 50000 }
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

**422 Unprocessable Content**
```json
{
    "status": "error",
    "message": "Payout referral pada status ini tidak dapat dibayar."
}
```

## [POST] /api/v1/sikeu/referral-pencairan/{id}/reject

### Request Body

```json
{
    "catatan": "Rekening tidak valid, mohon perbaiki data bank."
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Invoice referral ditolak.",
    "data": { "id": 3, "status": "ditolak" }
}
```

### Response Error

**422 Unprocessable Content**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "catatan": ["Catatan penolakan wajib diisi."]
    }
}
```

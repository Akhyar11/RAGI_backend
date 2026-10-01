# ReferralPencairanController

> **Modul**: SIKEU (Keuangan)
> **Base URL**: `/api/v1/sikeu`
> **Autentikasi**: Bearer Token (`auth:api` + `can:sikeu.pengeluaran.read`)
> **Dibuat**: 2026-09-27
> **Diperbarui**: 2026-09-30

Invoice payout reward referral SPMB (`spmb_referral_payouts`) yang masuk ke admin keuangan.
Alur approval diselaraskan dengan **Pengajuan Operasional**: `pending_keuangan` → `pending_direktur` → `disetujui` → `dicairkan` (+ `ditolak`). Pencairan otomatis tercatat sebagai `PengeluaranKampus` kategori `honorarium` + jurnal, dengan **nominal manual** (maksimal sebesar total bukti). Endpoint ini dipakai oleh **tab SPMB** pada halaman Pengajuan Operasional.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/referral-pencairan` | Daftar invoice referral (filter `search`, `status`, `sort_by` [`created_at`,`updated_at`,`total_nominal`,`nomor_bukti`], `sort_order`, paginasi) | ✅ Staf Keuangan |
| GET | `/api/v1/sikeu/referral-pencairan/{id}` | Detail invoice + referrer + riwayat usage referral | ✅ Staf Keuangan |
| POST | `/api/v1/sikeu/referral-pencairan/{id}/approve` | Approval bertahap (`pending_keuangan` → `pending_direktur` → `disetujui`) atau tolak | ✅ Staf Keuangan |
| POST | `/api/v1/sikeu/referral-pencairan/{id}/cairkan` | Cairkan payout: buat pengeluaran + jurnal, payout → `dicairkan` (nominal manual) | ✅ Staf Keuangan |

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ (POST) |

## Query Params (GET list)

| Param | Type | Default | Deskripsi |
|---|---|---|---|
| `search` | string | — | Cari `nomor_bukti`, `sikeu_reference`, nama/username/email referrer |
| `status` | string | — | `pending_keuangan` / `pending_direktur` / `disetujui` / `dicairkan` / `ditolak` |
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
        { "id": 3, "nomor_bukti": "PAYOUT-20260930-AB12CD", "status": "pending_keuangan", "referral_count": 2, "total_nominal": 100000, "referrer": { "id": 7, "name": "Budi", "username": "budi", "email": "budi@kampus.ac.id" }, "usages_count": 2 }
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
    "data": {
        "id": 3,
        "nomor_bukti": "PAYOUT-20260930-AB12CD",
        "status": "pending_keuangan",
        "referral_count": 2,
        "total_nominal": 100000,
        "nama_bank": "BCA",
        "nomor_rekening": "1234567890",
        "nama_pemilik_rekening": "Budi Santoso",
        "referrer": { "id": 7, "name": "Budi", "username": "budi" },
        "approver_keuangan": null,
        "approver_direktur": null,
        "usages": [
            {
                "id": 11,
                "referral_code": "REF-XYZ123",
                "status": "qualified",
                "pendaftaran": {
                    "id": 21,
                    "no_pendaftaran": "REG-20260930-1234",
                    "nama_lengkap": "Siti Aminah",
                    "status": "lulus_administrasi",
                    "gelombang_penerimaan": { "id": 2, "nama": "Gelombang 1" }
                }
            }
        ]
    }
}
```

## [POST] /api/v1/sikeu/referral-pencairan/{id}/approve

> Approval bertahap. `pending_keuangan` → `pending_direktur` (approval keuangan), `pending_direktur` → `disetujui` (approval direktur). `aksi=reject` menolak pada tahap mana pun.

### Request Body

```json
{
    "aksi": "approve",
    "catatan": "Bukti valid, lanjut ke direktur."
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Invoice referral disetujui ke tahap berikutnya.",
    "data": { "id": 3, "nomor_bukti": "PAYOUT-20260930-AB12CD", "status": "pending_direktur" }
}
```

### Response Error

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
**422 Unprocessable Content**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": { "catatan": ["The catatan field is required when aksi is reject."] }
}
```

---

## [POST] /api/v1/sikeu/referral-pencairan/{id}/cairkan

> Hanya untuk payout berstatus `disetujui`. Membuat `PengeluaranKampus` (honorarium) + jurnal, decrement saldo unit kas, dan menandai payout `dicairkan`. `nominal_cair` diinput manual dan tidak boleh melebihi `total_nominal` bukti.

### Request Body

```json
{
    "unit_kas_id": 1,
    "nominal_cair": 75000,
    "akun_beban_id": null,
    "tanggal_bayar": "2026-09-30",
    "nomor_referensi_transfer": "TRF-123456",
    "catatan": "Transfer sebagian sesuai bukti."
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Payout referral berhasil dicairkan dan dicatat sebagai pengeluaran.",
    "data": { "id": 3, "nomor_bukti": "PAYOUT-20260930-AB12CD", "status": "dicairkan", "sikeu_reference": "EXP-HON-20260930-XY12", "total_nominal": 100000 }
}
```

### Response Error

**401 Unauthorized**
```json
{ "status": "error", "message": "Unauthenticated." }
```
**422 Unprocessable Content**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": { "nominal_cair": ["Nominal pencairan tidak boleh melebihi total bukti (Rp 100.000)."] }
}
```

### Catatan Tambahan

> - Endpoint ini non-soft-delete (data payout tidak dihapus; hanya berubah status).
> - `nominal_cair` wajib > 0 dan ≤ `total_nominal`.
> - Audit log perubahan payout ditangani otomatis oleh `PayoutReferralObserver`.

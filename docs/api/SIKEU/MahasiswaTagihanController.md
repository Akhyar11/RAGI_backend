# MahasiswaTagihanController

> **Modul**: SIKEU (Keuangan)  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-08-05  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/mahasiswa/tagihan` | Portal rincian tagihan semester mahasiswa aktif | ✅ Mahasiswa |
| GET | `/api/v1/sikeu/mahasiswa/invoice/{id}` | Unduh / Generate Surat Invoice Tagihan Resmi | ✅ Mahasiswa |
| GET | `/api/v1/sikeu/mahasiswa/rekening-tujuan` | Rekening kampus tujuan transfer manual — hanya bank_manual BNI/BSN aktif bernomor rekening (tanpa saldo) | ✅ Mahasiswa |
| POST | `/api/v1/sikeu/pembayaran/manual-init` | Inisiasi transfer manual: kunci nominal + kode unik 1-499 per tagihan (`nominal_transfer = jumlah + kode`). Idempoten: kombinasi tagihan + rekening + nominal yang sama mengembalikan kode yang sudah ada (`reused: true`); ganti rekening/nominal → kode baru | ✅ Mahasiswa |
| POST | `/api/v1/sikeu/pembayaran/manual-upload` | Unggah bukti transfer manual — mode `pembayaran_id` (lampirkan ke inisiasi) atau legacy (`tagihan_id` + rekening + nominal, kode unik dibuat otomatis) | ✅ Mahasiswa |

> **Penguncian menunggu validasi**: tagihan yang punya pembayaran manual `pending` berbukti dikunci untuk pembayaran lain (`pay-bills`, `manual-init`, `manual-upload` → 422) sampai disetujui/ditolak. `myBills` menyertakan `pending_verification` dan `last_rejection` (alasan penolakan keuangan).

---

## GET /api/v1/sikeu/mahasiswa/tagihan

> Menampilkan daftar tagihan semester berjalan untuk mahasiswa yang sedang terautentikasi.

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "data": [
        {
            "id": 1,
            "nomor_tagihan": "INV-SIAKAD-20260801-001",
            "total_tagihan": 3500000.00,
            "total_potongan": 500000.00,
            "total_bayar": 0.00,
            "sisa_tagihan": 3000000.00,
            "status": "belum_bayar",
            "jatuh_tempo": "2026-09-01",
            "virtual_account": {
                "va_number": "8882026080001",
                "bank_nama": "Bank BNI",
                "status": "aktif"
            }
        }
    ]
}
```


---

## GET /api/v1/sikeu/mahasiswa/payment-channels

> Mengambil daftar kanal pembayaran yang tersedia untuk mahasiswa, difilter berdasarkan konfigurasi `PaymentGatewayConfig` yang aktif (`bsn_h2h`, `xendit`, `midtrans`, `rekening_manual`).

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
    "message": "Daftar saluran pembayaran berhasil diambil.",
    "data": [
        {
            "id": "BSN",
            "name": "Bank BSN (Virtual Account H2H)",
            "code": "BSN",
            "type": "VIRTUAL_ACCOUNT",
            "category": "va",
            "prefix": "90012",
            "logo_color": "from-teal-700 to-emerald-800",
            "badge": "Otomatis Realtime",
            "description": "VA Host-to-Host BSN (CUSTID = No. Pendaftaran), verifikasi otomatis & masuk saldo BSN",
            "fee": 0,
            "is_active": true
        }
    ]
}
```

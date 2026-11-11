# SpmbOnlinePaymentController

> **Modul:** SIKEU / SPMB
> **Base URL:** `/api/v1/sikeu/callback`
> **Autentikasi:** ❌ Publik; token callback provider wajib
> **Diperbarui:** 2026-11-11

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| POST | `/api/v1/sikeu/callback/xendit-checkout` | Konfirmasi invoice checkout Xendit SPMB | ✅ `x-callback-token` |

## POST /api/v1/sikeu/callback/xendit-checkout

Atur callback invoice/payment link Xendit ke URL **backend** ini. Request menggunakan `Content-Type: application/json`, `Accept: application/json`, dan header `x-callback-token` yang cocok dengan konfigurasi Xendit pada order tersebut. Callback VA lama tetap menggunakan endpoint lama.

```json
{"id":"invoice-provider-id","external_id":"SPMB-91-01JEXAMPLE","status":"PAID","amount":90000,"paid_amount":90000,"currency":"IDR","payment_method":"BANK_TRANSFER"}
```

`external_id` harus sama dengan `sikeu_payment_checkouts.order_id`; `id` harus sama dengan invoice provider yang disimpan. Nominal harus sesuai checkout, currency IDR, dan provider Xendit. Hanya status `PAID` melunasi tagihan. Status pending/expired dicatat tanpa menambah pembayaran; callback sukses berulang mengembalikan success tanpa menggandakan kas/jurnal/notifikasi.

```json
{"status":"success","message":"Notifikasi pembayaran Xendit diterima.","data":{"processed":true,"status":"paid","pembayaran_id":52}}
```

Retry sukses: `data: {"processed": false, "status": "paid"}`. Callback nonlunas: `data: {"processed": false, "status": "pending"}` atau status dari provider.

Error memakai envelope `status: error`, `message`, serta `errors` untuk validasi: 401 token callback salah/tidak tersedia; 400 identitas/provider/currency tidak cocok; 404 order tidak ditemukan; 422 nominal tidak cocok atau tagihan sudah berubah/dibayar melalui transaksi lain. Kegagalan jurnal membatalkan transaksi (500), sehingga pembayaran dapat diproses ulang sesudah pemetaan keuangan diperbaiki. Transaksi nyata yang melampaui sisa tagihan membutuhkan rekonsiliasi, tidak dihitung dua kali.

Midtrans memakai endpoint callback yang sudah ada di [MidtransCallbackController](MidtransCallbackController.md), yang meneruskan order checkout tercatat ke service pelunasan yang sama. H2H memakai sinkronisasi bridge yang sudah tersedia, berdasarkan ID billing, dengan lock dan pemeriksaan retry.

Implementasi mengikuti protokol [Midtrans Snap](https://docs.midtrans.com/docs/snap-snap-integration-guide), [signature notifikasi Midtrans](https://docs.midtrans.com/docs/https-notification-webhooks), dan endpoint invoice `/v2/invoices` yang dicantumkan dalam [panduan migrasi resmi Xendit](https://docs.xendit.co/docs/migrate-to-payment-session). API invoice dipertahankan untuk integrasi checkout ini; panduan Xendit juga menyediakan jalur migrasi ke Payment Sessions.

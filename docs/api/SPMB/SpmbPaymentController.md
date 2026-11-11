# SpmbPaymentController

> **Modul:** SPMB / SIKEU
> **Base URL:** `/api/spmb`
> **Autentikasi:** Bearer Passport
> **Diperbarui:** 2026-11-11

## Daftar endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/spmb/master/payment-gateway-options` | Pilihan kanal untuk komponen biaya | ✅ `spmb.manage` |
| GET | `/api/spmb/pembayaran` | Tagihan calon yang login | ✅ Pemilik pendaftaran atau `spmb.manage` / `spmb.pendaftaran.read` |
| POST | `/api/spmb/pembayaran/{id}/checkout` | Siapkan pembayaran tagihan | ✅ Pemilik atau petugas pembayaran |

## Request Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Accept` | `application/json` | Ya, pada semua endpoint |
| `Authorization` | `Bearer {token}` | Ya, pada semua endpoint |
| `Content-Type` | `application/json` | Ya, untuk request POST |

## GET /api/spmb/master/payment-gateway-options

Menampilkan konfigurasi kanal aktif yang didukung alur SPMB. Nilai dropdown adalah ID record konfigurasi, bukan label atau credential provider.

| Query parameter | Tipe | Default | Keterangan |
|---|---|---|---|
| `search` | string | - | Pencarian nama gateway, maksimum 200 karakter |
| `page` | integer | `1` | Halaman data, minimum 1 |
| `per_page` | integer | `15` | Jumlah per halaman, minimum 1 dan dipotong ke maksimum 100 |
| `sort_by` | string | `gateway_name` | `gateway_name` atau `id` |
| `sort_order` | string | `asc` | `asc` atau `desc` |

```json
{
  "status": "success",
  "message": "Pilihan kanal pembayaran berhasil dimuat.",
  "data": [
    {"id": 12, "gateway_name": "midtrans", "environment": "sandbox", "is_active": true}
  ],
  "meta": {"current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1}
}
```

Pilihan tersimpan pada komponen tetap memiliki metadata `payment_gateway` dari API detail komponen meskipun berada di luar halaman opsi pertama atau kemudian nonaktif. Opsi kosong bukan pemilihan kanal baru; `payment_gateway_config_id: null` berarti mengikuti default SIKEU ketika tagihan baru diterbitkan. Credential provider tidak pernah dikembalikan.

## GET /api/spmb/pembayaran

Hanya menampilkan tagihan milik pendaftaran pengguna yang login. Petugas dengan `spmb.manage` atau `spmb.pendaftaran.read` dapat membuka endpoint, tetapi data tetap dibatasi pada kepemilikan akun. Pengguna tanpa pendaftaran dan tanpa permission tersebut menerima 403.

| Query parameter | Tipe | Default | Keterangan |
|---|---|---|---|
| `tahap` | string | semua | `pendaftaran` atau `daftar_ulang` |
| `search` | string | - | Pencarian nomor tagihan |
| `page` | integer | `1` | Halaman data, minimum 1 |
| `per_page` | integer | `15` | Jumlah per halaman, minimum 1 dan dipotong ke maksimum 100 |
| `sort_by` | string | `id` | `id`, `nomor_tagihan`, atau `created_at` |
| `sort_order` | string | `asc` | `asc` atau `desc` |

```json
{
  "status": "success",
  "message": "Tagihan pembayaran SPMB berhasil dimuat.",
  "data": [
    {
      "id": 91,
      "nomor_tagihan": "INV-SPMB-20261111-ABC12",
      "status": "belum_bayar",
      "tipe_referensi": "spmb_pendaftaran",
      "payment_gateway_config_id": 12,
      "gateway_name": "midtrans",
      "environment": "sandbox",
      "total_tagihan": 100000,
      "total_potongan": 10000,
      "total_denda": 0,
      "total_bayar": 0,
      "sisa_tagihan": 90000,
      "jatuh_tempo": "2026-12-11",
      "details": [{"id": 101, "keterangan": "Biaya pendaftaran", "nominal": 100000}],
      "virtual_account": null
    }
  ],
  "meta": {"current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1}
}
```

Envelope list hanya memakai `status`, `message`, `data`, dan `meta`. Daftar kosong menghasilkan 200 dan `data: []`.

## POST /api/spmb/pembayaran/{id}/checkout

Pemilik ditentukan melalui relasi `tagihan.calonMahasiswa.user_id`. Petugas dapat menyiapkan checkout dengan permission `sikeu.paymentgateway.manage` atau `sikeu.pembayaran.create`. Request tidak memerlukan body; browser tidak menentukan nominal, provider, maupun status lunas. Field `amount`, `nominal`, dan `payment_gateway_config_id` dilarang.

**200 — Midtrans Snap redirect / Xendit invoice:**

```json
{
  "status": "success",
  "message": "Instruksi pembayaran berhasil disiapkan.",
  "data": {
    "method": "redirect",
    "gateway_name": "midtrans",
    "environment": "sandbox",
    "order_id": "SPMB-91-01JEXAMPLE",
    "amount": 90000,
    "redirect_url": "https://app.sandbox.midtrans.com/snap/v2/vtweb/example",
    "expires_at": "2026-11-12T00:00:00.000000Z"
  }
}
```

URL berasal dari provider dan divalidasi HTTPS/domain provider. Checkout pending dengan nominal yang sama dipakai ulang selama masih berlaku. Return URL provider diarahkan ke halaman pembayaran sesuai tahap melalui frontend URL yang dikonfigurasi. Klik/link kembali dari provider belum menandai lunas; hanya konfirmasi backend yang mengubah status. Semua order disimpan di `sikeu_payment_checkouts`, termasuk percobaan gagal, agar callback tidak ditebak dari ID calon/tagihan.

**200 — H2H bank:**

```json
{
  "status": "success",
  "message": "Instruksi pembayaran berhasil disiapkan.",
  "data": {
    "method": "h2h",
    "gateway_name": "bsn_h2h",
    "amount": 90000,
    "bank_name": "BTN Syariah",
    "id_billing": 991,
    "id_tagihan": "bridge-991",
    "custid": "REG-2026-123",
    "nominal": 90000,
    "va_fee": 1500,
    "total_bridge": 91500,
    "reused": false
  }
}
```

Billing dibuat melalui bridge yang dikonfigurasi di SIKEU. Billing yang sudah diterbitkan dipakai ulang. Fee bridge tetap berada di luar tagihan RAG; sinkronisasi mengalokasikan maksimal sisa tagihan.

Tagihan lama tanpa mapping dapat mengembalikan `method: "virtual_account"`, `va_number`, `bank_name`, `amount`, dan `expires_at` dari VA yang benar-benar tersimpan dan aktif. Tidak membuat VA lokal pengganti untuk kegagalan kanal eksplisit.

## Response Error

| Status | Kondisi |
|---|---|
| 400 | Format request/body tidak dapat diproses |
| 401 | Token login tidak tersedia/tidak valid |
| 403 | Tidak memiliki permission opsi atau bukan pemilik/petugas checkout |
| 404 | ID tagihan tidak ditemukan |
| 422 | Parameter tidak valid, tagihan lunas/belum disetujui, VA lama kedaluwarsa, kanal nonaktif/berubah, credential belum lengkap, atau checkout sedang dibuat |
| 502 | Provider/bridge gagal atau respons checkout tidak valid |

### Response Error (400 Bad Request)

```json
{
  "status": "error",
  "message": "Format request tidak dapat diproses."
}
```

### Response Error (401 Unauthorized)

```json
{
  "status": "error",
  "message": "Unauthenticated."
}
```

### Response Error (403 Forbidden)

```json
{
  "status": "error",
  "message": "Anda tidak memiliki hak akses untuk melakukan aksi ini."
}
```

### Response Error (404 Not Found)

```json
{
  "status": "error",
  "message": "Data tagihan tidak ditemukan."
}
```

### Response Error (422 Unprocessable Content)

```json
{
  "status": "error",
  "message": "Kanal pembayaran tagihan tidak aktif atau konfigurasinya telah berubah. Hubungi panitia SPMB.",
  "errors": {
    "payment_gateway_config_id": [
      "Kanal pembayaran tagihan tidak aktif atau konfigurasinya telah berubah. Hubungi panitia SPMB."
    ]
  }
}
```

### Response Error (502 Bad Gateway)

```json
{
  "status": "error",
  "message": "Layanan pembayaran belum dapat membuat checkout. Silakan coba kembali."
}
```

## Operasional

Terapkan migrasi `2026_11_11_140000_add_spmb_payment_channels.php`, aktifkan kanal dan credential yang sesuai melalui SIKEU, lalu pilih kanal pada komponen biaya SPMB. Pemetaan COA dan unit kas kanal harus lengkap; kegagalan pembukuan menyebabkan rollback pelunasan untuk diproses ulang setelah konfigurasi diperbaiki. Konfigurasi sandbox dan produksi tidak boleh dicampur. Pengubahan environment ditolak selama kanal masih memiliki tagihan yang belum lunas.

Untuk H2H, jalankan sinkronisasi bank yang sudah tersedia (`php artisan sikeu:sync-h2h`). Email memakai worker queue `notifications,default`. Credential produksi, callback dashboard provider, dan pembayaran bank nyata perlu diverifikasi pada deployment.

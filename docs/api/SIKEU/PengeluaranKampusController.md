# PengeluaranKampusController

> **Modul**: SIKEU (Keuangan)  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Sanctum / JWT)  
> **Dibuat**: 2026-08-18  
> **Diperbarui**: 2026-11-09

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/pengeluaran` | List transaksi pengeluaran kampus dengan filter & pagination | ✅ Staf Keuangan |
| POST | `/api/v1/sikeu/pengeluaran` | Catat pengeluaran dan jurnal otomatis dari COA serta unit kas terpilih | ✅ Staf Keuangan |
| GET | `/api/v1/sikeu/pengeluaran/{id}` | Detail transaksi pengeluaran spesifik | ✅ Staf Keuangan |

## GET `/api/v1/sikeu/pengeluaran`

Mengambil daftar transaksi dengan pencarian, filter, pagination, dan ringkasan nominal.

| Query Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `search` | string | ❌ | Cari nomor transaksi, vendor, atau keterangan |
| `kategori` | string | ❌ | `operasional`, `pemeliharaan`, `laboratorium`, `kegiatan`, `honorarium`, `lainnya`, atau `semua` |
| `jenis_pajak` | string | ❌ | `tanpa_pajak`, `pph_21`, `pph_23`, `ppn_11`, atau `semua` |
| `status` | string | ❌ | Filter status pembayaran |
| `per_page` | integer | ❌ | Jumlah data per halaman, default 15, maksimum 100 |
| `page` | integer | ❌ | Halaman |

## POST `/api/v1/sikeu/pengeluaran`

Mencatat transaksi pengeluaran, mengurangi saldo unit kas, dan menerbitkan jurnal seimbang melalui `JurnalSikeuService`. `unit_kas_id` wajib menunjuk unit kas aktif yang memiliki COA kas/bank eksplisit. Akun beban default dipetakan menurut kategori; akun pajak dipetakan sesuai jenis pajak. Akun/COA yang tidak tersedia atau saldo kas yang kurang akan menggagalkan transaksi tanpa menyimpan transaksi atau jurnal.

### Request Body

```json
{
    "kategori": "operasional",
    "nominal": 10000,
    "tanggal_transaksi": "2026-11-09",
    "nama_vendor": "Vendor Pengadaan",
    "npwp_vendor": "01.234.567.8-901.000",
    "jenis_pajak": "pph_23",
    "unit_kas_id": 2,
    "keterangan": "Pembelian perlengkapan",
    "file_bukti_bayar": "bukti-pembayaran.pdf"
}
```

| Field | Type | Required | Deskripsi |
|---|---|---|---|
| `kategori` | string | ✅ | `operasional`, `pemeliharaan`, `laboratorium`, `kegiatan`, `honorarium`, atau `lainnya` |
| `akun_beban_id` | integer | ❌ | Override akun beban; harus berupa COA kelompok beban |
| `nominal` | number | ✅ | Nominal transaksi, minimum 1000 |
| `tanggal_transaksi` | date | ✅ | Tanggal transaksi |
| `nama_vendor` | string | ✅ | Nama vendor |
| `npwp_vendor` | string | ❌ | NPWP vendor |
| `jenis_pajak` | string | ✅ | `tanpa_pajak`, `pph_21`, `pph_23`, atau `ppn_11` |
| `unit_kas_id` | integer | ✅ | Unit kas aktif dengan COA kas/bank terpetakan |
| `keterangan` | string | ❌ | Keterangan transaksi |
| `file_bukti_bayar` | string | ❌ | Referensi/URL bukti pembayaran |

Pemetaan default akun beban kategori: honorarium ke akun beban gaji; pemeliharaan ke beban pemeliharaan; laboratorium ke beban laboratorium; operasional/kegiatan/lainnya ke beban operasional. Untuk PPh 21/23, nominal pajak menjadi utang pajak dan mengurangi kas yang dibayarkan ke vendor. Untuk PPN, jurnal memisahkan utang pajak dari kas yang dibayarkan.

**201 Created**
```json
{
    "status": "success",
    "message": "Pengeluaran berhasil dicatat, saldo unit kas disesuaikan, dan jurnal akuntansi telah diterbitkan.",
    "data": {
        "nomor_transaksi": "EXP-OPE-20261109-AB12CD34",
        "kategori": "operasional",
        "nominal": 10000,
        "jenis_pajak": "pph_23",
        "nominal_pajak": 200,
        "net_dibayarkan": 9800,
        "status_pembayaran": "lunas"
    }
}
```

### Response Error

**422 Unprocessable Entity** — input tidak valid, unit kas tidak aktif/COA belum dipetakan, akun beban/pajak tidak tersedia, atau saldo unit kas tidak mencukupi.

# DispensasiTagihanController

> **Modul**: SIKEU (Keuangan)  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-08-05  
> **Diperbarui**: 2026-09-21

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/dispensasi` | List permohonan dispensasi pembayaran (mahasiswa: hanya milik sendiri) | ✅ Bearer |
| POST | `/api/v1/sikeu/dispensasi` | Pengajuan permohonan dispensasi baru | ✅ Bearer |
| GET | `/api/v1/sikeu/dispensasi/{id}` | Detail permohonan dispensasi | ✅ Bearer |
| GET | `/api/v1/sikeu/dispensasi/{id}/cetak-bukti` | Cetak Surat Bukti Dispensasi Resmi (+hash QR, mahasiswa: hanya milik sendiri) | ✅ Bearer |
| DELETE | `/api/v1/sikeu/dispensasi/{id}` | Hapus dispensasi (ditolak bila sudah ada pembayaran cicilan) | ✅ Bearer |
| GET | `/api/v1/sikeu/dispensasi/validasi/{signature_hash}` | Verifikasi publik surat via QR Code | ❌ Publik |

---

## POST /api/v1/sikeu/dispensasi

> Mengajukan permohonan penundaan atau cicilan pembayaran tagihan. Memeriksa otomatis tunggakan dispensasi sebelumnya (`has_unpaid_previous_dispensation`).

### Request Body

```json
{
    "tagihan_id": 1,
    "mahasiswa_id": 101,
    "tipe_dispensasi": "penundaan_jatuh_tempo",
    "jatuh_tempo_baru": "2026-10-15",
    "jumlah_cicilan": 1,
    "nominal_per_cicilan": 3000000,
    "alasan": "Menunggu bantuan dana orang tua akhir bulan"
}
```

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Permohonan dispensasi berhasil diajukan dan menunggu persetujuan pimpinan.",
    "warning_unpaid_previous": false,
    "data": {
        "id": 4,
        "tagihan_id": 1,
        "mahasiswa_id": 101,
        "status": "pending"
    }
}
```

---

## DELETE /api/v1/sikeu/dispensasi/{id}

> Hapus permohonan dispensasi. Status tagihan dikembalikan normal (`belum_bayar`/`sebagian`) bila masih berstatus `dispensasi`. Aksi tercatat di audit log.

### Guard record pembayaran

Ditolak (`422`) hanya bila tagihan terkait sudah memiliki pembayaran cicilan **beneran**, yaitu pembayaran sukses yang tercatat **setelah skema dispensasi disetujui** (atau diajukan bila belum disetujui). Pembayaran biasa sebelum dispensasi ada tidak dihitung:

### Jurnal memorandum persetujuan

Saat dispensasi disetujui, sistem mencatat jurnal memorandum `JRN-DSP-*` bernilai nol (tanpa efek saldo) sebagai jejak di buku. Cicilan/penundaan tidak memindahkan nilai sehingga tidak ada jurnal berpasangan; mutasi kas tetap dicatat normal (`Dr Kas / Cr Piutang`) saat cicilan dibayar.

```json
{
    "status": "error",
    "message": "Dispensasi tidak dapat dihapus karena tagihan INV-... sudah memiliki 2 pembayaran cicilan tercatat (... IDR). Hapus/batalkan pembayaran terlebih dahulu bila memang harus dihapus.",
    "data": { "payment_count": 2, "total_bayar": 2000000 }
}
```

---

## GET /api/v1/sikeu/dispensasi/validasi/{signature_hash}

> Verifikasi publik surat dispensasi via QR Code (tanpa login). `signature_hash` (`SIG-DISP-XXXX`) disimpan saat approval dan tercetak di surat.

### Response Sukses

```json
{
    "status": "success",
    "message": "Surat dispensasi sah dan terverifikasi di sistem keuangan kampus.",
    "data": {
        "nomor_dispensasi": "DISP-2026-00012",
        "status": "approved",
        "is_valid": true,
        "tipe_dispensasi": "cicilan",
        "nominal_per_cicilan": 1000000,
        "mahasiswa": { "nama_mahasiswa": "...", "nim": "..." },
        "tagihan": { "nomor_tagihan": "INV-...", "sisa": 3000000 },
        "security_hash": "SIG-DISP-XXXX"
    }
}
```

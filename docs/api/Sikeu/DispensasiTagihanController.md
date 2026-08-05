# DispensasiTagihanController

> **Modul**: SIKEU (Keuangan)  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-08-05  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/dispensasi` | List permohonan dispensasi pembayaran | ✅ Bearer |
| POST | `/api/v1/sikeu/dispensasi` | Pengajuan permohonan dispensasi baru | ✅ Bearer |
| GET | `/api/v1/sikeu/dispensasi/{id}` | Detail permohonan dispensasi | ✅ Bearer |
| GET | `/api/v1/sikeu/dispensasi/{id}/cetak-bukti` | Cetak Surat Bukti Dispensasi Resmi | ✅ Bearer |

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

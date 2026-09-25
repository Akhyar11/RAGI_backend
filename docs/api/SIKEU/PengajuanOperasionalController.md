# PengajuanOperasionalController

> **Modul**: SIKEU (Sistem Informasi Keuangan)  
> **Base URL**: `/api/v1/sikeu/pengajuan-operasional`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-09-25  
> **Diperbarui**: 2026-09-25  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth / Permission |
|---|---|---|---|
| GET | `/api/v1/sikeu/pengajuan-operasional` | Daftar pengajuan operasional & panjar dinas (filter tab SINAPRA vs SIMPEG) | ✅ Sanctum |
| GET | `/api/v1/sikeu/pengajuan-operasional/{id}` | Detail rincian pengajuan operasional / panjar | ✅ Sanctum |
| POST | `/api/v1/sikeu/pengajuan-operasional` | Membuat pengajuan operasional baru | ✅ Sanctum |
| POST | `/api/v1/sikeu/pengajuan-operasional/{id}/approve` | Persetujuan berjenjang pengajuan operasional barang/non-barang | ✅ Sanctum |
| POST | `/api/v1/sikeu/pengajuan-operasional/{id}/setujui-panjar-simpeg` | Penetapan kas pembayar & nominal panjar dinas SIMPEG (Tahap 3) | ✅ Sanctum |
| POST | `/api/v1/sikeu/pengajuan-operasional/{id}/pencairan` | Pencairan kas & upload resi/bukti bayar panjar (Tahap 5) | ✅ Sanctum |
| POST | `/api/v1/sikeu/pengajuan-operasional/{id}/lpj` | Pengunggahan LPJ operasional sarpras | ✅ Sanctum |
| POST | `/api/v1/sikeu/pengajuan-operasional/{id}/tutup-lpj-simpeg` | Verifikasi LPJ dinas SIMPEG & penutupan kasbon dinas (Tahap 8) | ✅ Sanctum |
| POST | `/api/v1/sikeu/lpj/{id}/verifikasi` | Verifikasi LPJ pengadaan sarpras | ✅ Sanctum |
| GET | `/api/v1/sikeu/referensi/fakultas` | Referensi fakultas | ✅ Sanctum |
| GET | `/api/v1/sikeu/referensi/ruangan` | Referensi ruangan | ✅ Sanctum |

---

## POST /api/v1/sikeu/pengajuan-operasional/{id}/setujui-panjar-simpeg

> Menyetujui nominal panjar perjalanan dinas SIMPEG serta menetapkan unit kas pembayar oleh Bagian Keuangan (Tahap 3).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
  "unit_kas_id": 1,
  "nominal_disetujui": 500000,
  "catatan": "Panjar disetujui untuk akomodasi dan transportasi"
}
```

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Panjar perjalanan dinas berhasil disetujui, menunggu konfirmasi pegawai",
  "data": {
    "id": 10,
    "nomor_pengajuan": "CAIR-ST-20260925-0001",
    "unit_kas_id": 1,
    "nominal_diajukan": 500000,
    "nominal_disetujui": 500000,
    "status": "panjar_disetujui"
  }
}
```

---

## POST /api/v1/sikeu/pengajuan-operasional/{id}/pencairan

> Mencairkan dana panjar dinas / pengajuan operasional dan menerbitkan bukti pengeluaran kas (Tahap 5).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `multipart/form-data` | ✅ |

### Request Body (FormData)

```
nominal_cair: 500000
tanggal_pencairan: 2026-09-25
unit_kas_id: 1
bukti_pencairan: [File resi_transfer.pdf / image]
```

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Dana berhasil dicairkan dan jurnal otomatis diterbitkan",
  "data": {
    "id": 10,
    "status": "dicairkan"
  }
}
```

---

## POST /api/v1/sikeu/pengajuan-operasional/{id}/tutup-lpj-simpeg

> Memverifikasi laporan pertanggungjawaban panjar perjalanan dinas dari modul SIMPEG, mencatat selisih pengembalian / klaim reimbursement, dan menyelesaikan status pengajuan (Tahap 8).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
  "catatan": "LPJ dinas telah diverifikasi, sisa panjar disetor ke kas operasional"
}
```

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Transaksi perjalanan dinas berhasil diverifikasi dan diselesaikan",
  "data": {
    "id": 10,
    "status": "selesai"
  }
}
```

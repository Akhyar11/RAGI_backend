# PengajuanOperasionalController

> **Modul**: SIKEU (Sistem Informasi Keuangan)  
> **Base URL**: `/api/v1/sikeu/pengajuan-operasional`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-09-25  
> **Diperbarui**: 2026-10-06    

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth / Permission |
|---|---|---|---|
| GET | `/api/v1/sikeu/pengajuan-operasional` | Daftar pengajuan operasional & panjar dinas (filter tab SINAPRA vs SIMPEG) | ✅ Sanctum |
| GET | `/api/v1/sikeu/pengajuan-operasional/{id}` | Detail rincian pengajuan operasional / panjar | ✅ Sanctum |
| POST | `/api/v1/sikeu/pengajuan-operasional` | Membuat pengajuan operasional baru | ✅ Sanctum |
| POST | `/api/v1/sikeu/pengajuan-operasional/{id}/approve` | Persetujuan berjenjang pengajuan operasional barang/non-barang | ✅ Sanctum |
| POST | `/api/v1/sikeu/pengajuan-operasional/{id}/setujui-panjar-simpeg` | Penetapan kas pembayar & nominal panjar dinas SIMPEG (Tahap 3) | ✅ Sanctum |
| POST | `/api/v1/sikeu/pengajuan-operasional/{id}/pencairan` | Pencairan kas & upload resi/bukti bayar panjar (Tahap 5) | ✅ Sanctum |
| POST | `/api/v1/sikeu/pengajuan-operasional/{id}/lpj` | Pengunggahan LPJ operasional sarpras (boleh defisit sebagai dasar reimbursement) | ✅ Sanctum |
| POST | `/api/v1/sikeu/pengajuan-operasional/{id}/reimburse` | Membuat pengajuan reimbursement RMB-* atas selisih kurang bayar LPJ | ✅ Sanctum |
| POST | `/api/v1/sikeu/pengajuan-operasional/{id}/tutup-lpj-simpeg` | Verifikasi LPJ dinas SIMPEG & penutupan kasbon dinas (Tahap 8) | ✅ Sanctum |
| POST | `/api/v1/sikeu/lpj/{id}/verifikasi` | Verifikasi LPJ pengadaan sarpras | ✅ Sanctum |
| GET | `/api/v1/sikeu/referensi/fakultas` | Referensi fakultas | ✅ Sanctum |
| GET | `/api/v1/sikeu/referensi/ruangan` | Referensi ruangan | ✅ Sanctum |
| GET | `/api/v1/sikeu/referensi/kategori-pengajuan` | Referensi kategori pengajuan operasional | ✅ Sanctum |

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

> Mencairkan dana panjar dinas / pengajuan operasional dan menerbitkan bukti pengeluaran kas (Tahap 5). Jika permohonan berasal dari kanal SINAPRA (`kanal = 'sinapra_pengadaan'`), pencairan ini secara otomatis menyelaraskan status usulan pengadaan barang pada SINAPRA menjadi `proses_pengadaan`.

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

## POST /api/v1/sikeu/pengajuan-operasional/{id}/lpj

> Pengunggahan LPJ operasional sarpras. Total pakai (`total_realisasi` + rincian tambahan) boleh melebihi nominal yang dicairkan — selisihnya tersimpan sebagai `sisa_nominal` negatif (kurang bayar) dan menjadi dasar pengajuan reimbursement via endpoint `/reimburse` di bawah. Sisa positif wajib memilih `metode_sisa` (`kembali_transfer` / `pakai_lagi`).

---

## POST /api/v1/sikeu/pengajuan-operasional/{id}/reimburse

> Membuat pengajuan anak `RMB-*` (`jenis_pengajuan = reimbursement`, `kategori = non_barang`, status awal `pending_keuangan`) yang ter-link ke pengajuan asal via `parent_pengajuan_id` dan `referensi_eksternal = reimburse:{nomor_asal}`. Reimbursement JANGAN diajukan lewat SINAPRA (khusus pengadaan barang/aset). Alur anak sama seperti pengajuan biasa: approve keuangan → direktur → pencairan (jurnal `JRN-RMB`, `jenis_sumber = reimbursement`).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `multipart/form-data` | ✅ |

### Request Body (Multipart / JSON)

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `nominal_diajukan` | numeric | ❌ | Default = selisih kurang, maksimal = selisih kurang |
| `unit_kas_id` | integer | ❌ | Default mengikuti unit kas pengajuan asal |
| `deskripsi` | string | ❌ | Keterangan klaim (min 10 karakter bila diisi) |
| `file_lampiran` | file (pdf/jpg/jpeg/png) | ❌ | Nota pendukung klaim (maks 5MB) |

### Response Sukses (201 Created)

```json
{
  "status": "success",
  "message": "Pengajuan reimbursement berhasil dibuat dan menunggu verifikasi keuangan",
  "data": {
    "id": 11,
    "nomor_pengajuan": "RMB-20261006-XXXXX",
    "status": "pending_keuangan",
    "parent_pengajuan_id": 10
  }
}
```

### Response Error (422)

```json
{
  "status": "error",
  "message": "Pengajuan asal tidak memiliki selisih kurang bayar (realisasi tidak melebihi pencairan)."
}
```

### Response Error (403 Forbidden)

```json
{
  "status": "error",
  "message": "Anda tidak memiliki hak akses untuk mengajukan reimbursement keuangan."
}
```

### Response Error (401 Unauthorized)

```json
{
  "status": "error",
  "message": "Unauthenticated."
}
```

### Response Error (404 Not Found)

```json
{
  "status": "error",
  "message": "Pengajuan operasional tidak ditemukan."
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

### Request Body (Multipart / JSON)

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `catatan` | string | ❌ | Catatan verifikasi penutupan kasbon dinas |
| `nominal_pelunasan` | numeric | ❌ | Nominal dana reimburse / pengembalian lebih bayar |
| `file_bukti_pelunasan` | file (image/pdf) | ❌ | Foto/bukti transfer pelunasan kasbon dinas (maks 10MB) |

```json
{
  "catatan": "LPJ dinas telah diverifikasi, reimbursement selisih telah ditransfer ke rekening pegawai",
  "nominal_pelunasan": 150000
}
```

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Transaksi perjalanan dinas berhasil diverifikasi dan diselesaikan",
  "data": {
    "id": 10,
    "status": "selesai",
    "tipe_pelunasan": "reimbursement",
    "nominal_pelunasan": 150000.00,
    "bukti_pelunasan_url": "http://localhost:8000/api/files/view?path=..."
  }
}
```

---

## Ref akuntansi & pelacakan keuangan

> Pencairan `JRN-EXP` (`jenis_sumber = pencairan_kas`), reimbursement `JRN-RMB` (`jenis_sumber = reimbursement`); keduanya `referensi_id = pengajuan.id` + nomor pengajuan di keterangan. `GET detail pengajuan` menyertakan relasi `jurnal` di dalam `data` (induk + anak RMB). `GET jurnal` mendukung `?referensi_id=`, `?nomor_pengajuan=` (ikut menarik jurnal anak RMB), `?jenis_sumber=reimbursement`; detail jurnal menyertakan relasi `referensi` di dalam `data`. Daftar pengajuan mendukung `?butuh_reimburse=1` (sisa < 0), `?belum_diajukan_reimburse=1` (defisit tanpa RMB aktif), `?belum_cair=1` (disetujui tapi belum dicairkan).

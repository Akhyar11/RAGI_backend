# PembayaranKasirController

> **Modul**: SIKEU — Keuangan  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 10 September 2026  
> **Diperbarui**: 10 September 2026

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| POST | `/api/v1/sikeu/pembayaran/kasir` | Proses pembayaran kasir/loket offline | ✅ operator_sikeu, kabag_keuangan |
| POST | `/api/v1/sikeu/pembayaran/{id}/koreksi` | Koreksi/batalkan transaksi pembayaran | ✅ kabag_keuangan |
| POST | `/api/v1/sikeu/tagihan/generate-mass` | Generate tagihan semester secara masal | ✅ operator_sikeu, kabag_keuangan |

---

## POST /api/v1/sikeu/pembayaran/kasir

> Memproses pembayaran di loket kasir kampus. Otomatis validasi tutup buku dan generate jurnal akuntansi.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "tagihan_id": 1,
    "jumlah_bayar": 3500000,
    "channel_bayar": "LOKET_TUNAI",
    "catatan": "Pembayaran tunai UKT semester ganjil"
}
```

| Field | Type | Required | Deskripsi |
|---|---|---|---|
| `tagihan_id` | integer | ✅ | ID tagihan mahasiswa yang akan dibayar |
| `jumlah_bayar` | numeric | ✅ | Nominal pembayaran (min: 1) |
| `channel_bayar` | enum | ✅ | `LOKET_TUNAI` atau `LOKET_TRANSFER` |
| `catatan` | string | ❌ | Catatan tambahan (maks 500 karakter) |

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Pembayaran kasir berhasil diproses.",
    "data": {
        "pembayaran": {
            "id": 5,
            "tagihan_id": 1,
            "jumlah_bayar": 3500000,
            "waktu_bayar": "2026-09-10T14:30:00.000000Z",
            "channel_bayar": "LOKET_TUNAI",
            "status": "success",
            "kode_transaksi": "TRX-LOKET-20260910-AB12C",
            "diverifikasi_oleh": 3
        },
        "kuitansi": {
            "kode_transaksi": "TRX-LOKET-20260910-AB12C",
            "tanggal": "2026-09-10 14:30:00",
            "mahasiswa_id": 7,
            "nomor_tagihan": "INV-SIAKAD-20260801-00007",
            "jumlah_bayar": 3500000,
            "channel": "LOKET_TUNAI",
            "sisa_setelah_bayar": 0,
            "status_tagihan": "lunas",
            "kasir": "kasir_sikeu"
        }
    }
}
```

### Response Error

**422 — Jumlah bayar melebihi sisa**
```json
{
    "status": "error",
    "message": "Jumlah bayar (Rp 5.000.000) melebihi sisa tagihan (Rp 3.500.000)."
}
```

**403 — Periode tutup buku**
```json
{
    "status": "error",
    "message": "Periode akuntansi \"Periode Agustus 2026\" sudah ditutup untuk tanggal ini. Tidak dapat memproses transaksi."
}
```

### Catatan Tambahan

> - Basis akrual: penerbitan tagihan mencatat `Dr 103.01 Piutang / Cr 401.01 (UKT) atau 401.02 (SPMB)`; pembayaran mencatat `Dr Kas/Bank / Cr 103.01 Piutang`; koreksi membaliknya (`Dr Piutang / Cr Kas`).
> - Setiap pembayaran kasir otomatis menghasilkan **Jurnal Umum**; bila COA wajib belum terkonfigurasi, transaksi **gagal eksplisit** (tidak lagi diam-diam tanpa jurnal).
> - Sistem memeriksa `sikeu_periode_akuntansi` untuk memastikan transaksi tidak jatuh pada periode yang sudah ditutup.
> - Status tagihan otomatis berubah ke `lunas` jika total bayar ≥ total bersih, atau `sebagian` jika belum cukup.
> - **Alokasi FIFO per komponen**: setiap pembayaran didistribusikan ke rincian (`sikeu_detail_tagihan.terbayar`) mulai dari komponen tertua; koreksi/pengalihan keluar mengembalikannya secara LIFO. Kekurangan per komponen = `nominal_bersih − terbayar`, terlihat di `details[].sisa` (unpaid-bills), `rincian_komponen[].sisa` (list tagihan), dan `kuitansi.rincian_komponen`.
> - Berlaku untuk calon mahasiswa (daftar ulang/SPMB) maupun mahasiswa aktif (UKT): kasir dapat memproses keduanya, bahkan campuran dalam satu batch, karena alokasi berbasis `tagihan_id`.

---

## POST /api/v1/sikeu/pembayaran/{id}/koreksi

> Membatalkan/mengoreksi transaksi pembayaran yang sudah tercatat. Menggunakan jurnal pembalik (reversal), bukan hard-delete.

### Request Body

```json
{
    "alasan_koreksi": "Salah input jumlah bayar, seharusnya Rp 2.500.000 bukan Rp 3.500.000"
}
```

| Field | Type | Required | Deskripsi |
|---|---|---|---|
| `alasan_koreksi` | string | ✅ | Alasan koreksi (min: 10 karakter) |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Koreksi pembayaran berhasil. Transaksi TRX-LOKET-20260910-AB12C telah dibatalkan.",
    "data": {
        "pembayaran": {
            "id": 5,
            "status": "reversed"
        },
        "tagihan_status_baru": "belum_bayar",
        "total_bayar_baru": 0
    }
}
```

### Response Error

**422 — Sudah pernah dikoreksi**
```json
{
    "status": "error",
    "message": "Transaksi ini sudah pernah dikoreksi/dibatalkan sebelumnya."
}
```

---

## POST /api/v1/sikeu/tagihan/generate-mass

> Generate tagihan semester secara masal untuk seluruh mahasiswa yang cocok dengan filter angkatan/jalur/semester.

### Request Body

```json
{
    "tahun_angkatan": 2025,
    "jalur_kelas": "Reguler",
    "semester": 3,
    "program_studi_id": null,
    "jatuh_tempo": "2026-09-30",
    "semester_label": "Semester Ganjil 2026/2027"
}
```

| Field | Type | Required | Deskripsi |
|---|---|---|---|
| `tahun_angkatan` | integer | ✅ | Tahun angkatan (2020-2040) |
| `jalur_kelas` | string | ✅ | Jalur kelas (Reguler/Karyawan/Internasional) |
| `semester` | integer | ❌ | Semester target (1-14) |
| `program_studi_id` | integer | ❌ | ID program studi (null = semua prodi) |
| `jatuh_tempo` | date | ✅ | Tanggal jatuh tempo (harus setelah hari ini) |
| `semester_label` | string | ❌ | Label semester untuk deskripsi tagihan |

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Berhasil menerbitkan 45 tagihan masal untuk Semester Ganjil 2026/2027.",
    "data": {
        "total_mahasiswa_eligible": 50,
        "total_tagihan_generated": 45,
        "total_skipped": 5,
        "total_nominal_per_mahasiswa": 4250000,
        "semester_label": "Semester Ganjil 2026/2027",
        "jatuh_tempo": "2026-09-30"
    }
}
```

### Response Error

**404 — Tidak ada setting tarif**
```json
{
    "status": "error",
    "message": "Tidak ditemukan setting tarif yang cocok untuk kombinasi Angkatan 2025 / Reguler. Silakan atur setting tarif terlebih dahulu di menu Master."
}
```

### Catatan Tambahan

> - Endpoint ini menggunakan data dari tabel `sikeu_setting_tarif` dan `sikeu_mahasiswa_tipe_tagihan`.
> - Mahasiswa yang sudah memiliki tagihan dengan nomor yang sama (duplikat) akan dilewati.
> - Semua operasi dibungkus dalam `DB::beginTransaction()` untuk konsistensi data.

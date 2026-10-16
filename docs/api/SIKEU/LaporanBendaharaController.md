# LaporanBendaharaController

> **Modul**: SIKEU
> **Base URL**: `/api/v1/sikeu/laporan-bendahara`
> **Autentikasi**: Bearer Token (menu SIKEU)
> **Otorisasi**: `sikeu.laporan.read` — dicek eksplisit di setiap method
> **Dibuat**: 2026-10-17
> **Diperbarui**: 2026-10-17

Parity legacy `menuLaporan` (`routes/laporan/laporan.js`), basis **kas**
(pembayaran `success` + mutasi kas unit; koreksi sebagai baris negatif).
Laporan akuntansi akrual tetap di `AkuntansiController::laporanKeuangan`.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/laporan-bendahara/saldo-harian?tanggal=` | Saldo masuk/keluar/bersih harian | ✅ |
| GET | `/api/v1/sikeu/laporan-bendahara/saldo-bulanan?tanggal=YYYY-MM` | Saldo bulanan | ✅ |
| GET | `/api/v1/sikeu/laporan-bendahara/harian?tanggal=` | Harian + saldo awal + UMK aktif | ✅ |
| GET | `/api/v1/sikeu/laporan-bendahara/bulanan?tanggal=YYYY-MM` | Bulanan + saldo awal + UMK aktif | ✅ |
| GET | `/api/v1/sikeu/laporan-bendahara/periodik?awal=&akhir=` | Garis transaksi rentang + filter kode | ✅ |
| GET | `/api/v1/sikeu/laporan-bendahara/perakun?awal=&akhir=` | Agregat per kode akun | ✅ |
| GET | `/api/v1/sikeu/laporan-bendahara/rekap-mahasiswa?mahasiswa_id=` | Tagihan vs bayar vs potongan + history | ✅ |
| POST | `/api/v1/sikeu/laporan-bendahara/rekap-perprodikelas` | Batch per prodi/kelas (maks 500) | ✅ |
| GET | `/api/v1/sikeu/laporan-bendahara/export-excel?jenis=` | Unduh `.xls` | ✅ |

---

## Request Headers

| Header | Type | Value | Deskripsi |
|---|---|---|---|
| Authorization | string | Bearer `<token>` | Token autentikasi pengguna |
| Accept | string | application/json | Format balasan yang diharapkan |
| Content-Type | string | application/json | Wajib untuk POST rekap-perprodikelas |

---

## Query Umum

| Parameter | Type | Deskripsi |
|---|---|---|
| `unit_kas_id` | integer | Filter unit kas (`exists:sikeu_unit_kas,id`) |
| `metode` | string | `OFFLINE`/`TUNAI` = tunai loket; selain itu non-tunai |
| `search` | string | Cari kode/uraian/nama/NIM |
| `per_page` | integer | Default `15`, maks `100` |
| `page` | integer | Default `1` |

Tunai = `channel_bayar IN (LOKET_TUNAI)`; non-tunai = komplemennya.

---

## GET saldo-harian

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tanggal` | date | ✅ | — | Hari laporan (YYYY-MM-DD) |
| `unit_kas_id` | integer | ❌ | — | Filter unit kas |
| `metode` | string | ❌ | `OFFLINE` | `OFFLINE` / non-tunai |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Saldo harian berhasil dimuat",
    "data": {
        "tanggal": "2026-10-15",
        "saldo_masuk": 2500000, "saldo_keluar": 250000, "saldo_bersih": 2250000,
        "rincian": {"pembayaran_mahasiswa": 2000000, "kas_masuk_lain": 500000, "kas_keluar": 250000}
    }
}
```

---

## GET saldo-bulanan

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tanggal` | string | ✅ | — | Bulan laporan (`YYYY-MM`) |
| `unit_kas_id` | integer | ❌ | — | Filter unit kas |
| `metode` | string | ❌ | `OFFLINE` | `OFFLINE` / non-tunai |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Saldo bulanan berhasil dimuat",
    "data": {"bulan": "2026-10", "awal": "2026-10-01", "akhir": "2026-10-31", "saldo_masuk": 15000000, "saldo_keluar": 3000000, "saldo_bersih": 12000000}
}
```

---

## GET harian / bulanan

Meniru chain legacy: **saldo awal bulan berjalan wajib dari tutup kas
bulan lalu** (per unit bila `unit_kas_id` diisi).

### Query Parameters (harian)

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tanggal` | date | ✅ | — | `before_or_equal:today` |
| `unit_kas_id` | integer | ❌ | — | Filter unit kas |
| `metode` | string | ❌ | `OFFLINE` | Metode kas |
| `search` | string | ❌ | — | Cari transaksi |
| `sort_by` | string | ❌ | `tanggal` | `tanggal` / `jumlah` |
| `sort_order` | string | ❌ | `asc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

### Query Parameters (bulanan)

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tanggal` | string | ✅ | — | Bulan laporan (`YYYY-MM`, tidak boleh masa depan) |
| `unit_kas_id` | integer | ❌ | — | Filter unit kas |
| `metode` | string | ❌ | `OFFLINE` | Metode kas |
| `search` | string | ❌ | — | Cari transaksi |
| `sort_by` | string | ❌ | `tanggal` | `tanggal` / `jumlah` |
| `sort_order` | string | ❌ | `asc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

### Query Parameters (harian)

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tanggal` | date | ✅ | — | `before_or_equal:today` |
| `unit_kas_id` | integer | ❌ | — | Filter unit kas |
| `metode` | string | ❌ | `OFFLINE` | Metode kas |
| `search` | string | ❌ | — | Cari transaksi |
| `sort_by` | string | ❌ | `tanggal` | `tanggal` / `jumlah` |
| `sort_order` | string | ❌ | `asc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Laporan harian berhasil dimuat",
    "data": {
        "tanggal": "2026-10-15",
        "saldo_awal": 5000000,
        "saldo_akhir": 7250000,
        "saldo_periode": {"saldo_masuk": 2500000, "saldo_keluar": 250000, "saldo_bersih": 2250000},
        "umk_aktif": [{"id": 1, "nama_penerima": "Bendahara", "total": "1500000.00", "status": "aktif"}],
        "transaksi": [
            {"id": "bayar-10", "kode_transaksi": "TRX-LOKET-...", "jumlah": 2000000, "jenis_transaksi": "masuk"},
            {"id": "koreksi-9", "uraian": "KOREKSI ...", "jumlah": -500000, "jenis_transaksi": "masuk", "channel": "KOREKSI"},
            {"id": "kas-4", "uraian": "Belanja ATK", "jumlah": -250000, "jenis_transaksi": "keluar"}
        ]
    },
    "meta": {"current_page": 1, "per_page": 15, "total": 3, "last_page": 1, "from": 1, "to": 3}
}
```

### Response Error

**422** bila belum tutup bulan lalu:
```json
{"status": "error", "message": "Bulan kemarin belum tutup buku."}
```

---

## GET periodik

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `awal` | date | ✅ | — | Tanggal mulai |
| `akhir` | date | ✅ | — | `after_or_equal:awal` |
| `code` | string | ❌ | — | Prefix kode akun (`Semua`/`0` = semua) |
| `only_mhs` | boolean | ❌ | `false` | Hanya baris bertagihan mahasiswa |
| `search` | string | ❌ | — | Cari transaksi |
| `sort_by` | string | ❌ | `tanggal` | `tanggal` / `jumlah` |
| `sort_order` | string | ❌ | `asc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Laporan periodik berhasil dimuat",
    "data": [{"id": "bayar-10", "tanggal": "2026-10-15 09:00:00", "kode_transaksi": "TRX-LOKET-...", "jumlah": 2000000, "jenis_transaksi": "masuk"}],
    "meta": {"current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1}
}
```

---

## GET perakun

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `awal` | date | ✅ | — | Tanggal mulai |
| `akhir` | date | ✅ | — | `after_or_equal:awal` |
| `code` | string | ❌ | — | Prefix kode akun |

Agregat `SUM(jumlah) GROUP BY kode_akun` dari garis periodik yang sama.

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Laporan per akun berhasil dimuat",
    "data": [{"kode_akun": "101.01", "jumlah": 2000000, "jumlah_transaksi": 1}]
}
```

---

## GET rekap-mahasiswa

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `mahasiswa_id` | integer | ✅ | `exists:siakad_mahasiswa,id` |
| `cutoff` | date | ❌ | Bayar dihitung s.d. tanggal ini |

Rumus per tagihan: `kekurangan = max(0, tagihan+denda-potongan-bayar)`;
beasiswa aktif menolkan kekurangan. History memuat baris `reversed` negatif.

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Rekap pembayaran mahasiswa berhasil dimuat",
    "data": {
        "mahasiswa_id": 1, "ada_beasiswa": false,
        "ringkasan": {"total_tagihan": 5000000, "total_bayar": 2000000, "total_potongan": 0, "total_kekurangan": 3000000, "jumlah_tagihan": 1},
        "tagihan": [{"tagihan_id": 1, "nomor_tagihan": "INV-...", "kekurangan": 3000000}],
        "history": [{"kode_transaksi": "TRX-...", "jumlah": 2000000, "status": "success"}]
    }
}
```

### Response Error

**404 Not Found** — mahasiswa/tagihan tidak ada; **422** — validasi gagal.

---

## POST rekap-perprodikelas

### Request Body

```json
{
    "data": [{"mahasiswa_id": 1, "nim": "2024001", "nama": "Budi"}],
    "batas_semester": "integer, nullable, 1-14",
    "batas_tanggal": "date, nullable, before_or_equal:today"
}
```

Maksimal 500 baris (`422` bila lebih).

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Rekap per prodi/kelas berhasil dimuat",
    "data": [{"mahasiswa_id": 1, "total_tagihan": 5000000, "total_bayar": 2000000, "kekurangan": 3000000}],
    "header": ["UKT Semester 1"],
    "ringkasan": {"mahasiswa": 1, "total_tagihan": 5000000, "total_bayar": 2000000, "total_kekurangan": 3000000}
}
```

---

## GET export-excel

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `jenis` | string | ✅ | `harian` / `bulanan` / `periodik` / `perakun` |
| `tanggal` | date / `YYYY-MM` | ⚠️ | Wajib untuk harian/bulanan |
| `awal` / `akhir` | date | ⚠️ | Wajib untuk periodik/perakun |
| `unit_kas_id` | integer | ❌ | Filter unit kas |
| `metode` | string | ❌ | Filter metode |
| `code` | string | ❌ | Prefix kode akun |

Mengembalikan stream `.xls` + mencatat audit log (`SIKEU/export/sikeu_pembayaran`).
Format HTML-table mengikuti `PiutangMahasiswaController::exportExcel`.

---

## Response Error Umum

**401 Unauthorized**
```json
{"status": "error", "message": "Token tidak valid atau sesi telah berakhir."}
```

**403 Forbidden**
```json
{"status": "error", "message": "Anda tidak memiliki akses laporan bendahara."}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {"tanggal": ["Tanggal tidak valid."]}
}
```

### Catatan Tambahan

> - Non-tunai melewati saldo awal (`saldo_awal=0`), mengikuti legacy.
> - `TANPA_KODE` = baris tanpa pemetaan akun kas (mis. koreksi).

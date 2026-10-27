# SaldoAwalController

> **Modul**: SIKEU — Akuntansi (Saldo Awal / Opening Balance)  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (auth:api)  
> **Dibuat**: 2026-10-27  
> **Diperbarui**: 2026-10-27

Fitur saldo awal dipakai saat cut-over/migrasi agar neraca memiliki saldo awal yang sah
tanpa mengubah saldo operasional unit kas. Alur kerja: **simpan draft → posting → batal posting**.

- Hanya akun **neraca** (`aset`, `liabilitas`, `ekuitas`) yang boleh diisi. Akun pendapatan/beban ditolak.
- Selisih total debet dan kredit otomatis dibukukan ke akun ekuitas penyeimbang `301.03`
  (*Ekuitas Saldo Awal*) sehingga jurnal selalu seimbang. Akun ini tidak boleh dipilih manual.
- Posting membentuk jurnal `sikeu_jurnal_umum` dengan `jenis_sumber = penyesuaian`,
  prefix nomor `JRN-OPEN`, dan `referensi_id` = id header saldo awal (jurnal terkunci dari edit/hapus biasa).
- Satu akun hanya boleh muncul satu kali dalam satu draft.
- Tanggal saldo awal tidak boleh berada pada periode akuntansi yang sudah ditutup.
- **Cut-off**: saldo awal adalah jurnal paling awal di buku. Posting ditolak bila sudah ada
  jurnal `posted` lain pada/sebelum tanggal saldo awal, karena transaksi lama akan terhitung dua kali.
  Migrasikan jurnal legacy terlebih dahulu, lalu tetapkan tanggal saldo awal setelah transaksi terakhir;
  atau jangan impor jurnal legacy yang sudah tercakup dalam saldo awal.
- Command migrasi jurnal legacy (`sikeu:sync-legacy`, `migrate:sikeu-legacy-sql`, `migrate:sikeu-kas`)
  otomatis menolak tanggal yang berada pada/sebelum cut-off saldo awal.
- Setelah diposting, draft tidak dapat diubah. Pembatalan ditolak bila sudah ada jurnal lain
  setelah tanggal saldo awal.
- Audit pasca cut-over: `php artisan sikeu:saldo-awal:audit` (tambahkan `--json` untuk output mesin).
  Command ini memeriksa jurnal sebelum cut-off, jurnal tanpa detail, jurnal tidak seimbang,
  neraca saldo, dan selisih `sikeu_unit_kas.saldo_saat_ini` vs buku besar.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/akuntansi/saldo-awal` | Data saldo awal aktif + COA neraca + akun penyeimbang + riwayat | ✅ `sikeu.akuntansi.read` |
| PUT | `/api/v1/sikeu/akuntansi/saldo-awal` | Simpan/perbarui draft saldo awal | ✅ `sikeu.akuntansi.manage` |
| POST | `/api/v1/sikeu/akuntansi/saldo-awal` | Posting draft menjadi jurnal umum | ✅ `sikeu.akuntansi.manage` |
| DELETE | `/api/v1/sikeu/akuntansi/saldo-awal` | Hapus draft / batalkan posting | ✅ `sikeu.akuntansi.manage` |

> Keempat aksi memakai URL yang sama agar pemetaan menu (`/sikeu/akuntansi/saldo-awal`)
> pada middleware `CheckMenuAccess` konsisten. Semua aksi dicatat ke `audit_logs`.

---

## GET /api/v1/sikeu/akuntansi/saldo-awal

> Memuat header saldo awal (draft diutamakan, fallback posted), daftar COA neraca aktif,
> akun penyeimbang `301.03`, dan 20 riwayat terakhir.

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
    "message": "Data saldo awal berhasil dimuat.",
    "data": {
        "saldo_awal": {
            "id": 1,
            "tanggal": "2026-01-01",
            "periode_id": 1,
            "status": "draft",
            "keterangan": "Cut over migrasi sistem lama",
            "total_debet": 100000000,
            "total_kredit": 100000000,
            "nominal_penyeimbang": 80000000,
            "akun_penyeimbang_id": 9,
            "jurnal_id": null,
            "details": [
                {
                    "id": 1,
                    "saldo_awal_id": 1,
                    "akun_id": 2,
                    "debet": 100000000,
                    "kredit": 0,
                    "keterangan": null,
                    "akun": { "id": 2, "kode_akun": "102.01", "nama_akun": "Kas Bank" }
                }
            ],
            "akun_penyeimbang": { "id": 9, "kode_akun": "301.03", "nama_akun": "Ekuitas Saldo Awal" },
            "jurnal": null,
            "periode": { "id": 1, "nama_periode": "Januari 2026", "status": "terbuka" }
        },
        "akun_penyeimbang": { "id": 9, "kode_akun": "301.03", "nama_akun": "Ekuitas Saldo Awal" },
        "coa": [
            { "id": 2, "kode_akun": "102.01", "nama_akun": "Kas Bank", "kelompok": "aset", "saldo_normal": "debet" }
        ],
        "validasi": {
            "tanggal_saldo_awal": "2026-01-01",
            "jumlah_jurnal_sebelum": 0,
            "tanggal_jurnal_terawal": null,
            "siap_posting": true,
            "pesan": "Tidak ada jurnal lain pada/sebelum tanggal saldo awal. Saldo awal siap diposting."
        },
        "riwayat": [
            { "id": 1, "tanggal": "2026-01-01", "status": "draft", "details_count": 2 }
        ]
    }
}
```

`saldo_awal` bernilai `null` bila belum ada data.

### Response Error

**401 Unauthorized**
```json
{ "status": "error", "message": "Token tidak valid atau sesi telah berakhir." }
```

**403 Forbidden**
```json
{ "status": "error", "message": "Anda tidak memiliki izin untuk mengakses menu ini." }
```

---

## PUT /api/v1/sikeu/akuntansi/saldo-awal

> Menyimpan atau memperbarui draft saldo awal. Bila sudah ada draft, seluruh baris draft
> diganti dengan payload terbaru. Ditolak bila sudah ada saldo awal berstatus `posted`.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "tanggal": "2026-01-01",
    "keterangan": "Cut over migrasi sistem lama",
    "details": [
        { "akun_id": 2, "debet": 100000000, "kredit": 0, "keterangan": "Saldo kas bank" },
        { "akun_id": 5, "debet": 0, "kredit": 20000000, "keterangan": "Utang pajak" }
    ]
}
```

| Field | Type | Required | Deskripsi |
|---|---|---|---|
| `tanggal` | date | ✅ | Tanggal saldo awal (`YYYY-MM-DD`) |
| `keterangan` | string | ❌ | Maks. 1000 karakter |
| `details` | array | ✅ | Minimal 1 baris |
| `details.*.akun_id` | integer | ✅ | Harus akun aktif kelompok `aset`/`liabilitas`/`ekuitas`, bukan `301.03` |
| `details.*.debet` | number | ✅ | ≥ 0, tidak boleh negatif |
| `details.*.kredit` | number | ✅ | ≥ 0, tidak boleh negatif |
| `details.*.keterangan` | string | ❌ | Maks. 255 karakter |

> Setiap baris wajib memiliki debet atau kredit > 0 dan tidak boleh keduanya sekaligus.

### Response Sukses

**201 Created** — draft baru dibuat
```json
{
    "status": "success",
    "message": "Draft saldo awal berhasil disimpan.",
    "data": {
        "id": 1,
        "tanggal": "2026-01-01",
        "status": "draft",
        "total_debet": 100000000,
        "total_kredit": 100000000,
        "nominal_penyeimbang": 80000000,
        "akun_penyeimbang_id": 9,
        "details": []
    }
}
```

`nominal_penyeimbang` bertanda: **positif** = tambahan kredit ke ekuitas `301.03`,
**negatif** = tambahan debet ke ekuitas.

> **200 OK** dikembalikan bila sudah ada draft dan payload menimpa draft tersebut
> (`id` tetap). Aksi `PUT` ini tercatat pada audit log sebagai `create` (draft baru)
> atau `update` (draft diperbarui).

### Response Error

**422 Unprocessable Entity — validasi**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "details.2.akun_id": ["Akun tidak valid, tidak aktif, atau bukan akun neraca (aset/liabilitas/ekuitas)."]
    }
}
```

**400 Bad Request — aturan bisnis (`DomainException`)**
```json
{
    "status": "error",
    "message": "Saldo awal sudah diposting. Batalkan posting terlebih dahulu sebelum mengubah data."
}
```

---

## POST /api/v1/sikeu/akuntansi/saldo-awal

> Memposting draft saldo awal menjadi jurnal umum berstatus `posted`. Tidak memerlukan body.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Saldo awal berhasil diposting ke jurnal umum.",
    "data": {
        "id": 1,
        "status": "posted",
        "jurnal_id": 12,
        "total_debet": 100000000,
        "total_kredit": 100000000,
        "jurnal": {
            "id": 12,
            "nomor_jurnal": "JRN-OPEN20260101-A1B2",
            "jenis_sumber": "penyesuaian",
            "status_posting": "posted"
        }
    }
}
```

### Response Error

**400 Bad Request**
```json
{
    "status": "error",
    "message": "Tidak ada draft saldo awal yang bisa diposting."
}
```

Kemungkinan pesan lain: draft tanpa baris akun, tanggal pada periode tertutup,
akun penyeimbang `301.03` belum ada di COA, atau sudah ada jurnal lain pada/sebelum
tanggal saldo awal (cut-off belum aman).

**400 Bad Request — cut-off bentrok**
```json
{
    "status": "error",
    "message": "Ada 12 jurnal posted pada/sebelum 01/01/2026 (jurnal terawal 05/01/2025). Saldo awal tidak boleh diposting karena transaksi lama akan terhitung dua kali. Migrasikan data lama terlebih dahulu, lalu tetapkan tanggal saldo awal setelah transaksi terakhir; atau kosongkan jurnal legacy yang sudah tercakup oleh saldo awal."
}
```

---

## DELETE /api/v1/sikeu/akuntansi/saldo-awal

> Menghapus draft (bila masih draft) atau membatalkan posting (menghapus jurnal dan
> mengembalikan header menjadi draft). Tidak memerlukan body.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses

**200 OK — hapus draft**
```json
{
    "status": "success",
    "message": "Draft saldo awal berhasil dihapus.",
    "data": null
}
```

**200 OK — batal posting**
```json
{
    "status": "success",
    "message": "Posting saldo awal berhasil dibatalkan dan dikembalikan menjadi draft.",
    "data": null
}
```

### Response Error

**400 Bad Request**
```json
{
    "status": "error",
    "message": "Saldo awal tidak dapat dibatalkan karena sudah ada jurnal lain setelah tanggal saldo awal. Batalkan jurnal tersebut terlebih dahulu."
}
```

Pembatalan juga ditolak bila tanggal jurnal berada pada periode yang sudah ditutup.

---

## Catatan Tambahan

> - Akun `301.03` *Ekuitas Saldo Awal* dibuat oleh migrasi `2026_10_27_000001_create_sikeu_saldo_awal_tables.php`
>   dan juga tersedia lewat `SikeuAkuntansiSeeder` (idempoten).
> - Jurnal saldo awal tidak dapat diubah/dihapus melalui endpoint jurnal umum karena memiliki `referensi_id`.
> - Nomor jurnal memakai prefix `JRN-OPEN` yang dapat diubah pada Pengaturan Akuntansi (`sikeu.jurnal_prefix_saldo_awal`).
> - Laporan neraca (`GET /api/v1/sikeu/akuntansi/laporan`) membaca saldo awal ini secara otomatis,
>   termasuk rincian `aset_lainnya`, `utang_lainnya`, `laba_ditahan_301`, `total_liabilitas`, dan `total_ekuitas`.

---

## Alur Migrasi yang Aman (menghindari saldo "crash")

1. **Tentukan cut-off** — tanggal berakhirnya data lama dan tanggal mulai buku baru.
2. **Pilih satu sumber kebenaran**:
   - **Opsi A — saldo awal saja (disarankan)**: migrasikan master data dan dokumen terbuka
     (tagihan/pembayaran), tetapi **jangan** impor jurnal historis. Posting saldo awal per tanggal cut-off.
   - **Opsi B — jurnal historis lengkap**: impor seluruh jurnal legacy, lalu **jangan** isi saldo awal
     (biarkan nol) karena saldo sudah terbentuk dari jurnal.
3. Jangan mencampur keduanya: jurnal historis **dan** saldo awal pada periode yang sama akan
   menghitung transaksi dua kali.
4. Setelah posting saldo awal, command migrasi jurnal legacy menolak tanggal `<=` cut-off.
5. Jalankan `php artisan sikeu:saldo-awal:audit` dan pastikan hasilnya bersih sebelum tutup buku.
6. Bila `rekonsiliasi_kas_unit` berselisih, samakan `sikeu_unit_kas.saldo_saat_ini` dengan saldo
   buku besar akun kas/bank yang dipetakan (`akun_keuangan_id`) — jangan mengubah jurnal manual
   untuk menutupi selisih.

# PajakPelaporanController

> **Modul**: SIKEU (Keuangan & Akuntansi)
> **Base URL**: `/api/v1/sikeu`
> **Autentikasi**: OAuth2 Bearer Token (Passport)
> **Dibuat**: 2026-11-09
> **Diperbarui**: 2026-11-09

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/pajak/pelaporan-akun` | Daftar COA dan alias pajak, termasuk status mode | ✅ Auditor Keuangan |
| PUT | `/api/v1/sikeu/pajak/pelaporan-akun/{akunId}` | Simpan atau kosongkan nama akun pelaporan pajak | ✅ Auditor Keuangan |
| PUT | `/api/v1/sikeu/pajak/pelaporan-akun/mode` | Aktifkan publikasi alias atau kembalikan ke nama akun sistem | ✅ Auditor Keuangan |

Semua endpoint memerlukan permission `sikeu.pajak_akun.manage`. Role `auditor_keuangan` memperoleh permission ini; role tersebut tetap tidak memperoleh hak untuk membuat atau mengubah transaksi.

## GET /api/v1/sikeu/pajak/pelaporan-akun

Daftar COA untuk pemetaan nama akun pelaporan. Alias disimpan terpisah dari COA asli.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari kode akun, nama sistem, atau alias |
| `sort_by` | string | ❌ | `kode_akun` | Kolom pengurutan yang didukung |
| `sort_order` | string | ❌ | `asc` | `asc` atau `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah baris per halaman, maksimum 100 |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses

```json
{
  "status": "success",
  "message": "Daftar alias akun pelaporan pajak berhasil dimuat.",
  "data": [
    {
      "id": 1,
      "kode_akun": "101.01",
      "nama_akun": "Kas Tunai",
      "nama_akun_pajak": "Kas dan Setara Kas Pajak"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 1,
    "last_page": 1,
    "from": 1,
    "to": 1
  },
  "mode": "dev"
}
```

## PUT /api/v1/sikeu/pajak/pelaporan-akun/{akunId}

Menyimpan alias tanpa mengubah `sikeu_akun_keuangan.nama_akun`. Kirim string kosong untuk menghapus alias.

### Request Body

```json
{
  "nama_akun_pajak": "Kas dan Setara Kas Pajak"
}
```

## PUT /api/v1/sikeu/pajak/pelaporan-akun/mode

Mode `dev` hanya menyimpan alias untuk diedit oleh auditor. Mode `publik` menampilkan alias pada COA, jurnal, dan buku besar kepada pengguna yang berhak melihat akuntansi. Mengembalikan mode ke `dev` menampilkan kembali nama COA asli. Tidak ada perubahan pada nilai, detail, status posting, atau saldo jurnal.

### Request Body

```json
{
  "mode": "publik"
}
```

### Response Sukses

```json
{
  "status": "success",
  "message": "Mode pelaporan pajak publik diaktifkan.",
  "data": {
    "mode": "publik"
  }
}
```

# PajakKampusController

> **Modul**: SIKEU (Keuangan)  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Sanctum / JWT)  
> **Dibuat**: 2026-08-18  
> **Diperbarui**: 2026-11-09

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/pajak` | List kewajiban pajak terutang & sudah disetor (PPh 21/23/PPN) | ✅ Staf Keuangan |
| POST | `/api/v1/sikeu/pajak/{id}/setor` | Catat bukti setor pajak dan jurnal penyetoran dari unit kas terpilih | ✅ Staf Keuangan |

## GET `/api/v1/sikeu/pajak`

Mengambil kewajiban pajak dari transaksi pengeluaran, termasuk status setoran dan ringkasan total.

| Query Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `jenis` | string | ❌ | Filter `pph_21`, `pph_23`, `ppn_11`, atau `semua` |
| `status` | string | ❌ | `terutang`, `disetor`, atau `semua` |
| `search` | string | ❌ | Cari nomor transaksi, vendor, atau NTPN |
| `per_page` | integer | ❌ | Jumlah data per halaman, default 20, maksimum 100 |
| `page` | integer | ❌ | Halaman |

**200 OK**
```json
{
    "status": "success",
    "message": "Data pajak kampus berhasil dimuat",
    "data": [
        {
            "id": 18,
            "nomor_transaksi_asal": "EXP-OPE-20261109-AB12CD34",
            "jenis": "PPh 23",
            "jenis_raw": "pph_23",
            "nominal": 200,
            "status": "terutang",
            "ntpn": "-"
        }
    ],
    "meta": { "current_page": 1, "per_page": 20, "total": 1, "last_page": 1 },
    "summary": { "total_terutang": 200, "total_disetor": 0, "total_keseluruhan": 200 }
}
```

## POST `/api/v1/sikeu/pajak/{id}/setor`

Mencatat NTPN dan tanggal setor, mengurangi saldo unit kas, lalu membuat jurnal penyetoran melalui `JurnalSikeuService`. Kewajiban dan jurnal asal tidak ditimpa. Unit kas harus aktif, memiliki COA kas/bank yang dipetakan, dan memiliki saldo cukup. COA utang pajak dipilih eksplisit sesuai jenis PPh 21, PPh 23, atau PPN; transaksi dibatalkan bila pemetaan COA tidak tersedia.

### Request Body

```json
{
    "ntpn": "NTPN-2026-000001",
    "tanggal_setor": "2026-11-09",
    "unit_kas_id": 2
}
```

| Field | Type | Required | Deskripsi |
|---|---|---|---|
| `ntpn` | string | ✅ | Nomor bukti setor, 6-50 karakter |
| `tanggal_setor` | date | ❌ | Tanggal penyetoran; default tanggal hari ini |
| `unit_kas_id` | integer | ✅ | ID unit kas aktif dengan COA kas/bank terpetakan |

**200 OK**
```json
{
    "status": "success",
    "message": "Penyetoran pajak berhasil dicatat dengan NTPN: NTPN-2026-000001.",
    "data": {
        "id": 18,
        "status": "disetor",
        "ntpn": "NTPN-2026-000001",
        "tanggal_setor": "2026-11-09",
        "jurnal_id": 27
    }
}
```

**422 Unprocessable Entity** — input tidak valid, kewajiban telah disetor, saldo kurang, atau COA unit/utang pajak belum dipetakan. Kegagalan tidak mengubah status transaksi, saldo unit kas, atau jurnal.

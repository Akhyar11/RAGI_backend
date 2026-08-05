# ExternalTagihanController

> **Modul**: SIKEU (Keuangan)  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-08-05  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| POST | `/api/v1/sikeu/tagihan/external` | Penerbitan Tagihan Eksternal (SPMB, SIAKAD, SIMPEG, SIPPM) | ✅ Bearer |
| GET | `/api/v1/sikeu/pembayaran` | Riwayat transaksi pembayaran tagihan mahasiswa | ✅ Bearer |

---

## POST /api/v1/sikeu/tagihan/external

> Memproses pembuatan invoice/tagihan baru dari sistem eksternal (SPMB/SIAKAD/SIMPEG/SIPPM). Mendukung mahasiswa aktif (`mahasiswa_id`) maupun Calon Mahasiswa SPMB (`calon_mahasiswa_id`). Otomatis membuatkan Virtual Account jika tidak membutuhkan persetujuan pimpinan.

### Request Body

```json
{
    "mahasiswa_id": null,
    "calon_mahasiswa_id": 888,
    "tipe_referensi": "calon_mahasiswa",
    "tahun_akademik_id": 1,
    "source_system": "SPMB",
    "requires_approval": false,
    "jatuh_tempo": "2026-09-05",
    "keterangan": "Pendaftaran Jalur Reguler",
    "details": [
        {
            "jenis_biaya_kode": "SPMB_ADM",
            "nominal": 250000,
            "keterangan": "Biaya Formulir SPMB"
        }
    ],
    "potongan": [
        {
            "tipe": "diskon",
            "nominal_potongan": 50000,
            "keterangan": "Diskon Pendaftaran Early Bird"
        }
    ]
}
```

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Tagihan eksternal berhasil diterbitkan dan Virtual Account aktif.",
    "data": {
        "tagihan": {
            "id": 12,
            "mahasiswa_id": null,
            "calon_mahasiswa_id": 888,
            "tipe_referensi": "calon_mahasiswa",
            "nomor_tagihan": "INV-SPMB-20260805-ABC12",
            "total_tagihan": 250000.00,
            "total_potongan": 50000.00,
            "total_bayar": 200000.00,
            "status": "belum_bayar",
            "source_system": "SPMB"
        },
        "virtual_account": {
            "id": 8,
            "va_number": "88826080500012",
            "bank_kode": "BNI",
            "nominal": 200000.00,
            "status": "aktif"
        }
    }
}
```

# PeminjamanController

> **Modul**: SINAPRA (Sarana, Prasarana, & Aset)  
> **Base URL**: `/api/sinapra`  
> **Autentikasi**: Bearer Token (`auth:api` / Passport)  
> **Dibuat**: 2026-08-19  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth | Permission |
|---|---|---|---|---|
| GET | `/api/sinapra/peminjaman-ruangan` | Listing permohonan peminjaman ruangan | ✅ | `sinapra.peminjaman_ruangan.read` |
| POST | `/api/sinapra/peminjaman-ruangan` | Permohonan peminjaman ruangan baru | ✅ | `sinapra.peminjaman_ruangan.create` |
| GET | `/api/sinapra/peminjaman-ruangan/{id}` | Detail peminjaman ruangan | ✅ | `sinapra.peminjaman_ruangan.read` |
| POST | `/api/sinapra/peminjaman-ruangan/{id}/approve` | Persetujuan/Penolakan peminjaman ruangan | ✅ | `sinapra.peminjaman_ruangan.approve` |
| GET | `/api/sinapra/peminjaman-aset` | Listing permohonan peminjaman barang/aset | ✅ | `sinapra.peminjaman_aset.read` |
| POST | `/api/sinapra/peminjaman-aset` | Permohonan peminjaman barang/aset baru | ✅ | `sinapra.peminjaman_aset.create` |
| GET | `/api/sinapra/peminjaman-aset/{id}` | Detail peminjaman barang/aset | ✅ | `sinapra.peminjaman_aset.read` |
| POST | `/api/sinapra/peminjaman-aset/{id}/approve` | Persetujuan/Penolakan peminjaman barang | ✅ | `sinapra.peminjaman_aset.approve` |
| POST | `/api/sinapra/peminjaman-aset/{id}/kembalikan` | Pengembalian barang/aset | ✅ | `sinapra.peminjaman_aset.approve` |

---

## POST /api/sinapra/peminjaman-aset/{id}/kembalikan

Deskripsi: Memproses pengembalian barang/aset pinjaman serta memperbarui kondisi fisik dan status ketersediaan barang kembali ke `tersedia`.

### Request Body
```json
{
    "kondisi_kembali": "baik"
}
```

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Pengembalian aset berhasil diproses",
    "data": {
        "id": 1,
        "aset_id": 2,
        "user_id": 5,
        "tanggal_pinjam": "2026-08-20",
        "tanggal_kembali_realisasi": "2026-08-22",
        "kondisi_kembali": "baik",
        "status": "kembali"
    }
}
```

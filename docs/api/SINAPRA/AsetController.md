# AsetController

> **Modul**: SINAPRA (Sarana, Prasarana, & Aset)  
> **Base URL**: `/api/sinapra`  
> **Autentikasi**: Bearer Token (`auth:api` / Passport)  
> **Dibuat**: 2026-08-19  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth | Permission |
|---|---|---|---|---|
| GET | `/api/sinapra/kategori-aset` | Listing kategori aset (termasuk parent/child) | ✅ | `sinapra.kategori_aset.read` |
| POST | `/api/sinapra/kategori-aset` | Tambah kategori aset | ✅ | `sinapra.kategori_aset.create` |
| GET | `/api/sinapra/kategori-aset/{id}` | Detail kategori aset | ✅ | `sinapra.kategori_aset.read` |
| PUT | `/api/sinapra/kategori-aset/{id}` | Update kategori aset | ✅ | `sinapra.kategori_aset.update` |
| DELETE | `/api/sinapra/kategori-aset/{id}` | Hapus kategori aset | ✅ | `sinapra.kategori_aset.delete` |
| GET | `/api/sinapra/aset` | Listing inventaris barang/aset | ✅ | `sinapra.aset.read` |
| POST | `/api/sinapra/aset` | Tambah aset baru | ✅ | `sinapra.aset.create` |
| GET | `/api/sinapra/aset/{id}` | Detail aset & riwayat perbaikan/peminjaman | ✅ | `sinapra.aset.read` |
| GET | `/api/sinapra/aset/{id}/hitung-penyusutan` | Kalkulasi nilai buku & penyusutan aset | ✅ | `sinapra.aset.read` |
| PUT | `/api/sinapra/aset/{id}` | Update data aset | ✅ | `sinapra.aset.update` |
| DELETE | `/api/sinapra/aset/{id}` | Soft delete aset | ✅ | `sinapra.aset.delete` |

---

## GET /api/sinapra/aset/{id}/hitung-penyusutan

Deskripsi: Menghitung estimasi sisa nilai buku aset berdasarkan umur perolehan barang dan persentase penyusutan kategori.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Estimasi penyusutan nilai buku aset berhasil dihitung",
    "data": {
        "aset_id": 1,
        "kode_aset": "AST-IT-001",
        "nama": "PC Workstation Server",
        "harga_perolehan": 20000000.0,
        "nilai_buku_saat_ini": 10000000.0
    }
}
```

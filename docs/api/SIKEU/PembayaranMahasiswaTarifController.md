# PembayaranMahasiswaTarifController

> **Modul**: SIKEU — Pembayaran Mahasiswa  
> **Base URL**: `/api/v1/sikeu/pembayaran-mahasiswa`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-09-21  
> **Diperbarui**: 2026-09-21

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/pembayaran-mahasiswa/tarif` | Daftar tarif per angkatan & prodi | ✅ |
| POST | `/api/v1/sikeu/pembayaran-mahasiswa/tarif` | Buat tarif baru | ✅ |
| GET | `/api/v1/sikeu/pembayaran-mahasiswa/tarif/{id}` | Detail tarif | ✅ |
| PUT | `/api/v1/sikeu/pembayaran-mahasiswa/tarif/{id}` | Ubah tarif | ✅ |
| DELETE | `/api/v1/sikeu/pembayaran-mahasiswa/tarif/{id}` | Hapus tarif (diproteksi bila dipakai angkatan sama) | ✅ |
| GET | `/api/v1/sikeu/pembayaran-mahasiswa/tarif-mahasiswa` | Tarif berlaku untuk mahasiswa tertentu | ✅ |
| GET | `/api/v1/sikeu/pembayaran-mahasiswa/tagihan` | Daftar tagihan (termasuk `semester`) | ✅ |
| POST | `/api/v1/sikeu/pembayaran-mahasiswa/tagihan` | Terbitkan tagihan (tolak duplikat semester) | ✅ |
| DELETE | `/api/v1/sikeu/pembayaran-mahasiswa/tagihan/{id}` | Hapus tagihan belum bayar | ✅ |
| POST | `/api/v1/sikeu/pembayaran-mahasiswa/tagihan/batch-delete` | Hapus banyak tagihan | ✅ |
| GET | `/api/v1/sikeu/pembayaran-mahasiswa/mass-tagihan/preview` | Pratinjau tagihan massal | ✅ |
| POST | `/api/v1/sikeu/pembayaran-mahasiswa/mass-tagihan` | Terbitkan tagihan massal (lewati yang sudah ditagih) | ✅ |
| POST | `/api/v1/sikeu/pembayaran-mahasiswa/alihkan-pembayaran` | Alihkan pembayaran antar tagihan | ✅ |

---

## DELETE /api/v1/sikeu/pembayaran-mahasiswa/tarif/{id}

> Hapus tarif. Proteksi disesuaikan dengan cara penagihan bekerja: penagihan selalu mengambil tarif berdasarkan **tahun angkatan** mahasiswa (lihat `tarif-mahasiswa`), sehingga tarif angkatan 2023 **bisa** dihapus walaupun komponen biayanya pernah dipakai menagih mahasiswa angkatan lain.

### Aturan proteksi

Tarif ditolak dihapus (`422`) hanya bila ada tagihan yang memuat komponen biaya sama **dan**:
- milik mahasiswa SIAKAD dengan `angkatan` = `tahun_angkatan` tarif (plus `program_studi_id` sama bila tarif spesifik prodi), **atau**
- milik calon mahasiswa yang gelombangnya berada pada tahun akademik dengan `tahun_mulai` = `tahun_angkatan` tarif.

### Response Error

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Tarif tidak dapat dihapus karena komponen biaya ini sudah pernah diterbitkan pada tagihan mahasiswa. Anda dapat menonaktifkan status tarif tersebut sebagai alternatif."
}
```

---

## POST /api/v1/sikeu/pembayaran-mahasiswa/tagihan

> Terbitkan tagihan individu. Kolom `semester` kini disimpan di tabel (`sikeu_tagihan_mahasiswa.semester`) dan dipakai sebagai guard duplikasi.

### Request Body

```json
{
    "mahasiswa_id": "integer, required tanpa calon_mahasiswa_id",
    "calon_mahasiswa_id": "integer, required tanpa mahasiswa_id",
    "tipe_referensi": "string, nullable, max:30",
    "semester": "integer, nullable, min:1, max:14",
    "jatuh_tempo": "date, required",
    "catatan": "string, nullable, max:500",
    "items": "array, required, min:1",
    "items.*.master_biaya_id": "integer, required, exists:sikeu_master_biaya,id",
    "items.*.nominal": "numeric, required, min:0",
    "mode_pembayaran": "enum: terbitkan_tagihan|bayar_loket_tunai|bayar_loket_transfer"
}
```

### Guard duplikat semester

### Cakupan semester tarif

Field `semester` pada tarif (`null` = semua semester, angka = hanya semester itu) ikut dalam seluruh aturan: duplikasi kombinasi, hierarki global-vs-prodi (hanya yang cakupannya bertabrakan), filter list `?semester=`, dan `tarif-mahasiswa?semester=N` yang menyaring komponen khusus semester (misal biaya lab semester 3 tidak ikut saat menagih semester 1). Contoh: biaya magang semester 3 dan 5 diinput sebagai dua baris tarif.

Pratinjau massal (`mass-tagihan/preview?semester=N`) memakai penyaringan yang sama, menandai mahasiswa yang `sudah_ditagih` (tidak ditagih ulang), dan mengembalikan `komponen_terpakai` (komponen yang cocok tarif + rentang nominalnya) beserta daftar penuh `mahasiswa`.

Bila `semester` diisi, request ditolak (`422`) bila mahasiswa/calon tersebut sudah memiliki tagihan semester sama pada tahun akademik aktif dengan status selain `batal`.

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Tagihan semester 1 untuk Bintang Pratama Putra sudah pernah diterbitkan (INV-MHS-20260920-XXXX, status: sebagian). Hapus atau batalkan tagihan tersebut terlebih dahulu bila ingin menagih ulang.",
    "errors": {
        "semester": ["Mahasiswa ini sudah memiliki tagihan semester 1 pada tahun akademik berjalan."]
    }
}
```

---

## POST /api/v1/sikeu/pembayaran-mahasiswa/mass-tagihan

> Terbitkan tagihan massal per angkatan. Mahasiswa yang sudah memiliki tagihan semester tersebut (status selain `batal`) **dilewati** dan dilaporkan via `skipped_count`.

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Berhasil menerbitkan 12 tagihan massal untuk angkatan 2026. 3 mahasiswa dilewati karena sudah memiliki tagihan semester 1.",
    "data": {
        "created_count": 12,
        "skipped_count": 3,
        "total_nominal": 45000000,
        "tahun_angkatan": 2026,
        "program_studi_id": 1
    }
}
```

### Catatan Tambahan

> - `semester` hasil backfill migrasi `2026_09_21_000001` diambil dari pola "... Semester N" pada `catatan_approval` untuk tagihan lama.
> - Index `tagihan_mhs_semester_ta_index (mahasiswa_id, semester, tahun_akademik_id)` mempercepat guard duplikasi.
> - Jurnal akrual otomatis: terbit tagihan `Dr 103.01 Piutang / Cr 401.01 (UKT) atau 401.02 (SPMB)`; hapus tagihan membaliknya; potongan `Dr 504.01 Beban / Cr Piutang` (+ sebaliknya saat batal).

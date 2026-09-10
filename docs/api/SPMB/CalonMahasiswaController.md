# CalonMahasiswaController

> **Modul**: SPMB  
> **Base URL**: `/api/spmb/pendaftaran`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Diperbarui**: 2026-09-10  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/spmb/pendaftaran/my` | Mendapatkan data pendaftaran calon mahasiswa yang sedang login beserta status tagihan | ✅ Calon Mhs |
| POST | `/api/spmb/pendaftaran/biodata` | Menyimpan / auto-save draft biodata pendaftaran calon mahasiswa | ✅ Calon Mhs |
| POST | `/api/spmb/pendaftaran/finalize` | Finalisasi pendaftaran (kunci formulir dan ubah status draft menjadi submitted) | ✅ Calon Mhs |

---

## [GET] /api/spmb/pendaftaran/my

> Mengambil data pendaftaran aktif dan tagihan virtual account calon mahasiswa yang sedang login.

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
    "data": {
        "pendaftaran": {
            "id": 1,
            "user_id": 10,
            "gelombang_id": 1,
            "program_studi_id": 1,
            "master_tipe_jalur_id": 1,
            "no_pendaftaran": "REG-20260910-1234",
            "nama_lengkap": "Ahmad",
            "nik": "3201234567890001",
            "status": "draft",
            "status_pembayaran": "belum_bayar"
        },
        "tagihan": {
            "tagihan": {},
            "virtual_account": {}
        }
    }
}
```

---

## [POST] /api/spmb/pendaftaran/biodata

> Menyimpan draft pendaftaran calon mahasiswa per langkah (multi-step wizard).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Content-Type` | `application/json` | ✅ |
| `Accept` | `application/json` | ✅ |

### Body Parameters (Draft Mode - Bertahap)

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `gelombang_id` | integer | ❌ | ID Gelombang penerimaan |
| `program_studi_id` | integer | ❌ | ID Program studi pilihan 1 |
| `program_studi_pilihan2_id` | integer | ❌ | ID Program studi pilihan 2 |
| `master_tipe_jalur_id` | integer | ❌ | Referensi master tipe jalur |
| `nama_lengkap` | string | ❌ | Nama lengkap calon mahasiswa |
| `nik` | string | ❌ | NIK KTP / Kartu Identitas |
| `no_hp` | string | ❌ | Nomor HP / WhatsApp |
| `provinsi` | string | ❌ | Nama Provinsi |
| `kota_kabupaten` | string | ❌ | Nama Kota/Kabupaten |
| `kecamatan` | string | ❌ | Nama Kecamatan |
| `kode_pos` | string | ❌ | Kode Pos |
| `alamat` | string | ❌ | Alamat lengkap domisili |
| `asal_sekolah` | string | ❌ | Nama sekolah asal |
| `jurusan_sekolah` | string | ❌ | Jurusan saat sekolah |
| `tahun_lulus` | string | ❌ | Tahun kelulusan |
| `nilai_rata_rapor` | numeric | ❌ | Nilai rata-rata rapor |
| `nama_ayah` | string | ❌ | Nama lengkap Ayah |
| `pekerjaan_ayah` | string | ❌ | Pekerjaan Ayah |
| `nama_ibu` | string | ❌ | Nama lengkap Ibu |
| `pekerjaan_ibu` | string | ❌ | Pekerjaan Ibu |
| `penghasilan_ortu` | string | ❌ | Rentang penghasilan orang tua |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Biodata berhasil disimpan dan Tagihan diterbitkan.",
    "data": {
        "pendaftaran": {
            "id": 1,
            "no_pendaftaran": "REG-20260910-1234",
            "status": "draft"
        },
        "tagihan": {}
    }
}
```

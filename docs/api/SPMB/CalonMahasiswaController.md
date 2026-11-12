# CalonMahasiswaController

> **Modul**: SPMB / **Base URL**: `/api/spmb/pendaftaran` / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-11-06

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
            "kewarganegaraan_id": 1,
            "nomor_paspor": null,
            "negara_penerbit_paspor": null,
            "negara_domisili": "Indonesia",
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

> Menyimpan draft pendaftaran calon mahasiswa per langkah (multi-step wizard). Saat tagihan pertama diterbitkan, rincian diambil dari komponen Master Biaya (gelombang + prodi) yang ditandai `dibebankan_saat_pendaftaran` sebagai **beban awal**; bila belum dikonfigurasi, fallback ke tarif SIKEU / `biaya_pendaftaran` gelombang. Kolom `tahun_akademik_id` tidak lagi dikirim pada payload tagihan.

Jika `kode_diskon` diisi, backend memvalidasi ulang kode dan menerapkannya hanya ke rincian Master Biaya yang ditandai sebagai beban pendaftaran. Kode tidak dapat ditambahkan atau diganti setelah tagihan terbit. Bila tidak ada rincian Master Biaya yang memenuhi syarat, kode diskon ditolak meskipun tagihan tanpa kode masih dapat mengikuti fallback tarif SIKEU.

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
| `kode_diskon` | string | ❌ | Kode diskon opsional; harus dikirim sebelum tagihan pendaftaran pertama kali dibuat |
| `nama_lengkap` | string | ❌ | Nama lengkap calon mahasiswa |
| `nik` | string | ❌ | NIK KTP / Kartu Identitas |
| `kewarganegaraan_id` | integer | ❌ | ID item aktif `spmb_master_referensi` dengan tipe `kewarganegaraan` |
| `nomor_paspor` | string | ❌ | Nomor paspor; wajib saat finalisasi WNA dan harus unik |
| `negara_penerbit_paspor` | string | ❌ | Negara penerbit paspor; wajib saat finalisasi WNA |
| `negara_kewarganegaraan` | string | ❌ | Negara kewarganegaraan; wajib saat finalisasi WNA |
| `kode_negara_kewarganegaraan` | string | ❌ | Kode ISO 3166-1 alpha-2; wajib saat finalisasi WNA, dipakai untuk sinkronisasi PDDIKTI |
| `negara_domisili` | string | ❌ | Negara domisili; jika Indonesia, provinsi, kota/kabupaten, dan kecamatan wajib |
| `no_hp` | string | ❌ | Nomor HP / WhatsApp |
| `provinsi` | string | ❌ | Provinsi/state; wajib saat domisili Indonesia |
| `kota_kabupaten` | string | ❌ | Kota/kabupaten/city; wajib saat domisili Indonesia |
| `kecamatan` | string | ❌ | Kecamatan/distrik; wajib saat domisili Indonesia |
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

## Tagihan beberapa kanal (2026-11-11)

Penyimpanan biodata menerbitkan beban pendaftaran per kanal yang ditetapkan pada komponen biaya. Respons `data.tagihan` mempertahankan `tagihan/virtual_account` pertama dan menambahkan `tagihan_list[]` (masing-masing berisi `tagihan` dan `virtual_account`). Penyimpanan berulang menggunakan seluruh tagihan tahap yang sudah ada. `GET /api/spmb/pendaftaran/me` menambahkan `data.tagihan_list[]` berisi seluruh tagihan SPMB calon. Nominal total tahap harus dihitung dari semua tagihan, bukan hanya item pertama.

## Identitas WNI dan WNA

- Referensi `kewarganegaraan_id` harus mengarah ke item aktif dengan tipe `kewarganegaraan`. Kode WNI mewajibkan NIK 16 digit saat finalisasi. Kode WNA melarang NIK dan mewajibkan `nomor_paspor`, `negara_penerbit_paspor`, `negara_kewarganegaraan`, serta `kode_negara_kewarganegaraan` ISO 3166-1 alpha-2.
- Draft tetap dapat disimpan bertahap sebelum identitas lengkap. `POST /api/spmb/pendaftaran/finalize` menjalankan ulang aturan identitas dan alamat.
- `alamat` wajib untuk semua pendaftar. Jika `negara_domisili` adalah Indonesia (atau kosong pada data lama), `provinsi`, `kota_kabupaten`, dan `kecamatan` harus terisi. Untuk negara lain ketiga field administratif tersebut opsional.
- WNA perlu memilih dokumen paspor melalui Master Syarat Berkas untuk jalur masuk yang sesuai. Dokumen tetap divalidasi menggunakan `jenis_dokumen` dan requirement aktif jalur tersebut.
- Referensi `WNI` dan `WNA` dibuat/diaktifkan oleh migrasi identitas WNA. Dropdown mengambil label/kode master; server menggunakan kode master sebagai sumber aturan.

Tagihan menyimpan `payment_gateway_config_id/payment_gateway_name/payment_gateway_environment`. VA dapat null sebelum checkout; jangan membuat VA lokal pengganti pada browser. Registrasi membuka `/spmb/pembayaran?tahap=pendaftaran`. Reissue VA tidak mengubah tagihan dengan kanal eksplisit atau pembayaran tercatat (422). Reset pendaftaran dengan tagihan kanal eksplisit atau pembayaran juga ditolak (422) agar riwayat/order provider tidak terhapus. Kesalahan mapping komponen diteruskan sebagai validasi 422.

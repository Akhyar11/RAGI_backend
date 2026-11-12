# PendaftaranManualController

> **Modul**: SPMB / **Base URL**: `/api/spmb` / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-11-06

Admin SPMB mencatat pendaftaran dari formulir kertas. Akun calon dibuat otomatis, referral dipilih dari pengguna aktif yang memiliki kode referral, dan bukti transfer disimpan privat untuk verifikasi tanpa mengubah status pembayaran menjadi lunas. Role pihak ketiga diatur melalui `third_party_registration_role` pada Pengaturan Sistem. Akun yang dibuat lewat endpoint pihak ketiga otomatis mendapat role tersebut; pencarian dan validasi pilihan pendaftaran hanya menerima akun aktif dengan role itu.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/spmb/pendaftaran/manual/referrers` | Mencari pengguna yang dapat dipilih sebagai referrer | Bearer Token (`spmb.manage`) |
| GET | `/api/spmb/pendaftaran/manual/third-parties` | Mencari akun dengan role pihak ketiga yang dikonfigurasi | Bearer Token (`spmb.manage`) |
| POST | `/api/spmb/pendaftaran/manual/third-parties` | Membuat akun dengan role pihak ketiga yang dikonfigurasi | Bearer Token (`spmb.manage`) |
| POST | `/api/spmb/pendaftaran/manual` | Membuat akun calon dan pendaftaran manual beserta lampiran opsional | Bearer Token (`spmb.manage`) |
| GET | `/api/spmb/pendaftaran/{id}/bukti-transfer/{proofId}` | Mengunduh bukti transfer privat | Bearer Token (`spmb.manage`) |

## Headers

Semua endpoint memerlukan `Authorization: Bearer {token}` dan `Accept: application/json`. Endpoint pembuatan memakai `Content-Type: multipart/form-data`.

## [GET] `/api/spmb/pendaftaran/manual/referrers`

Mencari pengguna aktif yang sudah memiliki kode referral. Mendukung pagination dengan `per_page` (default 15, maksimum 100) dan mengembalikan metadata pagination.

### Query Parameters

| Parameter | Tipe | Default | Keterangan |
|---|---|---:|---|
| `search` | string | - | Cari nama, username, email, atau kode referral |
| `per_page` | integer | 15 | Jumlah item per halaman, maksimum 100 |
| `page` | integer | 1 | Halaman yang diminta |

### Response Sukses

**200 OK**

~~~json
{
  "status": "success",
  "message": "Daftar referrer berhasil diambil.",
  "data": [
    {
      "id": 25,
      "name": "Budi Santoso",
      "username": "budi.santoso",
      "email": "budi@example.test",
      "referral_code": "REF-BUDI25"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 1 }
}
~~~

## [GET] `/api/spmb/pendaftaran/manual/third-parties`

Mencari akun aktif dengan role yang diatur pada `third_party_registration_role`. Jika role belum diatur atau tidak aktif, endpoint mengembalikan data kosong.

### Query Parameters

| Parameter | Tipe | Default | Keterangan |
|---|---|---:|---|
| `search` | string | - | Cari nama, username, atau email |
| `per_page` | integer | 15 | Jumlah item per halaman, maksimum 100 |
| `page` | integer | 1 | Halaman yang diminta |

### Response Sukses

**200 OK**

~~~json
{
  "status": "success",
  "message": "Daftar akun pihak ketiga berhasil diambil.",
  "data": [{ "id": 32, "name": "Mitra Sekolah", "username": "pt_mitra_abc123", "email": "mitra@example.test" }],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 1 }
}
~~~

## [POST] `/api/spmb/pendaftaran/manual/third-parties`

Membuat akun pihak ketiga aktif dan terverifikasi menggunakan role yang ditentukan dalam pengaturan sistem. Password sementara dibuat acak dan hanya dikembalikan pada respons ini.

### Request Body

| Field | Tipe | Wajib | Aturan |
|---|---|---:|---|
| `name` | string | Ya | Maksimal 255 karakter |
| `email` | string | Ya | Email valid dan unik |
| `phone` | string | Tidak | Maksimal 30 karakter |

~~~json
{
  "name": "Mitra Sekolah",
  "email": "mitra@example.test",
  "phone": "081234567890"
}
~~~

### Response Sukses

**201 Created**

~~~json
{
  "status": "success",
  "message": "Akun pihak ketiga berhasil dibuat.",
  "data": {
    "id": 32,
    "name": "Mitra Sekolah",
    "username": "pt_mitra_abc123",
    "email": "mitra@example.test",
    "password_sementara": "nilai-acak-sekali",
    "role": "Mitra"
  }
}
~~~

### Response Error

**422 Unprocessable Entity**

~~~json
{
  "status": "error",
  "message": "The email has already been taken.",
  "errors": { "email": ["The email has already been taken."] }
}
~~~

## [POST] `/api/spmb/pendaftaran/manual`

Membuat akun role `calon_mhs` serta pendaftaran dengan status `submitted`, sumber `manual`, dan pembayaran `belum_bayar`. Admin boleh memasukkan pendaftaran pada gelombang berstatus aktif walaupun periode form online sudah berakhir.

### Request Body (`multipart/form-data`)

| Field | Tipe | Wajib | Aturan |
|---|---|---:|---|
| `email` | string | Ya | Email valid dan unik di `core_users` |
| `gelombang_id` | integer | Ya | Harus menunjuk gelombang berstatus aktif |
| `master_tipe_jalur_id` | integer | Ya | ID tipe jalur yang tersedia |
| `program_studi_id` | integer | Ya | ID program studi aktif |
| `program_studi_pilihan2_id` | integer/null | Tidak | Program studi kedua; harus berbeda dari pilihan pertama |
| `nama_lengkap` | string | Ya | Maksimal 255 karakter |
| `nik` | string | WNI: ya, WNA: tidak | Maksimal 20 karakter, unik; wajib 16 digit untuk WNI |
| `kewarganegaraan_id` | integer | Ya | ID item aktif `spmb_master_referensi` bertipe `kewarganegaraan` |
| `nomor_paspor` | string | WNA: ya | Maksimal 50 karakter, unik |
| `negara_penerbit_paspor` | string | WNA: ya | Negara penerbit paspor, maksimal 100 karakter |
| `negara_kewarganegaraan` | string | WNA: ya | Negara kewarganegaraan |
| `kode_negara_kewarganegaraan` | string | WNA: ya | Kode ISO 3166-1 alpha-2, dua huruf |
| `negara_domisili` | string | Ya | Jika Indonesia, provinsi/kota/kecamatan wajib; negara lain memakai alamat bebas |
| `no_hp` | string | Ya | Maksimal 30 karakter |
| `alamat` | string | Ya | Maksimal 2.000 karakter |
| `tanggal_lahir` | date | Tidak | Harus sebelum hari ini |
| `tempat_lahir`, `jenis_kelamin`, `agama`, `status_sipil` | string | Tidak | Biodata calon |
| `provinsi`, `kota_kabupaten`, `kecamatan`, `kode_pos` | string | Tidak | Alamat calon |
| `asal_sekolah`, `alamat_sekolah`, `jurusan_sekolah`, `tahun_lulus`, `npsn_sekolah` | string | Tidak | Data pendidikan |
| `nilai_rata_rapor` | numeric | Tidak | Nilai rata-rata rapor antara 0 dan 100 |
| `nama_ayah`, `pekerjaan_ayah`, `nama_ibu`, `pekerjaan_ibu`, `penghasilan_ortu`, `nama_wali`, `telepon_wali` | string | Tidak | Data orang tua/wali |
| `nama_ortu`, `alamat_ortu`, `telp_ortu` | string | Tidak | Kontak orang tua/wali utama |
| `asal_lulusan` | `sekolah` / `pt` | Tidak | Jenis pendidikan asal |
| `asal_pt`, `jenis_pt`, `alamat_pt`, `jenjang_pt`, `progdi_pt`, `ipk_pt`, `nim_pt`, `tahun_lulus_pt` | string/numeric | Tidak | Riwayat pendidikan tinggi untuk pendaftar transfer |
| `info_daftar`, `ket_info_daftar` | string | Tidak | Sumber informasi calon mengetahui pendaftaran |
| `referrer_user_id` | integer/null | Tidak | Pengguna aktif dengan kode referral |
| `pihak_ketiga_user_ids[]` | integer[] | Tidak | Maksimal 20 ID akun aktif dengan role pihak ketiga terkonfigurasi |
| `bukti_transfer` | file | Tidak | PDF/JPG/PNG maksimal 10 MB; disimpan privat |

Password akun sementara dibuat acak. Nilai plaintext hanya dikembalikan pada respons pembuatan ini; database hanya menyimpan hash dan password tidak dicatat pada audit log. Bukti transfer berstatus `menunggu_verifikasi`; unggahan tidak membuat tagihan ataupun menandai pembayaran lunas.

~~~json
{
  "email": "calon@example.test",
  "gelombang_id": 4,
  "master_tipe_jalur_id": 1,
  "program_studi_id": 12,
  "nama_lengkap": "Calon Mahasiswa",
  "nik": "1234567890123456",
  "no_hp": "081234567890",
  "alamat": "Jl. Pendidikan No. 1",
  "referrer_user_id": 25,
  "pihak_ketiga_user_ids[0]": 32,
  "pihak_ketiga_user_ids[1]": 38
}
~~~

### Response Sukses

**201 Created**

~~~json
{
  "status": "success",
  "message": "Pendaftaran manual berhasil dibuat. Simpan password sementara sebelum menutup halaman ini.",
  "data": {
    "pendaftaran": {
      "id": 301,
      "no_pendaftaran": "REG-20261106-A7B8C9",
      "nama_lengkap": "Calon Mahasiswa",
      "status": "submitted",
      "status_pembayaran": "belum_bayar",
      "sumber_pendaftaran": "manual",
      "used_referral_code": "REF-BUDI25",
      "referrer_user_id": 25,
      "pihak_ketiga": [
        { "id": 12, "user_id": 32, "nama": "Mitra Sekolah A", "urutan": 0 },
        { "id": 13, "user_id": 38, "nama": "Komunitas B", "urutan": 1 }
      ],
      "bukti_pembayaran_manual": [
        { "id": 9, "original_name": "bukti-transfer.pdf", "status": "menunggu_verifikasi" }
      ]
    },
    "akun": {
      "username": "cm_calon_a7b8c9",
      "email": "calon@example.test",
      "password_sementara": "nilai-acak-sekali"
    }
  }
}
~~~

## [GET] `/api/spmb/pendaftaran/{id}/bukti-transfer/{proofId}`

Mengunduh file bukti transfer dari storage privat. `proofId` harus terkait dengan pendaftaran `{id}`.

**200 OK** mengembalikan file asli. **404 Not Found** dikembalikan bila pendaftaran, bukti, atau file tidak ditemukan.

## Error Umum

| Status | Kondisi |
|---:|---|
| `400` | Format request tidak dapat dibaca |
| `401` | Token tidak ada atau tidak valid |
| `403` | Pengguna tidak memiliki permission `spmb.manage` |
| `404` | Gelombang, bukti transfer, atau pendaftaran tidak ditemukan |
| `422` | Validasi email/NIK, pilihan master, referrer, pihak ketiga, atau file gagal |

Contoh envelope error API:

~~~json
{ "status": "error", "message": "Unauthenticated." }
~~~

~~~json
{ "status": "error", "message": "Akses ditolak." }
~~~

~~~json
{ "status": "error", "message": "No query results for model [App\\Models\\Spmb\\PendaftaranCalonMhs] 999." }
~~~

~~~json
{
  "status": "error",
  "message": "Data yang diberikan tidak valid.",
  "errors": { "pihak_ketiga_user_ids.0": ["Akun pihak ketiga tidak sesuai dengan role yang dikonfigurasi."] }
}
~~~

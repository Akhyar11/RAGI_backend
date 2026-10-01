# TemplateSuratSpmbController

> **Modul**: SPMB / **Base URL**: `/api/spmb/template-surat` / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-10-01

Mengelola **Template Surat Keputusan (SK) dan Surat Resmi SPMB** secara dinamis. Mendukung kustomisasi kop institusi, nomor surat otomatis, konsideran pembuka, diktum keputusan, petunjuk daftar ulang, informasi pejabat penandatangan, catatan kaki, serta penggantian placeholder token (`{nama}`, `{no_pendaftaran}`, `{nik}`, `{tempat_lahir}`, `{tanggal_lahir}`, `{asal_sekolah}`, `{prodi_diterima}`, `{jenjang}`, `{jalur}`, `{gelombang}`, `{tahun_akademik}`, `{tanggal_penetapan}`, `{tahun}`, `{romawi_bulan}`, `{kota}`).

## Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ untuk POST/PUT |

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/spmb/template-surat` | Daftar template surat berpaginasi | ✅ |
| POST | `/api/spmb/template-surat` | Buat template surat baru | ✅ |
| GET | `/api/spmb/template-surat/{id}` | Detail template surat | ✅ |
| PUT | `/api/spmb/template-surat/{id}` | Perbarui template surat | ✅ |
| DELETE | `/api/spmb/template-surat/{id}` | Hapus template surat (soft delete) | ✅ |
| GET | `/api/spmb/template-surat/{id}/preview` | Pratinjau cetak PDF template surat (dummy data) | ✅ |

> Semua endpoint dilindungi middleware `auth:sanctum` dan izin `can:spmb.manage`. Pengguna tanpa otorisasi akan menerima response `401 Unauthorized` atau `403 Forbidden`.

## Response Error Umum

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "No query results for model [App\\Models\\Spmb\\TemplateSuratSpmb] 99."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "kode": ["Kode template surat sudah digunakan."],
        "nama": ["Nama template surat wajib diisi."]
    }
}
```

---

## [GET] /api/spmb/template-surat

Mengambil daftar template surat SPMB yang tersedia dengan filter dan paginasi server-side.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Pencarian teks pada kolom `kode`, `nama`, atau `judul_surat` |
| `jenis_surat` | string | ❌ | — | Filter jenis surat (`sk_lulus`, dll.) |
| `jalur_masuk_id` | integer | ❌ | — | Filter spesifik ID Jalur Masuk (`spmb_jalur_masuk`) |
| `gelombang_id` | integer | ❌ | — | Filter spesifik ID Gelombang (`spmb_gelombang_penerimaan`) |
| `is_active` | boolean | ❌ | — | Filter status aktif template (`true` / `false`) |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan: `kode`, `nama`, `jenis_surat`, `jalur_masuk_id`, `gelombang_id`, `is_active`, `created_at`, `updated_at` |
| `sort_order` | string | ❌ | `desc` | Arah pengurutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maksimum 100) |
| `page` | integer | ❌ | `1` | Nomor halaman data |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data template surat berhasil diambil.",
    "data": [
        {
            "id": 1,
            "kode": "SK_LULUS_DEFAULT",
            "nama": "Template Standar SK Kelulusan SPMB",
            "jenis_surat": "sk_lulus",
            "jalur_masuk_id": null,
            "gelombang_id": null,
            "is_active": true,
            "kop_nama_institusi": "UNIVERSITAS INDONUSA",
            "kop_nama_sub": "PANITIA PENERIMAAN MAHASISWA BARU (SPMB)",
            "kop_alamat_kontak": "Sekretariat SPMB Kampus Terpadu • Email: spmb@kampus.ac.id • Website: spmb.kampus.ac.id\nTahun Akademik {tahun_akademik}",
            "format_nomor_surat": "SKL/SPMB/{tahun}/{romawi_bulan}/{no_pendaftaran}",
            "judul_surat": "SURAT KETERANGAN TANDA LULUS SELEKSI",
            "teks_pembuka": "Berdasarkan hasil evaluasi verifikasi kelengkapan berkas administrasi dan pemenuhan syarat seleksi penerimaan mahasiswa baru Tahun Akademik {tahun_akademik}, Panitia Penerimaan Mahasiswa Baru menyatakan bahwa:",
            "teks_keputusan": "DINYATAKAN LULUS / DITERIMA",
            "petunjuk_daftar_ulang": "1. Calon mahasiswa yang dinyatakan lulus wajib melakukan Daftar Ulang melalui portal resmi SPMB pada menu Daftar Ulang.\n2. Selesaikan pembayaran biaya registrasi/UKT menggunakan nomor Virtual Account resmi yang tertera pada invoice tagihan Anda sebelum batas waktu yang ditentukan.\n3. Setelah pembayaran daftar ulang terkonfirmasi lunas, sistem akan menerbitkan Nomor Induk Mahasiswa (NIM) resmi dan akun akademik mahasiswa baru.\n4. Surat keterangan ini sah dan dihasilkan secara otomatis oleh Sistem Informasi Penerimaan Mahasiswa Baru terintegrasi.",
            "kota_penetapan": "Surakarta",
            "nama_penandatangan": "Panitia Seleksi SPMB",
            "jabatan_penandatangan": "Ketua Panitia SPMB / Direktur Admisi",
            "nip_penandatangan": null,
            "catatan_kaki": "Dokumen ini merupakan bukti kelulusan seleksi SPMB yang sah. Keabsahan dokumen dapat diverifikasi langsung melalui database induk kampus terintegrasi.",
            "created_at": "2026-10-01T08:00:00.000000Z",
            "updated_at": "2026-10-01T08:00:00.000000Z",
            "deleted_at": null,
            "jalur_masuk": null,
            "gelombang": null
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1,
        "from": 1,
        "to": 1,
        "filters": {
            "search": null,
            "sort_by": "created_at",
            "sort_order": "desc"
        }
    }
}
```

---

## [POST] /api/spmb/template-surat

Membuat template surat baru di database.

### Request Body

```json
{
    "kode": "SK_LULUS_PRESTASI_2026",
    "nama": "Template SK Kelulusan Jalur Prestasi",
    "jenis_surat": "sk_lulus",
    "jalur_masuk_id": 2,
    "gelombang_id": null,
    "is_active": true,
    "kop_nama_institusi": "UNIVERSITAS INDONUSA",
    "kop_nama_sub": "PANITIA PENERIMAAN MAHASISWA BARU (SPMB)",
    "kop_alamat_kontak": "Sekretariat SPMB Kampus Terpadu • Email: spmb@kampus.ac.id",
    "format_nomor_surat": "SKL-PRS/{tahun}/{romawi_bulan}/{no_pendaftaran}",
    "judul_surat": "SURAT KEPUTUSAN KELULUSAN JALUR PRESTASI",
    "teks_pembuka": "Berdasarkan verifikasi portofolio prestasi dan seleksi administrasi penerimaan mahasiswa baru Tahun Akademik {tahun_akademik}, Panitia menetapkan bahwa:",
    "teks_keputusan": "DINYATAKAN LULUS / DITERIMA (BEASISWA PRESTASI)",
    "petunjuk_daftar_ulang": "1. Lengkapi berkas konfirmasi penerimaan beasiswa prestasi di portal SPMB.\n2. Ikuti pembekalan mahasiswa jalur prestasi sesuai jadwal.",
    "kota_penetapan": "Surakarta",
    "nama_penandatangan": "Prof. Dr. Ir. Budi Santoso, M.Kom.",
    "jabatan_penandatangan": "Wakil Rektor I / Ketua Panitia SPMB",
    "nip_penandatangan": "197508152000031002",
    "catatan_kaki": "SK Kelulusan ini sah dan diterbitkan secara elektronik oleh sistem SPMB kampus terintegrasi."
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Template surat berhasil dibuat.",
    "data": {
        "id": 2,
        "kode": "SK_LULUS_PRESTASI_2026",
        "nama": "Template SK Kelulusan Jalur Prestasi",
        "jenis_surat": "sk_lulus",
        "jalur_masuk_id": 2,
        "gelombang_id": null,
        "is_active": true,
        "kop_nama_institusi": "UNIVERSITAS INDONUSA",
        "kop_nama_sub": "PANITIA PENERIMAAN MAHASISWA BARU (SPMB)",
        "kop_alamat_kontak": "Sekretariat SPMB Kampus Terpadu • Email: spmb@kampus.ac.id",
        "format_nomor_surat": "SKL-PRS/{tahun}/{romawi_bulan}/{no_pendaftaran}",
        "judul_surat": "SURAT KEPUTUSAN KELULUSAN JALUR PRESTASI",
        "teks_pembuka": "Berdasarkan verifikasi portofolio prestasi dan seleksi administrasi penerimaan mahasiswa baru Tahun Akademik {tahun_akademik}, Panitia menetapkan bahwa:",
        "teks_keputusan": "DINYATAKAN LULUS / DITERIMA (BEASISWA PRESTASI)",
        "petunjuk_daftar_ulang": "1. Lengkapi berkas konfirmasi penerimaan beasiswa prestasi di portal SPMB.\n2. Ikuti pembekalan mahasiswa jalur prestasi sesuai jadwal.",
        "kota_penetapan": "Surakarta",
        "nama_penandatangan": "Prof. Dr. Ir. Budi Santoso, M.Kom.",
        "jabatan_penandatangan": "Wakil Rektor I / Ketua Panitia SPMB",
        "nip_penandatangan": "197508152000031002",
        "catatan_kaki": "SK Kelulusan ini sah dan diterbitkan secara elektronik oleh sistem SPMB kampus terintegrasi.",
        "created_at": "2026-10-01T09:00:00.000000Z",
        "updated_at": "2026-10-01T09:00:00.000000Z"
    }
}
```

---

## [GET] /api/spmb/template-surat/{id}

Mendapatkan rincian data template surat beserta relasi jalur masuk dan gelombang penerimaan.

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Detail template surat berhasil diambil.",
    "data": {
        "id": 1,
        "kode": "SK_LULUS_DEFAULT",
        "nama": "Template Standar SK Kelulusan SPMB",
        "jenis_surat": "sk_lulus",
        "jalur_masuk_id": null,
        "gelombang_id": null,
        "is_active": true,
        "kop_nama_institusi": "UNIVERSITAS INDONUSA",
        "kop_nama_sub": "PANITIA PENERIMAAN MAHASISWA BARU (SPMB)",
        "kop_alamat_kontak": "Sekretariat SPMB Kampus Terpadu • Email: spmb@kampus.ac.id • Website: spmb.kampus.ac.id\nTahun Akademik {tahun_akademik}",
        "format_nomor_surat": "SKL/SPMB/{tahun}/{romawi_bulan}/{no_pendaftaran}",
        "judul_surat": "SURAT KETERANGAN TANDA LULUS SELEKSI",
        "teks_pembuka": "Berdasarkan hasil evaluasi verifikasi kelengkapan berkas administrasi dan pemenuhan syarat seleksi penerimaan mahasiswa baru Tahun Akademik {tahun_akademik}, Panitia Penerimaan Mahasiswa Baru menyatakan bahwa:",
        "teks_keputusan": "DINYATAKAN LULUS / DITERIMA",
        "petunjuk_daftar_ulang": "1. Calon mahasiswa yang dinyatakan lulus wajib melakukan Daftar Ulang melalui portal resmi SPMB pada menu Daftar Ulang.\n2. Selesaikan pembayaran biaya registrasi/UKT menggunakan nomor Virtual Account resmi yang tertera pada invoice tagihan Anda sebelum batas waktu yang ditentukan.\n3. Setelah pembayaran daftar ulang terkonfirmasi lunas, sistem akan menerbitkan Nomor Induk Mahasiswa (NIM) resmi dan akun akademik mahasiswa baru.\n4. Surat keterangan ini sah dan dihasilkan secara otomatis oleh Sistem Informasi Penerimaan Mahasiswa Baru terintegrasi.",
        "kota_penetapan": "Surakarta",
        "nama_penandatangan": "Panitia Seleksi SPMB",
        "jabatan_penandatangan": "Ketua Panitia SPMB / Direktur Admisi",
        "nip_penandatangan": null,
        "catatan_kaki": "Dokumen ini merupakan bukti kelulusan seleksi SPMB yang sah. Keabsahan dokumen dapat diverifikasi langsung melalui database induk kampus terintegrasi.",
        "created_at": "2026-10-01T08:00:00.000000Z",
        "updated_at": "2026-10-01T08:00:00.000000Z",
        "deleted_at": null,
        "jalur_masuk": null,
        "gelombang": null
    }
}
```

---

## [PUT] /api/spmb/template-surat/{id}

Memperbarui data konfigurasi template surat yang sudah ada.

### Request Body

```json
{
    "kode": "SK_LULUS_DEFAULT",
    "nama": "Template Standar SK Kelulusan SPMB (Revisi 2026)",
    "jenis_surat": "sk_lulus",
    "jalur_masuk_id": null,
    "gelombang_id": null,
    "is_active": true,
    "kop_nama_institusi": "UNIVERSITAS INDONUSA SURAKARTA",
    "kop_nama_sub": "PANITIA PENERIMAAN MAHASISWA BARU (SPMB)",
    "kop_alamat_kontak": "Sekretariat SPMB • Gedung Rektorat Lt. 1 • Email: admisi@indonusa.ac.id",
    "format_nomor_surat": "SKL/SPMB/{tahun}/{romawi_bulan}/{no_pendaftaran}",
    "judul_surat": "SURAT KETERANGAN TANDA LULUS SELEKSI MAHASISWA BARU",
    "teks_pembuka": "Berdasarkan hasil evaluasi verifikasi kelengkapan berkas administrasi dan pemenuhan syarat seleksi penerimaan mahasiswa baru Tahun Akademik {tahun_akademik}, Panitia SPMB menyatakan bahwa:",
    "teks_keputusan": "DINYATAKAN LULUS / DITERIMA",
    "petunjuk_daftar_ulang": "1. Akses menu Daftar Ulang pada portal SPMB.\n2. Bayar UKT sebelum batas tanggal yang ditentukan.",
    "kota_penetapan": "Surakarta",
    "nama_penandatangan": "Dr. Ir. H. Ahmad Fauzi, M.T.",
    "jabatan_penandatangan": "Ketua Panitia SPMB",
    "nip_penandatangan": "198001012005011003",
    "catatan_kaki": "Dokumen ini sah dan diterbitkan secara digital oleh Sistem Informasi SPMB."
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Template surat berhasil diperbarui.",
    "data": {
        "id": 1,
        "kode": "SK_LULUS_DEFAULT",
        "nama": "Template Standar SK Kelulusan SPMB (Revisi 2026)",
        "jenis_surat": "sk_lulus",
        "jalur_masuk_id": null,
        "gelombang_id": null,
        "is_active": true,
        "kop_nama_institusi": "UNIVERSITAS INDONUSA SURAKARTA",
        "kop_nama_sub": "PANITIA PENERIMAAN MAHASISWA BARU (SPMB)",
        "kop_alamat_kontak": "Sekretariat SPMB • Gedung Rektorat Lt. 1 • Email: admisi@indonusa.ac.id",
        "format_nomor_surat": "SKL/SPMB/{tahun}/{romawi_bulan}/{no_pendaftaran}",
        "judul_surat": "SURAT KETERANGAN TANDA LULUS SELEKSI MAHASISWA BARU",
        "teks_pembuka": "Berdasarkan hasil evaluasi verifikasi kelengkapan berkas administrasi dan pemenuhan syarat seleksi penerimaan mahasiswa baru Tahun Akademik {tahun_akademik}, Panitia SPMB menyatakan bahwa:",
        "teks_keputusan": "DINYATAKAN LULUS / DITERIMA",
        "petunjuk_daftar_ulang": "1. Akses menu Daftar Ulang pada portal SPMB.\n2. Bayar UKT sebelum batas tanggal yang ditentukan.",
        "kota_penetapan": "Surakarta",
        "nama_penandatangan": "Dr. Ir. H. Ahmad Fauzi, M.T.",
        "jabatan_penandatangan": "Ketua Panitia SPMB",
        "nip_penandatangan": "198001012005011003",
        "catatan_kaki": "Dokumen ini sah dan diterbitkan secara digital oleh Sistem Informasi SPMB.",
        "created_at": "2026-10-01T08:00:00.000000Z",
        "updated_at": "2026-10-01T09:15:00.000000Z"
    }
}
```

---

## [DELETE] /api/spmb/template-surat/{id}

Menghapus template surat secara soft delete.

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Template surat berhasil dihapus.",
    "data": null
}
```

---

## [GET] /api/spmb/template-surat/{id}/preview

Melakukan rendering dan pratinjau langsung template surat ke dalam stream dokumen PDF utuh (A4 portrait) menggunakan data simulasi (mock candidate).

### Response Sukses

**200 OK**
- **Content-Type**: `application/pdf`
- **Content-Disposition**: `inline; filename="Preview-Template-SK_LULUS_DEFAULT.pdf"`
- **Body**: Binary PDF stream dokumen SK Tanda Lulus.

---

## Catatan Soft Delete
Model `TemplateSuratSpmb` menggunakan trait `SoftDeletes`. Menghapus template surat tidak akan menghapus baris dari database secara fisik melainkan mengisi kolom `deleted_at`. Template yang di-soft-delete tidak akan digunakan lagi oleh generator SK pendaftaran.

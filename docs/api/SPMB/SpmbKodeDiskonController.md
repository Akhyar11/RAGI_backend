# SpmbKodeDiskonController

> **Modul**: SPMB / **Base URL**: /api/spmb / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-11-06

Admin mengelola kode melalui /master/kode-diskon dengan permission spmb.manage. Pendaftar mengakses pratinjau melalui /kode-diskon/validate dengan permission spmb.pendaftaran.create.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/spmb/master/kode-diskon` | Mengambil daftar kode diskon (paginasi) | Bearer Token (`spmb.manage`) |
| POST | `/api/spmb/master/kode-diskon` | Membuat kode diskon baru | Bearer Token (`spmb.manage`) |
| GET | `/api/spmb/master/kode-diskon/{id}` | Detail informasi kode diskon | Bearer Token (`spmb.manage`) |
| PUT | `/api/spmb/master/kode-diskon/{id}` | Memperbarui data kode diskon | Bearer Token (`spmb.manage`) |
| DELETE | `/api/spmb/master/kode-diskon/{id}` | Menghapus (soft delete) kode diskon | Bearer Token (`spmb.manage`) |
| POST | `/api/spmb/kode-diskon/validate` | Validasi dan pratinjau kode diskon calon mahasiswa | Bearer Token (`spmb.pendaftaran.create`) |

## Headers

Semua endpoint memerlukan header berikut:

| Header | Nilai | Wajib |
|---|---|---|
| Authorization | Bearer {token} | Ya |
| Accept | application/json | Ya |
| Content-Type | application/json | Untuk POST dan PUT |

## [GET] /api/spmb/master/kode-diskon

Mengambil daftar kode diskon terpaginasikan. Permission: spmb.manage.

### Query Parameters

| Parameter | Tipe | Default | Keterangan |
|---|---|---:|---|
| search | string | - | Cari pada kode atau nama |
| kode | string | - | Filter sebagian kode |
| nama | string | - | Filter sebagian nama |
| is_active | boolean | - | Filter status aktif |
| tipe_diskon | string | - | persen atau nominal |
| nilai_min | numeric | - | Nilai diskon minimum |
| nilai_max | numeric | - | Nilai diskon maksimum |
| berlaku_mulai | date | - | Filter tanggal mulai yang sama persis (YYYY-MM-DD) |
| berlaku_sampai | date | - | Filter tanggal akhir yang sama persis (YYYY-MM-DD) |
| sort_by | string | created_at | kode, nama, tipe_diskon, nilai, batas_penggunaan, berlaku_mulai, berlaku_sampai, is_active, created_at, atau updated_at |
| sort_order | string | desc | asc atau desc |
| per_page | integer | 15 | Jumlah data per halaman, antara 1–100 |
| page | integer | 1 | Nomor halaman |

### Response Sukses

**200 OK**

~~~json
{
  "status": "success",
  "message": "Daftar kode diskon berhasil dimuat.",
  "data": [
    {
      "id": 12,
      "kode": "SPMBHEMAT",
      "nama": "Diskon Pendaftaran",
      "tipe_diskon": "persen",
      "nilai": "10.00",
      "maksimum_potongan": "500000.00",
      "batas_penggunaan": 10,
      "berlaku_mulai": "2026-11-01",
      "berlaku_sampai": "2026-12-31",
      "is_active": true,
      "created_at": "2026-11-06T10:00:00.000000Z",
      "updated_at": "2026-11-06T10:00:00.000000Z",
      "deleted_at": null,
      "penggunaan_count": 3
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
      "is_active": null,
      "tipe_diskon": null,
      "sort_by": "created_at",
      "sort_order": "desc"
    }
  }
}
~~~

## [POST] /api/spmb/master/kode-diskon

Membuat kode diskon. Permission: spmb.manage.

### Request Body

| Field | Tipe | Wajib | Aturan |
|---|---|---:|---|
| kode | string | Ya | Maksimal 50 karakter, huruf/angka/-/_, unik; disimpan kapital |
| nama | string | Ya | Maksimal 150 karakter |
| tipe_diskon | string | Ya | persen atau nominal |
| nilai | numeric | Ya | Lebih besar dari 0; persentase maksimal 100 |
| maksimum_potongan | numeric/null | Tidak | Jika diisi harus lebih besar dari 0 |
| batas_penggunaan | integer/null | Tidak | Kuota total klaim; jika 10, maksimal 10 pendaftaran pertama |
| berlaku_mulai | date/null | Tidak | Format YYYY-MM-DD |
| berlaku_sampai | date/null | Tidak | Format YYYY-MM-DD, sama dengan atau setelah tanggal mulai |
| is_active | boolean | Ya | Menentukan kode aktif |

~~~json
{
  "kode": "SPMBHEMAT",
  "nama": "Diskon Pendaftaran",
  "tipe_diskon": "persen",
  "nilai": 10,
  "maksimum_potongan": 500000,
  "batas_penggunaan": 10,
  "berlaku_mulai": "2026-11-01",
  "berlaku_sampai": "2026-12-31",
  "is_active": true
}
~~~

### Response Sukses

**201 Created**

~~~json
{
  "status": "success",
  "message": "Kode diskon berhasil dibuat.",
  "data": {
    "id": 12,
    "kode": "SPMBHEMAT",
    "nama": "Diskon Pendaftaran",
    "tipe_diskon": "persen",
    "nilai": "10.00",
    "maksimum_potongan": "500000.00",
    "batas_penggunaan": 10,
    "berlaku_mulai": "2026-11-01",
    "berlaku_sampai": "2026-12-31",
    "is_active": true,
    "created_at": "2026-11-06T10:00:00.000000Z",
    "updated_at": "2026-11-06T10:00:00.000000Z",
    "deleted_at": null
  }
}
~~~

## [GET] /api/spmb/master/kode-diskon/{id}

Mengambil detail satu kode diskon beserta jumlah klaim. Permission: spmb.manage.

### Path Parameters

| Parameter | Tipe | Keterangan |
|---|---|---|
| id | integer | ID kode diskon |

### Response Sukses

**200 OK**

~~~json
{
  "status": "success",
  "message": "Detail kode diskon berhasil dimuat.",
  "data": {
    "id": 12,
    "kode": "SPMBHEMAT",
    "nama": "Diskon Pendaftaran",
    "tipe_diskon": "persen",
    "nilai": "10.00",
    "maksimum_potongan": "500000.00",
    "batas_penggunaan": 10,
    "berlaku_mulai": "2026-11-01",
    "berlaku_sampai": "2026-12-31",
    "is_active": true,
    "created_at": "2026-11-06T10:00:00.000000Z",
    "updated_at": "2026-11-06T10:00:00.000000Z",
    "deleted_at": null,
    "penggunaan_count": 3
  }
}
~~~

## [PUT] /api/spmb/master/kode-diskon/{id}

Memperbarui kode diskon. Permission: spmb.manage. Path parameter id bertipe integer. Body mengikuti field POST; validasi kode unik mengabaikan data dengan ID yang sedang diperbarui.

### Request Body

~~~json
{
  "kode": "SPMBHEMAT",
  "nama": "Diskon Pendaftaran Gelombang 1",
  "tipe_diskon": "persen",
  "nilai": 15,
  "maksimum_potongan": 600000,
  "batas_penggunaan": 10,
  "berlaku_mulai": "2026-11-01",
  "berlaku_sampai": "2026-12-31",
  "is_active": true
}
~~~

### Response Sukses

**200 OK**

~~~json
{
  "status": "success",
  "message": "Kode diskon berhasil diperbarui.",
  "data": {
    "id": 12,
    "kode": "SPMBHEMAT",
    "nama": "Diskon Pendaftaran Gelombang 1",
    "tipe_diskon": "persen",
    "nilai": "15.00",
    "maksimum_potongan": "600000.00",
    "batas_penggunaan": 10,
    "berlaku_mulai": "2026-11-01",
    "berlaku_sampai": "2026-12-31",
    "is_active": true,
    "created_at": "2026-11-06T10:00:00.000000Z",
    "updated_at": "2026-11-06T10:30:00.000000Z",
    "deleted_at": null
  }
}
~~~

## [DELETE] /api/spmb/master/kode-diskon/{id}

Menonaktifkan kode dengan soft delete. Catatan klaim tetap tersedia untuk audit. Permission: spmb.manage. Path parameter id bertipe integer.

### Response Sukses

**200 OK**

~~~json
{
  "status": "success",
  "message": "Kode diskon berhasil dinonaktifkan dengan soft delete.",
  "data": null
}
~~~

## [POST] /api/spmb/kode-diskon/validate

Mempratinjau kode diskon untuk pendaftaran milik pengguna yang sedang login. Permission: spmb.pendaftaran.create.

### Request Body

| Field | Tipe | Wajib | Aturan |
|---|---|---:|---|
| kode | string | Ya | Kode diskon; normalisasi menjadi huruf kapital |
| master_tipe_jalur_id | integer | Ya | ID tipe jalur yang terdaftar |
| program_studi_id | integer | Ya | ID program studi yang terdaftar |
| gelombang_id | integer | Ya | ID gelombang yang terdaftar |

~~~json
{
  "kode": "SPMBHEMAT",
  "master_tipe_jalur_id": 1,
  "program_studi_id": 1,
  "gelombang_id": 1
}
~~~

### Response Sukses

**200 OK**

~~~json
{
  "status": "success",
  "message": "Kode diskon valid.",
  "data": {
    "kode": "SPMBHEMAT",
    "subtotal_komponen": 1000000,
    "nominal_diskon": 100000,
    "total_setelah_diskon": 900000,
    "tipe_diskon": "persen",
    "nilai": 10,
    "already_applied": false
  }
}
~~~

Pratinjau tidak memesan kuota. Kuota diperiksa kembali secara transaksional saat backend menyimpan kode penggunaan dan memulai penerbitan tagihan pendaftaran.

## Format Error

| HTTP | Kondisi | Contoh |
|---:|---|---|
| 400 | JSON request tidak valid | {"message":"Malformed JSON request."} |
| 401 | Token tidak ada atau tidak valid | {"message":"Unauthenticated."} |
| 403 | Tidak memiliki permission endpoint | {"message":"Akses ditolak."} |
| 404 | ID kode tidak ditemukan | {"message":"No query results for model [App\\Models\\Spmb\\SpmbKodeDiskon] 999."} |
| 422 | Validasi gagal, kode kedaluwarsa/nonaktif, kuota habis, atau komponen tidak memenuhi syarat | {"message":"The given data was invalid.","errors":{"batas_penggunaan":["The batas penggunaan must be greater than 0."]}} |

## Aturan Masa Berlaku dan Kuota Klaim

Tanggal mulai dan akhir diperiksa langsung oleh backend setiap kali kode divalidasi dan sebelum tagihan pertama diterbitkan. Kedua tanggal inklusif dan mengikuti zona waktu SPMB_TIMEZONE (default Asia/Jakarta), tanpa scheduler.

batas_penggunaan adalah kuota total klaim. Nilai 10 mengizinkan maksimal sepuluh pendaftaran pertama yang berhasil menerapkan kode; satu pendaftaran dihitung satu kali. Nilai null berarti tanpa batas. Pratinjau tidak memesan kuota. Saat kode diterapkan, backend mengunci baris kode dalam transaksi dan memeriksa ulang jumlah klaim agar permintaan bersamaan tidak melampaui kuota.

Kode hanya diterapkan ke rincian Master Biaya SPMB yang memiliki dibebankan_saat_pendaftaran = true untuk tipe jalur dan program studi yang dipilih. Tarif fallback SIKEU dan komponen daftar ulang tidak memenuhi syarat. Satu pendaftaran hanya dapat memakai satu kode. Kode dan nominal potongan disimpan sebagai snapshot; kode baru tidak dapat dimasukkan setelah tagihan pendaftaran dibuat.

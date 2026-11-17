# SpmbKodeDiskonController

> **Modul**: SPMB / **Base URL**: `/api/spmb` / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-11-17

Admin mengelola kode melalui `/master/kode-diskon` dengan permission `spmb.manage`. Pendaftar mengakses pratinjau melalui `/kode-diskon/validate` dengan permission `spmb.pendaftaran.create`. Preview tidak mengklaim kuota; klaim terjadi saat invoice diterbitkan.

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
| tipe_diskon | string | - | `persen`, `nominal`, atau `total_tetap` |
| nilai_min | numeric | - | Nilai diskon minimum |
| nilai_max | numeric | - | Nilai diskon maksimum |
| maksimum_potongan_min | numeric | - | Batas nominal potongan minimum |
| maksimum_potongan_max | numeric | - | Batas nominal potongan maksimum |
| potongan_status | string | - | `berbatas` atau `tanpa_batas` |
| kuota_status | string | - | `tersedia`, `penuh`, atau `tanpa_batas` berdasarkan jumlah klaim dan kuota |
| berlaku_mulai | date | - | Filter tanggal mulai yang sama persis (YYYY-MM-DD) |
| berlaku_sampai | date | - | Filter tanggal akhir yang sama persis (YYYY-MM-DD) |
| sort_by | string | created_at | kode, nama, tipe_diskon, nilai, batas_penggunaan, berlaku_mulai, berlaku_sampai, is_active, created_at, atau updated_at |
| sort_order | string | desc | asc atau desc |
| per_page | integer | 15 | Jumlah data per halaman, antara 1–100 |
| page | integer | 1 | Nomor halaman |

List/detail menyertakan relasi `jalur_masuk[]` dan `jenis_pendaftaran[]`. Scope disimpan dengan ID master; relasi kosong berarti semua jalur/jenis.

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
      "nilai_min": null,
      "nilai_max": null,
      "maksimum_potongan_min": null,
      "maksimum_potongan_max": null,
      "potongan_status": null,
      "kuota_status": null,
      "berlaku_mulai": null,
      "berlaku_sampai": null,
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
| tipe_diskon | string | Ya | `persen`, `nominal`, atau `total_tetap` |
| nilai | numeric | Ya | Lebih besar dari 0; persentase maksimal 100 |
| maksimum_potongan | numeric/null | Tidak | Batas potongan `persen`/`nominal`; harus null untuk `total_tetap` |
| jalur_masuk_ids | integer[] | Tidak | ID jalur masuk. Kosong berarti semua jalur. |
| master_tipe_jalur_ids | integer[] | Tidak | ID jenis pendaftaran. Kosong berarti semua jenis. |
| batas_penggunaan | integer/null | Tidak | Kuota total klaim; jika 10, maksimal 10 pendaftaran pertama |
| berlaku_mulai | date/null | Tidak | Format YYYY-MM-DD |
| berlaku_sampai | date/null | Tidak | Format YYYY-MM-DD, sama dengan atau setelah tanggal mulai |
| is_active | boolean | Ya | Menentukan kode aktif |

`persen` mengurangi persentase subtotal eligible, `nominal` mengurangi sebesar nilai nominal, dan `total_tetap` membuat total komponen eligible menjadi nilai target atau tetap lebih rendah bila biaya awal sudah di bawah target. Biaya gateway tidak termasuk target.

~~~json
{
  "kode": "SPMBHEMAT",
  "nama": "Diskon Pendaftaran",
  "tipe_diskon": "total_tetap",
  "nilai": 17000,
  "maksimum_potongan": null,
  "jalur_masuk_ids": [1],
  "master_tipe_jalur_ids": [2],
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

## POST /api/spmb/kode-diskon/validate

Mempratinjau kode diskon untuk pendaftaran milik pengguna yang sedang login. Permission: `spmb.pendaftaran.create`. Preview tidak mengklaim kuota. Scope jalur diperiksa dari jalur yang terkait dengan `gelombang_id`; scope jenis dicocokkan dengan `master_tipe_jalur_id`.

### Headers

| Header | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `Authorization` | string | Ya | `Bearer {token_calon_mahasiswa}` |
| `Accept` | string | Ya | `application/json` |
| `Content-Type` | string | Ya | `application/json` |

### Body Parameters

| Parameter | Tipe | Wajib | Aturan |
|---|---|---|---|
| `kode` | string | Ya | Kode diskon, dinormalisasi menjadi huruf kapital |
| `master_tipe_jalur_id` | integer | Ya | ID jenis pendaftaran dari master |
| `program_studi_id` | integer | Ya | ID program studi |
| `gelombang_id` | integer | Ya | ID gelombang; jalur diverifikasi server dari gelombang ini |

### Request Body

```json
{
  "kode": "SPMBHEMAT",
  "master_tipe_jalur_id": 2,
  "program_studi_id": 1,
  "gelombang_id": 1
}
```

### Response Sukses

**200 OK**

```json
{
  "status": "success",
  "message": "Kode diskon valid.",
  "data": {
    "kode": "SPMBHEMAT",
    "subtotal_komponen": 250000,
    "nominal_diskon": 233000,
    "total_setelah_diskon": 17000,
    "tipe_diskon": "total_tetap",
    "nilai": 17000,
    "already_applied": false
  }
}
```

### Response Error

**400 Bad Request**

```json
{"status":"error","message":"Malformed JSON request."}
```

**401 Unauthorized**

```json
{"status":"error","message":"Unauthenticated."}
```

**403 Forbidden**

```json
{"status":"error","message":"Akses ditolak."}
```

**404 Not Found**

```json
{"status":"error","message":"Pendaftaran tidak ditemukan."}
```

**422 Unprocessable Entity**

```json
{
  "status": "error",
  "message": "Data yang diberikan tidak valid.",
  "errors": {"kode_diskon": ["Kode diskon tidak berlaku untuk jalur masuk yang dipilih."]}
}
```

## Aturan Masa Berlaku dan Kuota Klaim

Tanggal mulai dan akhir inklusif dan mengikuti zona waktu `SPMB_TIMEZONE` (default `Asia/Jakarta`). Kuota total dikunci dan diperiksa ulang secara transaksional saat invoice berhasil diterbitkan, sehingga preview/draft tidak memakai kuota dan retry invoice tidak membuat klaim kedua.

Kode hanya diterapkan pada rincian Master Biaya positif yang ditandai `dibebankan_saat_pendaftaran = true`. Tarif fallback SIKEU dan komponen daftar ulang tidak eligible. `total_tetap` menghitung potongan agar total eligible mencapai target, tetapi tidak menaikkan tagihan jika biaya sudah di bawah target; biaya gateway tidak termasuk target.

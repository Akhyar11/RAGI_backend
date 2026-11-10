# H2hBankController

> **Modul**: SIKEU (Sistem Informasi Keuangan Kampus)  
> **Base URL**: `/api/v1/h2h/btns` (dan alias `/api/btns`)  
> **Autentikasi**: Checksum SHA1 + API Token BTNS (Header `Authorization: Bearer {token}` atau `sopingi-btns`)  
> **Dibuat**: 10 November 2026  
> **Diperbarui**: 10 November 2026  
> **Status**: Aktif (Pengganti microservice Go `indonusa_h2h_v2` dan database `sikeudb`)

## Deskripsi Singkat

Controller ini mengimplementasikan integrasi Host-to-Host (H2H) secara **native di dalam RAG** untuk perbankan (Bank BTN Syariah / BTNS). Seluruh alur transaksi inquiry, payment, dan reversal berjalan langsung di database RAG secara realtime (0 detik latensi) tanpa dependensi microservice eksternal atau database legacy.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/h2h/btns/tes` | Health check & ping konektivitas perbankan | Publik |
| POST | `/api/v1/h2h/btns` | Router utama Bank BTNS (Inquiry, Payment, Reversal) | ✅ Token BTNS + Checksum |

---

## 1. GET /api/v1/h2h/btns/tes

Digunakan oleh Network Operations Center (NOC) Bank atau sistem pemantau kampus untuk menguji status ketersediaan endpoint H2H BTN Syariah secara berkala.

### Headers

| Key | Value | Required |
|---|---|---|
| `Accept` | `application/json` | ✅ |

### Query Parameters

Tidak memerlukan parameter kueri.

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Berhasil Akses POLINUS-API H2H Native RAG Via BTNS",
  "data": {
    "rc": "OK",
    "msg": "Berhasil Akses POLINUS-API H2H Native RAG Via BTNS",
    "system": "RAG Unified Core SIKEU",
    "timestamp": "2026-11-10T10:00:00.000000Z"
  },
  "rc": "OK",
  "msg": "Berhasil Akses POLINUS-API H2H Native RAG Via BTNS",
  "system": "RAG Unified Core SIKEU",
  "timestamp": "2026-11-10T10:00:00.000000Z"
}
```

---

## 2. POST /api/v1/h2h/btns (Action: Inquiry)

Digunakan oleh ATM, Mobile Banking, atau Teller Bank BTN Syariah untuk mengecek tagihan mahasiswa menggunakan CUSTID (Nomor Pembayaran).

### Headers

| Key | Value | Required |
|---|---|---|
| `Content-Type` | `application/json` | ✅ |
| `Accept` | `application/json` | ✅ |
| `sopingi-btns` | `{API_TOKEN_BTNS}` | ⚠️ Token bank (header alternatif) |
| `Authorization` | `Bearer {API_TOKEN_BTNS}` | ⚠️ Token bank (header standar) |

### Request Body

```json
{
  "action": "inquiry",
  "kodeBank": "BTNS",
  "kodeBiller": "31310",
  "kodeChannel": "MBANK",
  "kodeTerminal": "ATM_01",
  "nomorPembayaran": "13202110065",
  "tanggalTransaksi": "2026-11-10 10:00:00",
  "idTransaksi": "02006104591116095401",
  "checksum": "3d53bdecab86be84b4b3636cf318dacd87778a27"
}
```
*Formula Checksum Inquiry*: `SHA1(nomorPembayaran + SECRET_KEY_BTNS + tanggalTransaksi)`

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Inquiry Success",
  "data": {
    "id": "132021100652026111001",
    "nomorPembayaran": "13202110065",
    "idPelanggan": "13202110065",
    "nama": "PUTRI SHELLA NOVITA SARI",
    "idTagihan": "2026111001",
    "totalNominal": "1500000",
    "apiBank": "BTNS",
    "createdAt": "2026-11-10 10:00:00",
    "tanggalTransaksi": "2026-11-10 10:00:00",
    "rc": "00",
    "msg": "Inquiry Success",
    "informasi": [
      { "label_key": "NIM", "label_value": "F21027" },
      { "label_key": "Program Studi", "label_value": "D4-Manajemen Informasi Kesehatan" }
    ],
    "rincian": [
      { "kode_rincian": "Sumbangan Pembinaan Pendidikan", "nominal": "1500000" }
    ]
  },
  "id": "132021100652026111001",
  "nomorPembayaran": "13202110065",
  "idPelanggan": "13202110065",
  "nama": "PUTRI SHELLA NOVITA SARI",
  "idTagihan": "2026111001",
  "totalNominal": "1500000",
  "apiBank": "BTNS",
  "createdAt": "2026-11-10 10:00:00",
  "tanggalTransaksi": "2026-11-10 10:00:00",
  "rc": "00",
  "msg": "Inquiry Success",
  "informasi": [
    { "label_key": "NIM", "label_value": "F21027" },
    { "label_key": "Program Studi", "label_value": "D4-Manajemen Informasi Kesehatan" }
  ],
  "rincian": [
    { "kode_rincian": "Sumbangan Pembinaan Pendidikan", "nominal": "1500000" }
  ]
}
```

---

## 3. POST /api/v1/h2h/btns (Action: Payment)

Digunakan oleh Core Banking saat nasabah/mahasiswa berhasil mendebet rekening/menyetor tunai. Transaksi di RAG langsung dieksekusi secara atomic (`DB::transaction`), mengupdate tagihan menjadi `lunas`, mencatat record di `sikeu_pembayaran`, menambah saldo `sikeu_unit_kas` (Bank BSN H2H), dan mencatat jurnal akuntansi umum (`Dr Kas Bank BSN / Cr Piutang Mahasiswa`).

### Request Body

```json
{
  "action": "payment",
  "kodeBank": "BTNS",
  "kodeBiller": "31310",
  "kodeChannel": "MBANK",
  "kodeTerminal": "ATM_01",
  "nomorPembayaran": "13202110065",
  "tanggalTransaksi": "2026-11-10 10:05:00",
  "idTransaksi": "02006104591116095401",
  "idTagihan": "2026111001",
  "totalNominal": "1500000",
  "nomorJurnalPembukuan": "1045900000005679125642085",
  "checksum": "1254d8fa387d226be080e5930bf1bfad37d8b17a"
}
```
*Formula Checksum Payment*: `SHA1(nomorPembayaran + SECRET_KEY_BTNS + tanggalTransaksi + totalNominal + nomorJurnalPembukuan)`

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Payment Success",
  "data": {
    "id": "132021100652026111001",
    "nomorPembayaran": "13202110065",
    "idPelanggan": "13202110065",
    "nama": "PUTRI SHELLA NOVITA SARI",
    "idTagihan": "2026111001",
    "totalNominal": "1500000",
    "nomorJurnalPembukuan": "1045900000005679125642085",
    "apiBank": "BTNS",
    "rc": "00",
    "msg": "Payment Success",
    "informasi": [
      { "label_key": "NIM", "label_value": "F21027" },
      { "label_key": "Program Studi", "label_value": "D4-Manajemen Informasi Kesehatan" }
    ],
    "rincian": [
      { "kode_rincian": "Pelunasan Biaya Kuliah", "nominal": "1500000" }
    ]
  },
  "id": "132021100652026111001",
  "nomorPembayaran": "13202110065",
  "idPelanggan": "13202110065",
  "nama": "PUTRI SHELLA NOVITA SARI",
  "idTagihan": "2026111001",
  "totalNominal": "1500000",
  "nomorJurnalPembukuan": "1045900000005679125642085",
  "apiBank": "BTNS",
  "rc": "00",
  "msg": "Payment Success",
  "informasi": [
    { "label_key": "NIM", "label_value": "F21027" },
    { "label_key": "Program Studi", "label_value": "D4-Manajemen Informasi Kesehatan" }
  ],
  "rincian": [
    { "kode_rincian": "Pelunasan Biaya Kuliah", "nominal": "1500000" }
  ]
}
```

---

## 4. POST /api/v1/h2h/btns (Action: Reversal)

Digunakan jika core banking perlu membatalkan transaksi yang telah dieksekusi (timeout settlement atau kesalahan terminal).

### Request Body

```json
{
  "action": "reversal",
  "kodeBank": "BTNS",
  "nomorPembayaran": "13202110065",
  "tanggalTransaksi": "2026-11-10 10:10:00",
  "tanggalTransaksiAsal": "2026-11-10 10:05:00",
  "idTransaksi": "REV-02006104591116",
  "totalNominal": "1500000",
  "nomorJurnalPembukuan": "1045900000005679125642085",
  "checksum": "a66bc50a302f7d4fe380f820015ec6e0e6fa85a5"
}
```

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Reversal Success",
  "data": {
    "rc": "00",
    "msg": "Reversal Success",
    "nomorJurnalPembukuan": "1045900000005679125642085",
    "nomorPembayaran": "13202110065"
  },
  "rc": "00",
  "msg": "Reversal Success",
  "nomorJurnalPembukuan": "1045900000005679125642085"
}
```

---

## 5. Respon Error & HTTP Status Codes

### Response Error (400 Bad Request / Checksum Invalid)

```json
{
  "status": "error",
  "message": "Checksum invalid",
  "rc": "30",
  "msg": "Checksum invalid"
}
```

### Response Error (400 Bad Request / Collecting Agent Bank Tidak Dikenal)

```json
{
  "status": "error",
  "message": "Collecting Agent (Bank) tidak dikenal",
  "rc": "31",
  "msg": "Collecting Agent (Bank) tidak dikenal"
}
```

### Response Error (401 Unauthorized / Token Bank Tidak Ditemukan)

```json
{
  "status": "error",
  "message": "API Token Bank Tidak Valid atau Tidak Ditemukan",
  "rc": "401",
  "msg": "API Token Bank Tidak Valid atau Tidak Ditemukan"
}
```

### Response Error (404 Not Found / Tagihan Belum Ada Invoice)

```json
{
  "status": "error",
  "message": "Nomor Pembayaran yg dimasukan belum ada invoice",
  "rc": "01",
  "msg": "Nomor Pembayaran yg dimasukan belum ada invoice"
}
```

### Response Error (422 Unprocessable Entity / Validasi Request Gagal)

```json
{
  "status": "error",
  "message": "Field checksum wajib diisi.",
  "errors": {
    "checksum": [
      "Field checksum wajib diisi."
    ]
  }
}
```

### Response Error (500 Internal Server Error / Database Problem)

```json
{
  "status": "error",
  "message": "Database problem - gagal memproses transaksi pembayaran",
  "rc": "91",
  "msg": "Database problem - gagal memproses transaksi pembayaran"
}
```

---

## 6. Tabel Kode Respon Bank (`rc`)

| `rc` | HTTP Code | Keterangan |
|---|---|---|
| `00` | 200 OK | Transaksi berhasil (Inquiry, Payment, atau Reversal) |
| `01` | 404 Not Found | Nomor Pembayaran / CUSTID belum memiliki tagihan aktif |
| `12` | 404 Not Found | Transaksi pembatalan ditolak, data pembayaran tidak ditemukan |
| `13` | 400 Bad Request | Nominal pembayaran tidak sesuai dengan total tagihan |
| `14` | 400 Bad Request | Tagihan tidak dikenal atau sudah lunas |
| `30` | 400 Bad Request | Format pesan tidak valid atau Checksum invalid |
| `31` | 400 Bad Request | Kode Bank tidak dikenal (bukan BTNS) |
| `88` | 400 Bad Request | Tagihan sudah terbayar |
| `91` | 500 Internal Error | Database / Internal server error |
| `94` | 400 Bad Request | Transaksi sudah pernah di-reversal sebelumnya |

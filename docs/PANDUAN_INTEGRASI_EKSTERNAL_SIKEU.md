# Panduan Integrasi API Eksternal — Modul SIKEU (Keuangan)

> **Dokumen**: Spesifikasi Integrasi Sistem Eksternal & Pihak Ketiga  
> **Modul Target**: SIKEU (Sistem Informasi Keuangan & Akuntansi Kampus)  
> **Sistem Induk**: RAG (Sistem Informasi Terpadu Kampus)  
> **Base URL Production**: `https://{domain-rag}/api/v1/sikeu`  
> **Base URL Local/Dev**: `http://localhost:8000/api/v1/sikeu`  
> **Format Data**: JSON (RFC 8259)  
> **Protokol**: HTTPS RESTful API  
> **Terakhir Diperbarui**: 2026-11-14

---

## 1. Pendahuluan & Arsitektur Integrasi

Modul **SIKEU** pada platform RAG bertindak sebagai **Central Financial Ledger & Billing Gateway** bagi seluruh transaksi keuangan kampus. Modul ini bertanggung jawab atas:
- Manajemen tarif akademik per angkatan & jalur kelas.
- Penerbitan invoice/tagihan mahasiswa (UKT/SPP, SPMB, Wisuda, Laboratorium, Asrama, dll.).
- Rekonsiliasi pembayaran kasir, perbankan (*Host-to-Host* BTN Syariah), dan payment gateway (*Midtrans* / *Xendit*).
- Pembukuan akuntansi otomatis berbasis pembukuan berpasangan (*Double-Entry Balanced Journal*).
- Manajemen kas operasional, kas kecil (*petty cash*), panjar UMK, dan pelaporan pajak.

Dokumen ini ditujukan bagi pengembang sistem di luar modul SIKEU yang memerlukan pertukaran data, antara lain:
1. **Sistem SPMB Luar**: Menerbitkan tagihan registrasi/formulir dan menerima webhook pelunasan.
2. **Sistem Akademik (SIAKAD Luar) / LMS Luar**: Mengecek kelayakan keuangan mahasiswa (*financial clearance gate*) sebelum mengizinkan KRS, ujian, atau cetak transkrip.
3. **Aplikasi Mobile Mahasiswa / Portal Eksternal**: Menampilkan tagihan aktif, riwayat transaksi, dan status beasiswa.
4. **Sistem Keuangan Holding / ERP Yayasan**: Menarik data Jurnal Umum, Buku Besar, dan Neraca Saldo untuk konsolidasi tahunan.
5. **Aplikasi Ormawa / Unit Khusus**: Mengajukan permohonan dana kegiatan operasional atau panjar dinas.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                       SISTEM EKSTERNAL / PIHAK KETIGA                       │
│  [SPMB Luar]   [SIAKAD/LMS Luar]   [ERP Yayasan]   [Mobile App]   [Bank]    │
└────────┬───────────────┬───────────────────┬──────────────┬───────────┬─────┘
         │               │                   │              │           │
         │ (POST tagihan)│ (GET eligibilitas)│ (GET jurnal) │(GET bills)│ (Webhook)
         ▼               ▼                   ▼              ▼           ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                      RAG SIKEU API GATEWAY (/api/v1/sikeu)                   │
│                                                                             │
│  ┌──────────────────────┐  ┌──────────────────────┐  ┌───────────────────┐  │
│  │ Sanctum Auth / Token │  │  RBAC Check (Gates)  │  │ Audit Log Service │  │
│  └──────────┬───────────┘  └──────────┬───────────┘  └─────────┬─────────┘  │
│             └─────────────────────────┼────────────────────────┘            │
│                                       ▼                                     │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                           SERVICE LAYER                               │  │
│  │   • ExternalTagihanService      • SikeuCompatService (Gate Legacy)    │  │
│  │   • AkuntansiService (COA/JU)   • SikeuMasterService (Tarif)          │  │
│  │   • SikeuMigratorService        • PaymentGatewayService               │  │
│  └────────────────────────────────────┬──────────────────────────────────┘  │
│                                       ▼                                     │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                 DATABASE RAG (PostgreSQL / MySQL / SQLite)            │  │
│  │  sikeu_tagihan_mahasiswa | sikeu_pembayaran_mahasiswa | sikeu_jurnal  │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Keamanan, Autentikasi, & IAM Permissions

Setiap request dari sistem eksternal **WAJIB** melalui mekanisme autentikasi dan otorisasi terpusat.

### 2.1. Standar Header HTTP

Setiap request HTTP wajib menyertakan header berikut:

```http
Authorization: Bearer <API_SERVICE_TOKEN>
Accept: application/json
Content-Type: application/json
X-Service-Name: NAMA_SISTEM_ANDA
X-Idempotency-Key: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d (Wajib untuk POST/PUT transaksi)
```

> [!IMPORTANT]
> **Idempotency Key (`X-Idempotency-Key`)**: Gunakan UUID v4 unik saat mengirimkan pembuatan tagihan atau pembayaran. Jika terjadi kegagalan jaringan (*timeout*), request dapat dikirim ulang dengan UUID yang sama tanpa risiko menimbulkan *double billing*.

### 2.2. Pembuatan Service Account di IAM

Untuk menghubungkan sistem eksternal:
1. Hubungi Administrator IAM RAG untuk membuat akun bertipe `system` atau `service_account`.
2. Akun akan diberikan API Token (Sanctum Personal Access Token) dengan waktu kedaluwarsa sesuai perjanjian keamanan (default: 365 hari atau *never expires* untuk server-to-server).
3. Akun diberikan permission minimal sesuai data yang dibutuhkan (*Principle of Least Privilege*).

### 2.3. Matriks Permission SIKEU yang Relevan

| Kode Permission | Deskripsi Akses | Digunakan Oleh |
|---|---|---|
| `sikeu.tagihan.read` | Membaca daftar tagihan, rincian biaya, dan kelayakan ujian | Portal Mahasiswa, SIAKAD, LMS |
| `sikeu.tagihan.manage` | Menerbitkan invoice/tagihan baru secara remote | SPMB Luar, Kursus/Diklat Eksternal |
| `sikeu.pembayaran.read` | Mengambil riwayat mutasi dan bukti transaksi kas/bank | Rekonsiliasi Bank, Dashboard BI |
| `sikeu.akuntansi.read` | Mengambil data COA, Jurnal Umum, dan Buku Besar | ERP Holding, Auditor Finansial |
| `sikeu.master.read` | Mengakses katalog tarif biaya, beasiswa, dan unit kas | Web Promosi, Kalkulator UKT |
| `sikeu.pengajuan.create` | Mengajukan pencairan kas operasional / panjar UMK | Portal Ormawa Eksternal |

---

## 3. Format Response & Penanganan Error

Semua response API SIKEU dibungkus dalam *envelope JSON* standar sesuai ketentuan `api_crud_standard`.

### 3.1. Response Sukses Data Tunggal / Aksi
```json
{
  "status": "success",
  "message": "Data berhasil diproses",
  "data": {
    "id": 105,
    "nomor_tagihan": "INV-2026-000105",
    "total_bayar": 3500000.00
  }
}
```

### 3.2. Response Sukses Daftar Data dengan Paginasi
```json
{
  "status": "success",
  "message": "Data retrieved successfully",
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 120,
    "last_page": 8,
    "from": 1,
    "to": 15
  },
  "filters": {
    "search": "2023001",
    "sort_by": "created_at",
    "sort_order": "desc"
  }
}
```

### 3.3. Response Error Validasi (422 Unprocessable Entity)
```json
{
  "status": "error",
  "message": "Data yang diberikan tidak valid.",
  "errors": {
    "mahasiswa_id": ["Mahasiswa ID tidak ditemukan di sistem akademik."],
    "details": ["Rincian komponen tagihan wajib diisi minimal 1 item."]
  }
}
```

### 3.4. Kode Status HTTP

| Kode | Keterangan |
|---|---|
| `200 OK` | Permintaan data atau pembaruan berhasil |
| `201 Created` | Tagihan atau transaksi baru berhasil diterbitkan |
| `400 Bad Request` | Struktur JSON rusak atau parameter wajib tidak sesuai |
| `401 Unauthorized` | Bearer token tidak disertakan atau sudah kadaluwarsa |
| `403 Forbidden` | Service account tidak memiliki permission untuk endpoint tersebut |
| `404 Not Found` | Data (mahasiswa, tagihan, akun COA) tidak ditemukan |
| `422 Unprocessable Entity` | Validasi aturan bisnis gagal |
| `429 Too Many Requests` | Melebihi batas kuota pemanggilan (Rate limit) |
| `500 Server Error` | Terjadi kesalahan internal server (dilengkapi audit logging) |

---

## 4. Katalog Endpoint Integrasi Utama

### KELOMPOK 1: Validasi Kelayakan Finansial Mahasiswa (Financial Gate)

Endpoint ini sangat krusial bagi sistem akademik (SIAKAD), LMS, perpustakaan, atau portal ujian luar untuk memastikan mahasiswa tidak memiliki tunggakan sebelum mengakses layanan akademik.

#### 1.1. Cek Ringkasan Pembayaran & Tunggakan Mahasiswa
- **Method & URL**: `GET /api/v1/sikeu/compat/ringkasan-pembayaran`
- **Permission**: `sikeu.tagihan.read`
- **Query Parameters**:
  - `nim` *(string, opsional)*: NIM Mahasiswa (contoh: `202301001`)
  - `mahasiswa_id` *(integer, opsional)*: ID Mahasiswa di tabel `siakad_mahasiswa`

**Contoh Response (200 OK)**:
```json
{
  "status": "success",
  "message": "Ringkasan pembayaran berhasil dimuat",
  "data": {
    "mahasiswa_id": 42,
    "nim": "202301001",
    "nama": "Budi Santoso",
    "prodi": "D3 Rekayasa Perangkat Lunak",
    "tahun_angkatan": 2023,
    "jalur_kelas": "Reguler",
    "total_tagihan": 15000000.00,
    "total_terbayar": 12500000.00,
    "total_potongan_beasiswa": 1000000.00,
    "sisa_tunggakan": 1500000.00,
    "status_keuangan": "MENUNGGAK",
    "has_active_dispensasi": true,
    "dispensasi": {
      "nomor_surat": "DISP/2026/08/012",
      "tenggat_waktu": "2026-12-15",
      "status": "DISETUJUI"
    }
  }
}
```

---

#### 1.2. Cek Eligibilitas Mengikuti Ujian (UTS / UAS)
- **Method & URL**: `GET /api/v1/sikeu/compat/eligibilitas-ujian`
- **Permission**: `sikeu.tagihan.read`
- **Query Parameters**:
  - `mahasiswa_id` *(integer, wajib)*: ID Mahasiswa
  - `tipe_ujian` *(string, wajib)*: `UTS` atau `UAS`
  - `semester` *(integer, opsional)*: Semester aktif mahasiswa (1 - 14)

**Contoh Response (200 OK)**:
```json
{
  "status": "success",
  "message": "Eligibilitas ujian berhasil diperiksa",
  "data": {
    "mahasiswa_id": 42,
    "tipe_ujian": "UTS",
    "is_eligible": true,
    "alasan": "Memenuhi syarat: Pelunasan tahap 1 telah mencapai 50% atau memiliki dispensasi aktif pimpinan.",
    "persentase_lunas": 75.0,
    "sisa_tunggakan": 1500000.00,
    "tenggat_dispensasi": "2026-12-15"
  }
}
```

---

#### 1.3. Cek Tagihan Aktif Mahasiswa
- **Method & URL**: `GET /api/v1/sikeu/compat/tagihan-aktif`
- **Permission**: `sikeu.tagihan.read`
- **Query Parameters**: `nim` atau `mahasiswa_id`, `per_page`, `page`

**Contoh Response (200 OK)**:
```json
{
  "status": "success",
  "message": "Tagihan aktif berhasil dimuat",
  "data": [
    {
      "id": 88,
      "nomor_tagihan": "INV-UKT-2026-088",
      "deskripsi": "UKT Semester Ganjil 2026/2027",
      "nominal": 4500000.00,
      "terbayar": 3000000.00,
      "sisa": 1500000.00,
      "jatuh_tempo": "2026-10-10",
      "status": "sebagian"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 1,
    "last_page": 1
  }
}
```

---

### KELOMPOK 2: Penerbitan Tagihan dari Sistem Eksternal (External Invoicing)

Endpoint ini digunakan ketika sistem eksternal ingin menerbitkan invoice resmi yang tercatat di RAG SIKEU dan dapat dibayarkan melalui saluran pembayaran kampus (Bank / VA / QRIS / Kasir).

#### 2.1. Terbitkan Tagihan Eksternal
- **Method & URL**: `POST /api/v1/sikeu/tagihan/external`
- **Permission**: `sikeu.tagihan.manage`

**Request Headers**:
```http
Authorization: Bearer <API_TOKEN>
X-Idempotency-Key: c9b794f8-1123-4b68-8097-f82312df5981
```

**Request Body**:
```json
{
  "source_system": "SPMB_PORTAL_LUAR",
  "tipe_referensi": "calon_mahasiswa",
  "calon_mahasiswa_id": 991,
  "mahasiswa_id": null,
  "tahun_akademik_id": 1,
  "jatuh_tempo": "2026-12-31",
  "keterangan": "Biaya Pendaftaran Ujian Mandiri & Psikotest",
  "requires_approval": false,
  "details": [
    {
      "jenis_biaya_kode": "SPMB_ADM",
      "nominal": 350000.00,
      "keterangan": "Formulir Pendaftaran"
    },
    {
      "jenis_biaya_kode": "SPMB_TES",
      "nominal": 150000.00,
      "keterangan": "Biaya Ujian CBT & Psikotest"
    }
  ],
  "potongan": [
    {
      "tipe": "diskon",
      "nominal_potongan": 50000.00,
      "keterangan": "Voucher Gelombang Early Bird"
    }
  ]
}
```

**Contoh Response (201 Created)**:
```json
{
  "status": "success",
  "message": "Tagihan eksternal berhasil diterbitkan dan Virtual Account aktif.",
  "data": {
    "tagihan": {
      "id": 142,
      "nomor_tagihan": "INV-EXT-20261114-991A",
      "source_system": "SPMB_PORTAL_LUAR",
      "total_tagihan": 500000.00,
      "total_potongan": 50000.00,
      "total_bayar": 450000.00,
      "status": "belum_bayar",
      "jatuh_tempo": "2026-12-31"
    },
    "virtual_account": {
      "va_number": "888261114000142",
      "bank_kode": "BTN_SYARIAH",
      "nominal": 450000.00,
      "expired_at": "2026-12-31 23:59:59"
    }
  }
}
```

---

### KELOMPOK 3: Riwayat Pembayaran & Rekapitulasi Piutang

#### 3.1. Tarik Riwayat Transaksi Pembayaran
- **Method & URL**: `GET /api/v1/sikeu/pembayaran`
- **Permission**: `sikeu.pembayaran.read`
- **Query Parameters**:
  - `mahasiswa_id` *(integer, opsional)*
  - `start_date` *(date Y-m-d, opsional)*: Tanggal mulai transaksi
  - `end_date` *(date Y-m-d, opsional)*: Tanggal selesai transaksi
  - `metode_pembayaran` *(string, opsional)*: `TUNAI`, `TRANSFER`, `H2H_BTN`, `MIDTRANS_QRIS`
  - `per_page` *(integer, default: 15, max: 100)*
  - `page` *(integer, default: 1)*

**Contoh Response (200 OK)**:
```json
{
  "status": "success",
  "message": "Data retrieved successfully",
  "data": [
    {
      "id": 512,
      "nomor_transaksi": "BYR-2026-11-00512",
      "tanggal_bayar": "2026-11-13 14:22:10",
      "tagihan_id": 88,
      "nomor_tagihan": "INV-UKT-2026-088",
      "nim": "202301001",
      "nama_mahasiswa": "Budi Santoso",
      "nominal": 3000000.00,
      "metode_pembayaran": "MIDTRANS_QRIS",
      "nomor_referensi": "MID-QRIS-9921827",
      "unit_kas": "Kas Bank Penampungan UKT",
      "status": "berhasil"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 1,
    "last_page": 1
  }
}
```

---

#### 3.2. Rekapitulasi Piutang Mahasiswa
- **Method & URL**: `GET /api/v1/sikeu/piutang`
- **Permission**: `sikeu.tagihan.read`
- **Query Parameters**:
  - `angkatan` *(integer, opsional)*: Filter tahun angkatan (contoh: `2024`)
  - `prodi_id` *(integer, opsional)*: Filter program studi
  - `status_lunas` *(boolean, opsional)*: `false` untuk melihat tunggakan saja

---

### KELOMPOK 4: Master Tarif & Katalog Biaya Kampus

Digunakan oleh landing page kampus, portal promosi, atau sistem pendaftaran luar untuk menampilkan simulasi biaya pendidikan secara dinamis tanpa hardcode.

#### 4.1. Katalog Tarif Berdasarkan Angkatan & Jalur Kelas
- **Method & URL**: `GET /api/v1/sikeu/setting-tarif`
- **Permission**: `sikeu.master.read`
- **Query Parameters**:
  - `tahun_angkatan` *(integer, opsional)*: Contoh `2025`
  - `prodi_id` *(integer, opsional)*
  - `jalur_kelas_id` *(integer, opsional)*: Reguler / Karyawan / Internasional

**Contoh Response (200 OK)**:
```json
{
  "status": "success",
  "message": "Master tarif berhasil dimuat",
  "data": [
    {
      "id": 12,
      "tahun_angkatan": 2025,
      "prodi": "D3 Rekayasa Perangkat Lunak",
      "jalur_kelas": "Reguler",
      "semester": 1,
      "komponen_biaya": [
        { "kode": "UKT_POKOK", "nama": "UKT Pokok Semester", "nominal": 3500000.00 },
        { "kode": "DPP_GEDUNG", "nama": "Dana Pengembangan (Gedung)", "nominal": 2000000.00 },
        { "kode": "SERAGAM", "nama": "Atribut & Jas Almamater", "nominal": 750000.00 }
      ],
      "total_biaya_semester": 6250000.00
    }
  ]
}
```

---

### KELOMPOK 5: Konsolidasi Akuntansi (ERP Holding / Yayasan)

Bagi pengembang sistem ERP yayasan, audit keuangan, atau pelaporan konsolidasi, RAG SIKEU menyediakan endpoint general ledger lengkap dengan integritas debet-kredit yang seimbang.

#### 5.1. Daftar Bagan Akun (Chart of Accounts / COA)
- **Method & URL**: `GET /api/v1/sikeu/akuntansi/coa`
- **Permission**: `sikeu.akuntansi.read`

#### 5.2. Jurnal Umum Berimbang (General Journal Stream)
- **Method & URL**: `GET /api/v1/sikeu/akuntansi/jurnal`
- **Permission**: `sikeu.akuntansi.read`
- **Query Parameters**:
  - `start_date` *(date Y-m-d)*
  - `end_date` *(date Y-m-d)*
  - `kode_akun` *(string, opsional)*: Filter akun tertentu (misal: `102.05` atau `401.01`)
  - `per_page` *(integer, max: 100)*

**Contoh Response (200 OK)**:
```json
{
  "status": "success",
  "message": "Data jurnal umum berhasil dimuat",
  "data": [
    {
      "id": 1024,
      "nomor_jurnal": "JU-2026-11-001024",
      "tanggal": "2026-11-13",
      "keterangan": "Penerimaan Pembayaran UKT NIM 202301001",
      "total_debet": 3000000.00,
      "total_kredit": 3000000.00,
      "is_balanced": true,
      "items": [
        {
          "kode_akun": "102.05",
          "nama_akun": "Xendit Penampungan Disbursement",
          "posisi": "debet",
          "nominal": 3000000.00
        },
        {
          "kode_akun": "401.01",
          "nama_akun": "Pendapatan UKT",
          "posisi": "kredit",
          "nominal": 3000000.00
        }
      ]
    }
  ]
}
```

---

### KELOMPOK 6: Antarmuka Migrator & Stream Sinkronisasi

Bagi sistem legacy yang ingin menyuplai transaksi historis ke RAG SIKEU:
- **`GET /api/v1/sikeu/migrator/status`**: Memeriksa ringkasan data legacy yang telah masuk, status mapping akun COA, dan koneksi.
- **`POST /api/v1/sikeu/migrator/start`**: Memicu background queue job (`MigrateSikeuJob`) untuk menarik stream data dari API eksternal.
- **`GET /api/v1/sikeu/migrator/mappings`**: Menampilkan pemetaan kode perkiraan lama ke kode COA baru standar RAG.

---

## 5. Webhook Asinkron: Notifikasi Pembayaran ke Sistem Eksternal

Ketika mahasiswa melunasi tagihan di RAG SIKEU (melalui Bank H2H, Kasir, atau Payment Gateway), RAG dapat mengirimkan notifikasi *Webhook HTTP POST* ke URL endpoint yang didaftarkan oleh sistem eksternal Anda.

### 5.1. Payload Webhook (`payment.received`)

```json
{
  "event": "sikeu.payment.received",
  "timestamp": "2026-11-14T08:30:15Z",
  "event_id": "evt_7f18b9c20a1",
  "data": {
    "nomor_transaksi": "BYR-2026-11-00512",
    "nomor_tagihan": "INV-EXT-20261114-991A",
    "source_system": "SPMB_PORTAL_LUAR",
    "calon_mahasiswa_id": 991,
    "mahasiswa_id": null,
    "nominal_dibayar": 450000.00,
    "sisa_tagihan": 0.00,
    "status_tagihan": "lunas",
    "metode_pembayaran": "BTN_SYARIAH_VA",
    "nomor_referensi": "VA-888261114000142"
  }
}
```

### 5.2. Keamanan & Verifikasi Webhook Signature

RAG menyertakan header `X-RAG-Signature` pada setiap pengiriman webhook. Sistem eksternal **WAJIB** memvalidasi signature menggunakan algoritma **HMAC-SHA256**:

$$\text{Signature} = \text{hash\_hmac}('sha256', \text{Raw\_Request\_Body}, \text{WEBHOOK\_SECRET})$$

```php
// Contoh verifikasi di PHP
$receivedSignature = $_SERVER['HTTP_X_RAG_SIGNATURE'] ?? '';
$payload = file_get_contents('php://input');
$expectedSignature = hash_hmac('sha256', $payload, env('RAG_WEBHOOK_SECRET'));

if (!hash_equals($expectedSignature, $receivedSignature)) {
    http_response_code(401);
    exit('Invalid Signature');
}
```

---

## 6. Contoh Implementasi Kode Pemanggil (Code Snippets)

### 6.1. Node.js / TypeScript (Axios)
```typescript
import axios from 'axios';

const sikeuClient = axios.create({
  baseURL: 'https://rag.kampus.ac.id/api/v1/sikeu',
  timeout: 10000,
  headers: {
    'Authorization': `Bearer ${process.env.RAG_SIKEU_API_TOKEN}`,
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-Service-Name': 'LMS_MOODLE_EXT'
  }
});

// Cek kelayakan ujian mahasiswa
export async function checkExamClearance(nim: string) {
  try {
    const response = await sikeuClient.get('/compat/ringkasan-pembayaran', {
      params: { nim }
    });
    const { sisa_tunggakan, has_active_dispensasi } = response.data.data;
    
    // Syarat: lunas atau punya dispensasi resmi
    const canTakeExam = sisa_tunggakan <= 0 || has_active_dispensasi;
    return { canTakeExam, sisa_tunggakan };
  } catch (error) {
    console.error('Gagal menghubungi RAG SIKEU:', error);
    throw error;
  }
}
```

### 6.2. PHP (Laravel HTTP Client)
```php
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RAGSikeuIntegrationService
{
    private string $baseUrl;
    private string $apiToken;

    public function __construct()
    {
        $this->baseUrl = config('services.rag.url') . '/api/v1/sikeu';
        $this->apiToken = config('services.rag.token');
    }

    /**
     * Menerbitkan tagihan pendaftaran SPMB ke RAG SIKEU
     */
    public function createRegistrationBill(int $calonMahasiswaId, float $nominal)
    {
        $response = Http::withToken($this->apiToken)
            ->withHeaders([
                'X-Service-Name'     => 'SPMB_PORTAL',
                'X-Idempotency-Key'  => (string) Str::uuid(),
            ])
            ->post("{$this->baseUrl}/tagihan/external", [
                'source_system'      => 'SPMB_PORTAL',
                'tipe_referensi'     => 'calon_mahasiswa',
                'calon_mahasiswa_id' => $calonMahasiswaId,
                'jatuh_tempo'        => now()->addDays(7)->format('Y-m-d'),
                'keterangan'         => 'Biaya Formulir SPMB Gelombang 1',
                'requires_approval'  => false,
                'details'            => [
                    [
                        'jenis_biaya_kode' => 'SPMB_ADM',
                        'nominal'          => $nominal,
                        'keterangan'       => 'Pendaftaran Ujian'
                    ]
                ]
            ]);

        if ($response->failed()) {
            throw new \Exception("Gagal menerbitkan tagihan: " . $response->json('message'));
        }

        return $response->json('data');
    }
}
```

### 6.3. Python (Requests)
```python
import os
import requests
import uuid

BASE_URL = "https://rag.kampus.ac.id/api/v1/sikeu"
API_TOKEN = os.getenv("RAG_SIKEU_TOKEN")

headers = {
    "Authorization": f"Bearer {API_TOKEN}",
    "Accept": "application/json",
    "X-Service-Name": "DATA_LAKE_YAYASAN"
}

def get_general_journal(start_date: str, end_date: str):
    url = f"{BASE_URL}/akuntansi/jurnal"
    params = {
        "start_date": start_date,
        "end_date": end_date,
        "per_page": 100
    }
    
    response = requests.get(url, headers=headers, params=params)
    response.raise_for_status()
    
    payload = response.json()
    return payload.get("data", [])
```

---

## 7. Checklist Implementasi Integrasi Eksternal

Bagi pengembang yang akan memulai integrasi:

- [ ] **Registrasi Kredensial**: Ajukan Service Account dan Bearer Token ke Tim Administrator IAM RAG.
- [ ] **Pengujian Endpoint**: Tes koneksi ke base URL development/staging dan pastikan token valid (`200 OK`).
- [ ] **Implementasi Idempotency Key**: Pastikan setiap request transaksi `POST` menyertakan header `X-Idempotency-Key`.
- [ ] **Penanganan Timeout & Retry**: Atur timeout HTTP minimal 10 detik, gunakan maksimal 3 kali percobaan (*retry*) dengan *exponential backoff*.
- [ ] **Validasi Signature Webhook**: Jika menggunakan webhook, pastikan endpoint penerima memvalidasi header `X-RAG-Signature`.
- [ ] **Monitoring & Alerting**: Catat log kegagalan HTTP 4xx/5xx pada sistem Anda untuk memudahkan investigasi lintas sistem.

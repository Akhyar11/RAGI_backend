# SystemNotificationController

> **Modul**: System / Inbox pribadi
> **Base URL**: `/api/notifications`
> **Autentikasi**: Bearer Token (Passport, `auth:api`)
> **Dibuat**: 2026-11-10
> **Diperbarui**: 2026-11-11

Inbox personal untuk notifikasi lintas modul. Penerima hanya dapat melihat serta menandai miliknya sendiri, tanpa permission pengelolaan modul. Feed LMS tetap memakai `/api/v1/lms/notifikasi`; status baca kedua feed independen.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/notifications` | Daftar terpaginasikan | ✅ Pengguna login |
| GET | `/api/notifications/unread-count` | Jumlah belum dibaca | ✅ Pengguna login |
| POST | `/api/notifications/{id}/read` | Tandai satu notifikasi | ✅ Pemilik |
| POST | `/api/notifications/read-all` | Tandai semua milik sendiri | ✅ Pengguna login |

## Headers (seluruh endpoint)

| Key | Value | Required |
|---|---|---|
| Authorization | `Bearer {access_token}` | ✅ |
| Accept | `application/json` | ✅ |
| Content-Type | `application/json` | POST |

## GET /api/notifications

| Parameter | Type | Default | Keterangan |
|---|---|---|---|
| page | integer | 1 | Minimum 1 |
| per_page | integer | 15 | Minimum 1; nilai di atas 100 dibatasi menjadi 100 |
| search | string | null | Cari title/message, maksimum 200 karakter |
| module_id | integer | null | ID dari `core_modules`, bukan label modul |
| is_read | boolean | null | `true`/`false` atau `1`/`0`; kosong berarti semua |
| sort_by | string | created_at | created_at, read_at, priority |
| sort_order | string | desc | asc atau desc; ID menjadi urutan pembanding kedua |

**200 OK** (contoh satu notifikasi):

```json
{
  "status": "success",
  "message": "Daftar notifikasi berhasil diambil.",
  "data": [{
    "id": 42,
    "user_id": 7,
    "module_id": 3,
    "type": "application_submitted",
    "title": "Pendaftaran SPMB berhasil dikirim",
    "message": "REG-123: Pendaftaran Anda menunggu verifikasi administrasi.",
    "url": "/spmb/dashboard",
    "entity_type": "App\\Models\\Spmb\\PendaftaranCalonMhs",
    "entity_id": 12,
    "priority": "normal",
    "is_read": false,
    "read_at": null,
    "created_at": "2026-11-10T03:00:00.000000Z",
    "updated_at": "2026-11-10T03:00:00.000000Z",
    "module": {"id": 3, "name": "Penerimaan", "code": "spmb", "primary_color": null}
  }],
  "meta": {"current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1},
  "filters": {"search": null, "module_id": null, "is_read": null, "sort_by": "created_at", "sort_order": "desc"}
}
```

`module` dapat null jika master belum tersedia/dihapus; notifikasi tetap tersimpan. `dedup_key` adalah detail internal dan tidak dikembalikan. List kosong menghasilkan `data: []`, `total: 0`, `from: null`, `to: null`.

## GET /api/notifications/unread-count

Tanpa query/body. Menghitung seluruh notifikasi belum dibaca pengguna, tidak mengikuti filter list.

**200 OK**:

```json
{"status":"success","message":"Jumlah notifikasi belum dibaca berhasil diambil.","data":{"unread_count":2}}
```

## POST /api/notifications/{id}/read

Tanpa body. ID numerik. Mengembalikan satu objek dengan field seperti item list, `is_read: true` dan `read_at` timestamp UTC. Pemanggilan ulang tidak mengubah `read_at`.

**200 OK**:

```json
{
  "status": "success",
  "message": "Notifikasi ditandai sudah dibaca.",
  "data": {
    "id": 42, "user_id": 7, "module_id": 3, "type": "application_submitted",
    "title": "Pendaftaran SPMB berhasil dikirim", "message": "REG-123: Pendaftaran Anda menunggu verifikasi administrasi.",
    "url": "/spmb/dashboard", "entity_type": "App\\Models\\Spmb\\PendaftaranCalonMhs", "entity_id": 12,
    "priority": "normal", "is_read": true, "read_at": "2026-11-10T04:00:00.000000Z",
    "created_at": "2026-11-10T03:00:00.000000Z", "updated_at": "2026-11-10T04:00:00.000000Z",
    "module": {"id": 3, "name": "Penerimaan", "code": "spmb", "primary_color": null}
  }
}
```

## POST /api/notifications/read-all

Tanpa body. Hanya notifikasi pengguna yang belum dibaca yang diperbarui. Status LMS dan pengguna lain tetap independen.

**200 OK**:

```json
{"status":"success","message":"Notifikasi ditandai sudah dibaca.","data":{"updated":2}}
```

Jika tidak ada perubahan:

```json
{"status":"success","message":"Tidak ada notifikasi belum dibaca.","data":{"updated":0}}
```

## Respons Error

- **401 Unauthorized**, seluruh endpoint tanpa token valid:

```json
{"status":"error","message":"Token tidak valid atau sesi telah berakhir."}
```

- **404 Not Found**, ID tidak ada atau milik pengguna lain (termasuk pengguna dengan role admin):

```json
{"status":"error","message":"Notifikasi tidak ditemukan."}
```

- **422 Unprocessable Entity**, filter tidak valid:

```json
{"status":"error","message":"The is read field must be true or false.","errors":{"is_read":["The is read field must be true or false."]}}
```

400/403 tidak digunakan pada operasi normal inbox; tidak ada body bisnis, permission modul, atau endpoint untuk membuat notifikasi lewat client. Pesan error mengikuti global handler/lokal aplikasi.

## Pemicu dan Operasional SPMB

| Event model | Type | Penerima / kunci deduplikasi |
|---|---|---|
| Pendaftaran menjadi submitted (termasuk manual/pembayaran) | application_submitted | Pemilik pendaftaran + ID + status |
| Keputusan verifikasi | application_verified / application_lulus_administrasi / application_gagal_administrasi | Pemilik + ID + status |
| Hasil dipublikasikan | result_published | Pemilik + ID hasil + status + prodi |
| VA aktif terbit/berubah | billing_va_issued | Pemilik + ID tagihan + nomor VA + kedaluwarsa |
| Tagihan berubah lunas | payment_paid | Pemilik + ID tagihan; pembayaran sebagian tidak memicu |
| Daftar ulang menunggu pembayaran / lunas | reenrollment_menunggu_pembayaran / reenrollment_lunas | Pemilik + ID pendaftaran + status |

Penerima calon adalah akun `pendaftaran.user_id`. Salinan admin dikirim ke akun aktif pada mapping `spmb_admin_notification_user_ids` di IAM → Pengaturan Sistem → Admin SPMB, dengan type `admin_{type_calon}`. Mapping kosong berarti tidak ada pengiriman admin. Pemilihan akun tidak bergantung pada nama role atau permission SPMB. Akun calon dikecualikan dari salinan admin untuk event pendaftarannya sendiri.

Pesan admin menyebut nama/nomor pendaftar, memakai judul berawalan `Admin SPMB:`, serta URL `/spmb/pendaftaran/{id}`. Seluruh pemicu pada tabel di atas mendukung salinan admin, termasuk pendaftaran manual dan callback pembayaran. Mapping tidak memberikan hak akses ke detail pendaftaran.

Penyimpanan ulang/retry tidak mengirim ulang inbox/email untuk keputusan yang sama kepada penerima yang sama. Status baca setiap penerima independen. Akun yang kemudian nonaktif/hilang dilewati dan perubahan mapping berlaku untuk event berikutnya. Tidak ada notifikasi pada hasil yang belum dipublikasikan. Hasil dengan tanggal masa depan memakai job tertunda, memeriksa ulang waktu publikasi serta mapping terkini saat dieksekusi. Pengaturan mapping dijelaskan pada [SystemSettingController](../IAM/SystemSettingController.md).

Inbox disimpan sinkron di transaksi event model; rollback juga membatalkan inbox. Email memakai `SystemInboxEmailNotification` dan diantrikan setelah commit. Jalankan worker queue sesuai [SERVER_SETUP.md](../../SERVER_SETUP.md), dengan queue `notifications` disertakan, misalnya `php artisan queue:work --queue=notifications,default`. Kegagalan antrean dicatat tanpa menggagalkan proses utama; kegagalan transport job mengikuti retry/failed jobs Laravel. Tidak memerlukan seeder: tabel inbox berisi data transaksi, relasi modul memakai master yang sudah tersedia.

Frontend bell menyatukan preview sistem dan LMS dengan ID sumber terpisah, menangani kegagalan masing-masing sumber, dan membuka inbox `/notifikasi`; `/lms/notifikasi` mempertahankan fungsi LMS. Tiap halaman memiliki navigasi sumber, pagination, filter, dan aksi baca.

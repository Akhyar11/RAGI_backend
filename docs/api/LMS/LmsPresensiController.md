# LmsPresensiController

> **Modul**: LMS / SIAKAD RAG
> **Base URL**: `/api/v1/lms`
> **Autentikasi**: Bearer token (Sanctum/Passport sesuai konfigurasi API)
> **Diperbarui**: 2026-11-09

Controller ini menyediakan monitoring keaktifan mengajar, rekap kehadiran, dan ekspor laporan dari tabel lokal RAG (`siakad_pertemuan`, `siakad_absensi_mahasiswa`, kelas, KRS, dan data dosen pengampu). RAG tidak mengirim atau menarik data ke/dari sistem presensi legacy. Data laporan hanya menjadi indikator monitoring, bukan input otomatis payroll.

Permission `lms.presensi.read` digunakan untuk membaca monitoring dan rekap. Permission `lms.presensi.manage` digunakan untuk export laporan.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/lms/admin/presensi/kelas` | Daftar kelas dengan ringkasan sesi dan presensi lokal | ✅ `lms.presensi.read` |
| GET | `/api/v1/lms/admin/presensi/rekap` | Rekap presensi per mahasiswa/pertemuan dari RAG | ✅ `lms.presensi.read` |
| GET | `/api/v1/lms/admin/presensi/keaktifan-dosen` | Monitoring pertemuan dan pengisian presensi dosen | ✅ `lms.presensi.read` |
| GET | `/api/v1/lms/admin/presensi/export` | Export rekap presensi lokal (CSV/XLSX) | ✅ `lms.presensi.manage` |
| GET | `/api/v1/lms/admin/presensi/keaktifan-dosen/export` | Export monitoring keaktifan dosen (CSV/XLSX) | ✅ `lms.presensi.manage` |

Endpoint integrasi legacy untuk status/log/sinkronisasi/tarik riwayat sudah tidak tersedia.

## Query Parameters

### `GET /admin/presensi/kelas`

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari kode atau nama kelas |
| `tahun_akademik_id` | integer | ❌ | — | Filter tahun akademik SIAKAD RAG |
| `sort_by` | string | ❌ | `kode_kelas` | `kode_kelas`, `nama_kelas`, `jumlah_sesi`, atau `created_at` |
| `sort_order` | string | ❌ | `desc` | `asc` atau `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Nomor halaman |

### `GET /admin/presensi/rekap` dan `/admin/presensi/export`

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari NIM, nama mahasiswa, atau materi pertemuan |
| `kelas_id` | integer | ❌ | — | Filter kelas lokal |
| `pertemuan_ke` | integer | ❌ | — | Filter nomor pertemuan |
| `status` | string | ❌ | — | Kode status dari Master Referensi SIAKAD bertipe `status_absensi` (termasuk status kustom aktif) |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100); tidak berlaku untuk export |
| `page` | integer | ❌ | `1` | Nomor halaman; tidak berlaku untuk export |
| `format` | string | ❌ | `csv` | `csv` atau `xlsx`; hanya untuk export |

### `GET /admin/presensi/keaktifan-dosen` dan `/admin/presensi/keaktifan-dosen/export`

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari kelas, mata kuliah, atau dosen pengampu |
| `program_studi_id` | integer | ❌ | — | Filter kelas pada program studi lokal |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100); tidak berlaku untuk export |
| `format` | string | ❌ | `csv` | `csv` atau `xlsx`; hanya untuk export |

Semua daftar dikembalikan dalam envelope `status`, `message`, `data`, dan `meta`. File export dicatat pada audit log.

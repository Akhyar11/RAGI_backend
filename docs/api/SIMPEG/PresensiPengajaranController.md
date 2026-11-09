# PresensiPengajaranController

> **Modul**: SIMPEG (monitoring sumber data LMS/SIAKAD)
> **Base URL**: `/api/simpeg`
> **Autentikasi**: Bearer token (Passport/Sanctum-compatible API guard)
> **Diperbarui**: 2026-11-09

Controller untuk memantau pertemuan pengajaran dosen berdasarkan relasi aktual SIAKAD `DosenPengampu → Dosen → User`, mengatur cut-off pengisian presensi per periode, dan mengelola izin khusus dosen setelah cut-off. Data ini hanya menjadi indikator/laporan dan tidak masuk ke perhitungan nominal gaji atau proses payroll.

## Permission

| Permission | Akses |
|---|---|
| `simpeg.presensi.pengajaran.read` | Lihat monitoring, cut-off, dan daftar izin |
| `simpeg.presensi.pengajaran.manage` | Atur cut-off, beri izin khusus, dan cabut izin |

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/simpeg/presensi/pengajaran` | Rekap pertemuan/presensi dosen pada satu bulan | ✅ `simpeg.presensi.pengajaran.read` |
| GET | `/api/simpeg/presensi/pengajaran/cutoff` | Baca cut-off periode | ✅ `simpeg.presensi.pengajaran.read` |
| PUT | `/api/simpeg/presensi/pengajaran/cutoff` | Atur atau ubah cut-off periode | ✅ `simpeg.presensi.pengajaran.manage` |
| GET | `/api/simpeg/presensi/pengajaran/exceptions` | Daftar izin khusus dosen pada periode | ✅ `simpeg.presensi.pengajaran.read` |
| POST | `/api/simpeg/presensi/pengajaran/exceptions` | Berikan izin khusus untuk dosen/periode sampai waktu tertentu | ✅ `simpeg.presensi.pengajaran.manage` |
| PATCH | `/api/simpeg/presensi/pengajaran/exceptions/{exception}/revoke` | Cabut izin khusus aktif dengan alasan | ✅ `simpeg.presensi.pengajaran.manage` |

## Query Parameters

### `GET /presensi/pengajaran`

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `periode` | string `YYYY-MM` | ✅ | — | Bulan laporan |
| `search` | string | ❌ | — | Nama dosen, NIDN, kelas, atau mata kuliah |
| `sort_by` | string | ❌ | `nama_lengkap` | `nama_lengkap` atau `nidn` |
| `sort_order` | string | ❌ | `asc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maksimum `100` |
| `page` | integer | ❌ | `1` | Nomor halaman |

### `GET /presensi/pengajaran/cutoff` dan `GET /presensi/pengajaran/exceptions`

Keduanya menerima `periode` wajib dengan format `YYYY-MM`. Daftar izin juga menerima `search`, `per_page` (maksimum 100), dan `page`.

## Request Body

### `PUT /presensi/pengajaran/cutoff`

```json
{
  "periode": "2026-11",
  "cutoff_at": "2026-11-30T23:59:00+07:00"
}
```

### `POST /presensi/pengajaran/exceptions`

```json
{
  "dosen_id": 42,
  "periode": "2026-11",
  "valid_until": "2026-12-03T23:59:00+07:00",
  "reason": "Dosen sedang bertugas di luar kampus dan meminta akses untuk melengkapi presensi."
}
```

Izin berlaku bagi seluruh pertemuan dosen terpilih pada periode tersebut sampai `valid_until`. Dosen harus memiliki akun SSO tertaut dan penugasan kelas/pertemuan tetap dibatasi pada pemetaan SIAKAD yang sah. Izin dicatat di audit log dan dapat dicabut.

### `PATCH /presensi/pengajaran/exceptions/{exception}/revoke`

```json
{
  "reason": "Persetujuan dibatalkan oleh Admin SIMPEG."
}
```

## Response Sukses

Semua endpoint mengembalikan envelope `status`, `message`, dan `data`. Endpoint daftar juga mengembalikan `meta` pagination. Contoh baris laporan:

```json
{
  "status": "success",
  "message": "Monitoring presensi pengajaran berhasil dimuat.",
  "data": [
    {
      "dosen_id": 42,
      "user_id": 81,
      "nama_dosen": "Dr. Contoh",
      "nidn": "0012345678",
      "kelas": [
        {
          "kelas_id": 12,
          "kode_kelas": "IF-A",
          "nama_kelas": "Informatika A",
          "mata_kuliah": "Pemrograman",
          "total_mahasiswa": 28,
          "pertemuans": [
            {
              "id": 205,
              "pertemuan_ke": 3,
              "tanggal": "2026-11-10",
              "status_pertemuan": "selesai",
              "total_absensi": 0,
              "total_mahasiswa": 28,
              "status_presensi": "belum_diisi"
            }
          ]
        }
      ],
      "total_pertemuan": 1,
      "pertemuan_terlaksana": 1,
      "presensi_terisi": 0,
      "presensi_belum_diisi": 1,
      "status_pemenuhan": "perlu_dilengkapi"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 1,
    "last_page": 1,
    "from": 1,
    "to": 1
  }
}
```

`total_absensi` hanya menghitung rekaman yang benar-benar disimpan. Pertemuan berstatus `terisi` jika seluruh peserta KRS aktif memiliki rekaman; `tanpa_peserta` berarti tidak ada peserta pada KRS. Pengingat dikirim selama roster belum lengkap dan cut-off periode belum tercapai.

Contoh cut-off sebelum diatur:

```json
{
  "status": "success",
  "message": "Pengaturan cut-off berhasil dimuat.",
  "data": {
    "periode": "2026-11",
    "cutoff_at": null,
    "is_closed": false
  }
}
```

## Aturan Penguncian

- Setelah waktu cut-off tercapai, penambahan/perubahan presensi untuk periode itu ditolak, termasuk input token mahasiswa, input manual dosen, dan persetujuan izin yang mengubah rekap. Data presensi dikelola lokal di RAG.
- Admin SIMPEG dapat memberi pengecualian untuk satu dosen dan satu periode dengan batas kedaluwarsa. Pengecualian tidak memperluas kelas yang boleh diakses dosen.
- Pengingat in-app dikirim harian untuk pertemuan berstatus selesai yang belum memiliki data absensi; pengingat berhenti saat terisi atau cut-off tercapai.
- Tanpa pengaturan cut-off untuk suatu periode, pengingat untuk periode tersebut tidak dikirim dan input presensi tidak ditutup.
- Tidak ada pengaruh terhadap perhitungan payroll.

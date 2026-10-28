# API Contract: Nomor SK SPMB melalui Arsip

## Keputusan

Nomor SK kelulusan SPMB hanya diterbitkan melalui alur permohonan dan persetujuan Arsip. SPMB tidak membuat nomor sendiri. Format nomor mengikuti generator Arsip:

`{nomor_urut}/{kode_klasifikasi}/{kode_unit}/{bulan_romawi}/{tahun}`

Konfigurasi template SPMB mengambil referensi modul, klasifikasi, dan unit melalui ID dari master. Admin mengonfigurasi template dengan record unit penerbit `INDO` yang tersedia di master; ID unit pada template menjadi sumber untuk permohonan. Arsip tidak boleh mengganti unit yang diminta ketika menyetujui permohonan. `format_nomor_surat` dipertahankan untuk kompatibilitas data lama, tetapi tidak lagi menentukan nomor SK.

## Endpoint

### `POST /api/spmb/pendaftaran/{id}/terbitkan-sk`

- Jika belum ada permohonan aktif, buat permohonan nomor melalui Arsip dan kembalikan `200 OK` dengan status `menunggu_verifikasi`. Belum ada nomor final atau PDF pada tahap ini.
- Jika permohonan telah disetujui Arsip, gunakan nomor resmi yang tersimpan di Arsip untuk membuat dan menyimpan PDF, lalu kembalikan `200 OK`.
- Jika template atau referensi master Arsip tidak valid, kembalikan `422 Unprocessable Entity` dan jangan membuat nomor atau PDF.
- Jika permohonan sebelumnya ditolak, pengiriman ulang endpoint ini membuat permohonan baru.
- Jika permohonan sudah disetujui tetapi nomor untuk unit yang diminta tidak ditemukan, endpoint mengembalikan `422` untuk rekonsiliasi oleh Arsip; SPMB tidak membuat permohonan pengganti otomatis.

### `GET /api/spmb/pendaftaran/{id}/sk-lulus`

- Hanya menyajikan PDF yang telah dibuat dengan nomor SK resmi Arsip.
- Selama permohonan belum disetujui atau nomor resmi belum tersedia, kembalikan `409 Conflict`; endpoint tidak membuat nomor internal atau PDF sementara.

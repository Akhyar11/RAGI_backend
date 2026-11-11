# API Contract: Nomor SK SPMB melalui Arsip

## Inbox Sistem dan Transisi SPMB (2026-11-10)

- Tabel `core_notifications` menyimpan notifikasi personal: `user_id` (FK core_users), `module_id` (FK nullable core_modules), `type`, `title`, `message`, `url`, `entity_type`, `entity_id`, `priority`, `is_read`, `read_at`, `dedup_key`, timestamps. Unique `(user_id, dedup_key)` mencegah duplikasi pada retry; ini data transaksi, bukan master.
- `GET /api/notifications` memerlukan `auth:api` dan hanya mengambil milik pengguna aktif. Filter: `module_id`, `is_read`, `search`, `sort_by` (created_at/read_at/priority), `sort_order` (asc/desc), `per_page` (default 15, maksimum 100), `page`. Respons envelope standar dengan `data[]`, `meta`, `filters`, dan relasi `module` (id/name/code/primary_color).
- `GET /api/notifications/unread-count` mengembalikan `data.unread_count`. `POST /api/notifications/{id}/read` mengembalikan notifikasi milik pengguna atau 404; timestamp baca tidak berubah pada pengulangan. `POST /api/notifications/read-all` hanya memperbarui notifikasi pengguna yang belum dibaca dan mengembalikan `data.updated`. Kedua POST tanpa body. Tidak ada permission modul yang diperlukan untuk inbox pribadi.
- Penerima SPMB selalu `pendaftaran.user_id`, termasuk pendaftaran manual; bukan admin pemroses/referrer/pihak ketiga. Pemicu: submitted, keputusan verifikasi, hasil yang dipublikasikan, VA aktif terbit/diperbarui, tagihan lunas, serta daftar ulang menunggu pembayaran/lunas. Perubahan data tanpa transisi tidak mengirim ulang. Tagihan sebagian/pending tidak mengirim notifikasi lunas.
- Inbox dicatat dalam transaksi pemicu. Email diantrikan setelah commit ke queue `notifications`; rollback tidak mengirim email. Kegagalan transport email dicatat tanpa membatalkan bisnis. Jalankan worker queue untuk pengiriman email. Hasil bertanggal masa depan dijadwalkan melalui job dan diperiksa ulang sebelum publikasi.
- Penyimpanan ulang hasil lulus pada prodi sama tidak menambah kuota dan mempertahankan status daftar ulang serta waktu publikasi. Perubahan prodi/status merekonsiliasi kuota dalam transaksi dengan lock pendaftaran dan kuota. Prodi tujuan penuh menghasilkan 422 tanpa mengubah hasil/kuota asal.
- Frontend bell mengambil feed sistem dan LMS secara independen, menjumlah unread, mengurutkan preview terbaru, dan menandai baca pada sumber yang tepat. Inbox `/notifikasi` untuk sistem; inbox `/lms/notifikasi` tetap tersedia dengan navigasi antar-inbox. Kegagalan satu sumber tidak menghapus sumber lain.

## Keputusan

Nomor SK kelulusan SPMB hanya diterbitkan melalui alur permohonan dan persetujuan Arsip. SPMB tidak membuat nomor sendiri. Format nomor mengikuti generator Arsip:

`{nomor_urut}/{kode_klasifikasi}/{kode_unit}/{bulan_romawi}/{tahun}`

Konfigurasi template SPMB mengambil referensi modul, klasifikasi, dan unit melalui ID dari master. Admin mengonfigurasi template dengan record unit penerbit `INDO` yang tersedia di master; ID unit pada template menjadi sumber untuk permohonan. Arsip tidak boleh mengganti unit yang diminta ketika menyetujui permohonan. `format_nomor_surat` dipertahankan untuk kompatibilitas data lama, tetapi tidak lagi menentukan nomor SK.

## Konfigurasi Role Pihak Ketiga SPMB

Pengaturan IAM menyimpan `third_party_registration_role` sebagai slug role akun pihak ketiga. Akun yang dibuat dari formulir manual mendapat role ini secara otomatis. Dropdown pihak ketiga hanya mengembalikan akun aktif dengan role tersebut. POST manual menerima `pihak_ketiga_user_ids[]` dan menautkan akun terpilih ke pendaftaran; nama lama tetap disimpan sebagai snapshot.

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

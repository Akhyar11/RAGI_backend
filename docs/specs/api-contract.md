# API Contract: Nomor SK SPMB melalui Arsip

## Kanal pembayaran dinamis komponen biaya SPMB (2026-11-11)

- `spmb_master_komponen_biaya.payment_gateway_config_id` adalah FK nullable ke `sikeu_payment_gateway_configs`. API CRUD komponen menerima ID konfigurasi yang aktif dan didukung. Nilai null mengikuti kanal default SIKEU pada penerbitan baru; jika default belum aktif/didukung, penerbitan ditolak 422 tanpa VA fiktif. Tagihan lama tetap mempertahankan VA yang tersimpan. Opsi berasal dari database; credential tidak dikembalikan.
- `GET /api/spmb/master/payment-gateway-options` memerlukan `spmb.manage`, menyediakan pagination `page/per_page` (15, maksimum 100), `search`, `sort_by` (gateway_name/id), `sort_order`, dan metadata aman `id/gateway_name/environment/is_active`. Gateway yang didukung adalah adapter Xendit, Midtrans, dan H2H bank yang tersedia di sistem.
- Konfigurasi gateway dapat aktif bersamaan; `is_default` menentukan pilihan untuk pemanggil lama yang tidak memilih kanal. Pemilihan default SPMB hanya mempertimbangkan kanal yang didukung (`midtrans`, `xendit`, `bsn_h2h`), sehingga default global modul lain tidak pernah terpilih sebagai kanal SPMB. Memilih kanal SPMB tidak mengubah default modul lain. Kredensial tetap dikelola di SIKEU dengan `sikeu.paymentgateway.manage`.
- Tagihan menyimpan `payment_gateway_config_id`, `payment_gateway_name`, dan `payment_gateway_environment` ketika diterbitkan. Perubahan master komponen berlaku untuk tagihan berikutnya. Provider/environment tagihan berjalan tidak dialihkan diam-diam ketika konfigurasi berubah atau nonaktif.
- Komponen dikelompokkan per gateway pada tahap pendaftaran/daftar ulang. Potongan dialokasikan sekali dengan batas nilai komponen; total antar-tagihan sama dengan total beban sesudah potongan. Biaya gateway dihitung memakai konfigurasi kanal masing-masing. Penerbitan ulang tahap yang sama menggunakan tagihan yang sudah ada.
- Respons lama `tagihan/virtual_account` tetap tersedia untuk kompatibilitas, ditambah `tagihan_list[]` yang memuat seluruh tagihan. Halaman pembayaran baru mengambil daftar lengkap dan tidak menyimpulkan lunas dari satu tagihan saja.
- `GET /api/spmb/pembayaran` (Bearer Passport) hanya menampilkan tagihan milik calon yang login; petugas dengan `spmb.manage`/`spmb.pendaftaran.read` boleh membuka endpoint, sedangkan pengguna tanpa pendaftaran dan tanpa permission menerima 403. Filter `tahap=pendaftaran|daftar_ulang`, pencarian, sorting, dan pagination standar memakai envelope `status/message/data/meta` tanpa key `filters` di root. Data mencakup rincian nominal/potongan/sisa, kanal, status, dan informasi pembayaran yang aman.
- `POST /api/spmb/pembayaran/{id}/checkout` hanya untuk pemilik tagihan atau petugas berizin `sikeu.paymentgateway.manage`/`sikeu.pembayaran.create`. Tidak menerima nominal/gateway dari browser. Midtrans menghasilkan token/redirect Snap; Xendit menghasilkan URL invoice; H2H menghasilkan billing/CUSTID dan instruksi bank. Tagihan lama tanpa mapping dapat menampilkan VA lama. Tagihan lunas/nonaktif/kanal belum siap menghasilkan 422, milik pengguna lain 403, tidak ditemukan 404, gangguan provider 502.
- Checkout online dicatat di `sikeu_payment_checkouts` (order/provider/nominal/environment/status dan URL/token checkout), digunakan ulang selama masih valid. Callback mencocokkan order, provider, nominal, dan tanda tangan/token server; callback tertunda/kedaluwarsa tidak melunasi tagihan, retry sukses tidak menggandakan kas/jurnal/notifikasi. Tidak ada fallback VA fiktif untuk kanal yang dipilih eksplisit.
- Callback Midtrans/Xendit serta sinkronisasi H2H memperbarui tagihan yang tepat. Agregasi status pendaftaran/daftar ulang memeriksa seluruh tagihan tahap terkait. Penyelesaian satu tahap tidak melunasi tahap lain. Konversi mahasiswa dan notifikasi tetap mengikuti status agregat; seluruh pengiriman email setelah commit.
- Frontend komponen biaya memakai AsyncSelect yang mengambil opsi API. Registrasi dan daftar ulang membuka `/spmb/pembayaran?tahap=...`; halaman tersebut menampilkan tiap tagihan dan menjalankan checkout sesuai respons backend. Browser tidak dapat mensimulasikan pembayaran untuk tagihan dengan kanal eksplisit.

## Inbox Sistem dan Transisi SPMB (2026-11-10)

### Mapping penerima admin SPMB melalui IAM (2026-11-11)

- IAM → Pengaturan Sistem menyediakan mapping beberapa akun penerima admin SPMB. Sumber penerima adalah daftar ID akun yang dipilih admin IAM, tanpa pencarian akun melalui ID tetap, email, nama role, atau inferensi permission SPMB.
- Key `spmb_admin_notification_user_ids` pada `core_system_settings` menyimpan JSON array ID integer positif dari `core_users`. Default `[]` menonaktifkan pengiriman admin. Mapping tidak memberikan hak akses modul kepada akun terpilih.
- `GET /api/admin/system-settings` menyertakan record key tersebut dengan `value` JSON string serta `users[]` berisi `id`, `name`, `username`, `email`, dan `is_active` untuk menampilkan pilihan tersimpan, termasuk akun yang kemudian nonaktif.
- `POST /api/admin/system-settings` menerima key tersebut melalui envelope `settings[]` yang sudah ada. Server menolak JSON bukan array, ID tidak valid/tidak ditemukan/nonaktif, atau lebih dari 100 entri dengan 422 sebelum menulis pengaturan. ID ganda dinormalisasi. `[]` menghapus seluruh penerima. Perubahan mapping dicatat pada audit log.
- Pembacaan/perubahan pengaturan dan `GET /api/admin/system-settings/spmb-admin-options` memerlukan autentikasi serta kewenangan IAM `iam.settings.update`. Endpoint opsi hanya menampilkan akun aktif, mendukung pencarian nama/username/email, `page`, `per_page` (default 15, maksimum 100), `sort_by` (name/username/email/id), dan `sort_order` (asc/desc).
- Admin yang dipetakan menerima inbox dan email untuk transisi SPMB yang sama dengan calon: submit, verifikasi, publikasi hasil, penerbitan VA, pelunasan, dan daftar ulang. Pesan admin menyebut nama/nomor pendaftar dengan URL `/spmb/pendaftaran/{id}`, type `admin_{type_calon}`, dan kunci deduplikasi per pengguna/event.
- Penerima nonaktif/hilang dilewati pada pengiriman. Akun calon pemilik pendaftaran dikecualikan dari salinan admin untuk event miliknya sendiri. Perubahan mapping berlaku untuk event selanjutnya; tidak mengirim ulang riwayat. Notifikasi calon tetap berjalan ketika mapping kosong atau rusak.
- Email admin memakai antrean `notifications` setelah transaksi berhasil. Rollback tidak meninggalkan inbox/email admin; kegagalan antrean tidak membatalkan proses utama.

- Tabel `core_notifications` menyimpan notifikasi personal: `user_id` (FK core_users), `module_id` (FK nullable core_modules), `type`, `title`, `message`, `url`, `entity_type`, `entity_id`, `priority`, `is_read`, `read_at`, `dedup_key`, timestamps. Unique `(user_id, dedup_key)` mencegah duplikasi pada retry; ini data transaksi, bukan master.
- `GET /api/notifications` memerlukan `auth:api` dan hanya mengambil milik pengguna aktif. Filter: `module_id`, `is_read`, `search`, `sort_by` (created_at/read_at/priority), `sort_order` (asc/desc), `per_page` (default 15, maksimum 100), `page`. Respons envelope standar dengan `data[]`, `meta`, `filters`, dan relasi `module` (id/name/code/primary_color).
- `GET /api/notifications/unread-count` mengembalikan `data.unread_count`. `POST /api/notifications/{id}/read` mengembalikan notifikasi milik pengguna atau 404; timestamp baca tidak berubah pada pengulangan. `POST /api/notifications/read-all` hanya memperbarui notifikasi pengguna yang belum dibaca dan mengembalikan `data.updated`. Kedua POST tanpa body. Tidak ada permission modul yang diperlukan untuk inbox pribadi.
- Penerima calon SPMB adalah `pendaftaran.user_id`, termasuk pendaftaran manual. Penerima admin ditetapkan melalui mapping IAM di atas. Pemicu: submitted, keputusan verifikasi, hasil yang dipublikasikan, VA aktif terbit/diperbarui, tagihan lunas, serta daftar ulang menunggu pembayaran/lunas. Perubahan data tanpa transisi tidak mengirim ulang. Tagihan sebagian/pending tidak mengirim notifikasi lunas.
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

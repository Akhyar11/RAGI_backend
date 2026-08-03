# Implementation Plan — Pengembangan Modul SIKEU (Sistem Informasi Keuangan + Akuntansi & Pajak Lengkap)

Mengembangkan modul **SIKEU** (Sistem Informasi Keuangan) secara komprehensif dan audit-ready sesuai spesifikasi ERD Part 2, alur proses bisnis kampus, integrasi API Tagihan Eksternal, Fitur Dispensasi Pembayaran, integrasi Payment Gateway (Xendit/Duitku), pengecekan status perizinan KRS, pengelolaan kas unit/petty cash, **Integrasi Arus Kas Penerimaan (UKT & Hibah SIPPM)**, **Modul Akuntansi Keuangan Penuh** (*Chart of Accounts*, *Jurnal Otomatis & Manual*, *Jurnal Penyesuaian/Penutupan*, *Posting Control*, *Penguncian Periode Akuntansi*, *Buku Besar*, *Neraca Saldo*, serta *4 Laporan Keuangan Utama*), **Pelaporan Pajak (PPh 21/23/PPN)**, serta **Unggah Bukti Pelaksanaan / LPJ Realisasi**. Seluruh antarmuka Frontend mematuhi **`crud-ui-standard`** dan **Sidebar RBAC Dinamis**.

---

## User Review Required

> [!IMPORTANT]
> **Kelengkapan Modul Akuntansi Keuangan (Accounting Suite Checklist)**:
> 1. **Master Akun (COA / Chart of Accounts)**: 5 Kelompok Utama (*1-Aset*, *2-Liabilitas*, *3-Ekuitas*, *4-Pendapatan*, *5-Beban*) dengan klasifikasi akun lancar, tetap, operasional, & pajak.
> 2. **Auto-Journal Feed**: Jurnal otomatis berpasangan (Debet & Kredit) dari Pembayaran UKT Mahasiswa, Pemasukan Hibah (SIPPM), Gaji Pegawai/Payroll (SIMPEG), Pencairan Kas Unit, Pengeluaran Kampus, dan Penyetoran Pajak.
> 3. **Manual, Adjustment & Closing Journal**: Dukungan entry Jurnal Umum Manual, Jurnal Penyesuaian (*Adjustment Journal*), dan Jurnal Penutupan (*Closing Journal*).
> 4. **Posting Control & Periode Akuntansi (`periode_akuntansi`)**: Bagian Keuangan memiliki wewenang meninjau draf jurnal (*Draft -> Posted*) serta menutup periode akuntansi bulanan/tahunan agar transaksi tidak dapat diubah kembali (*Closed Period Guard*).
> 5. **Buku Besar (General Ledger) & Neraca Saldo (Trial Balance)**: Monitoring mutasi dan saldo berjalan per kode akun COA.
> 6. **4 Laporan Keuangan Standar Akuntansi**:
>    - **Laporan Laba Rugi / Aktivitas** (*Income Statement / Statement of Activities*)
>    - **Neraca / Posisi Keuangan** (*Balance Sheet / Statement of Financial Position*)
>    - **Laporan Arus Kas** (*Cash Flow Statement - Operasional, Investasi, Pendanaan*)
>    - **Laporan Perubahan Ekuitas / Ekuitas Dana** (*Statement of Changes in Equity*)

> [!IMPORTANT]
> **Database Strategy Warning**:
> Sesuai arahan utama, migrasi dan seeding **WAJIB** menggunakan `php artisan migrate` dan `php artisan db:seed`, dan **TIDAK BOLEH** menggunakan `php artisan migrate:fresh` agar data database yang sudah ada di lingkungan workspace tidak terhapus.

> [!IMPORTANT]
> **Form Rules Compliance (`crud-ui-standard`)**:
> - Form dengan **> 5 input** (misal: *Generate Tagihan*, *Catat Pemasukan Hibah*, *Pengajuan Pencairan Kas*, *Input Transaksi Manual Pengeluaran Kampus*, *Entry Jurnal Manual / Penyesuaian*) dibuat pada **Halaman Terpisah** dengan layout **Grid Maksimal 3 Kolom** (`grid-cols-1 md:grid-cols-2 lg:grid-cols-3`) dan dilengkapi **Tombol Kembali (Back Button)**.
> - Form pendek (**<= 5 input**) (misal: *Jenis Biaya*, *Tarif UKT*, *Beasiswa*, *Unit Kas*, *Kode Akun COA*, *Buka/Tutup Periode Akuntansi*) menggunakan **Modal Pop-up** dengan layout **Grid 2 Kolom** (`grid-cols-1 md:grid-cols-2`).

---

## Proposed Changes

### Backend Architecture & Database (Laravel)

#### [NEW] [2026_08_01_000001_create_sikeu_tables.php](file:///Users/it/Project/RAG/backend/database/migrations/2026_08_01_000001_create_sikeu_tables.php)
Tabel-tabel dasar SIKEU sesuai ERD Part 2 + Akuntansi Lengkap + Kas Unit + Pajak + LPJ + Approval & Dispensasi:
1. `jenis_biaya` (id, kode, nama, tipe, deskripsi, is_recurring, is_active)
2. `tarif_ukt` (id, program_studi_id, jenis_biaya_id, tahun_akademik_id, kelompok_ukt, nominal, is_active)
3. `beasiswa` (id, kode, nama, sumber, tipe_potongan, nilai_potongan, deskripsi, is_active)
4. `mahasiswa_beasiswa` (id, mahasiswa_id, beasiswa_id, tahun_akademik_id, berlaku_mulai, berlaku_sampai, status, ditetapkan_oleh, file_sk)
5. `tagihan_mahasiswa` (id, mahasiswa_id, tahun_akademik_id, nomor_tagihan, total_tagihan, total_potongan, total_denda, total_bayar, status, requires_approval, status_approval, disetujui_oleh, tanggal_approval, catatan_approval, source_system, jatuh_tempo)
6. `detail_tagihan` (id, tagihan_id, jenis_biaya_id, nominal, potongan, nominal_bersih, keterangan)
7. `potongan_tagihan` (id, tagihan_id, beasiswa_id, tipe, nominal_potongan, keterangan, diinput_oleh)
8. `denda_tagihan` (id, tagihan_id, tipe_denda, nominal_denda, tanggal_denda, keterangan)
9. `dispensasi_tagihan` (id, tagihan_id, mahasiswa_id, tipe_dispensasi, jatuh_tempo_baru, jumlah_cicilan, nominal_per_cicilan, alasan, dokumen_pendukung, status, diajukan_oleh, disetujui_oleh, tanggal_persetujuan, catatan_pimpinan)
10. `virtual_account` (id, tagihan_id, va_number, bank_kode, bank_nama, nominal, expired_at, status)
11. `pembayaran` (id, tagihan_id, virtual_account_id, kode_transaksi, jumlah_bayar, waktu_bayar, channel_bayar, bank_pengirim, status, diverifikasi_oleh)
12. `callback_payment_gateway` (id, order_id, payment_type, raw_payload, status, pembayaran_id, received_at, processed_at)
13. `rekonsiliasi_pembayaran` (id, tanggal_rekonsiliasi, bank_kode, total_transaksi, total_nominal_sistem, total_nominal_bank, selisih, status, file_laporan_bank, diproses_oleh)
14. `pemasukan_kampus` (id, nomor_transaksi, sumber_pemasukan [hibah_sippm, donatur, kerjasama, pendapatan_lainnya], unit_kas_id, akun_pendapatan_id, nominal, tanggal_terima, nama_donor_instansi, nomor_kontrak_ref, file_bukti_transfer, keterangan, created_by)
15. `unit_kas` (id, unit_kerja_id, nama_kas, saldo_awal, saldo_saat_ini, penanggung_jawab_id, deskripsi, status)
16. `pengajuan_pencairan_kas` (id, nomor_pengajuan, unit_kerja_id, unit_kas_id, pemohon_id, judul_pengajuan, deskripsi, nominal_diajukan, nominal_disetujui, jenis_pengajuan, file_lampiran, status, approved_pimpinan_by, approved_pimpinan_at, approved_keuangan_by, approved_keuangan_at)
17. `transaksi_kas_unit` (id, unit_kas_id, pengajuan_pencairan_id, kode_transaksi, jenis_transaksi [debet_pemasukan, kredit_pengeluaran], nominal, saldo_sebelum, saldo_sesudah, keterangan, tanggal_transaksi, created_by)
18. `approval_history_pencairan` (id, pengajuan_id, user_id, role_approver, status_action, catatan)
19. `akun_keuangan` (id, kode_akun, nama_akun, kelompok [aset, liabilitas, ekuitas, pendapatan, beban], saldo_normal [debet, kredit], is_active)
20. **[NEW AKUNTANSI]** `periode_akuntansi` (id, nama_periode, tahun, bulan, tanggal_mulai, tanggal_selesai, status [terbuka, ditutup], ditutup_oleh, ditutup_pada)
21. `jurnal_umum` (id, nomor_jurnal, tanggal_jurnal, periode_id, jenis_sumber [pembayaran_mahasiswa, pemasukan_hibah, pencairan_kas, pengeluaran_manual, penyesuaian, penutupan], referensi_id, keterangan, status_posting [draft, posted], total_debet, total_kredit, created_by, posted_by, posted_at)
22. `detail_jurnal_umum` (id, jurnal_id, akun_id, debet, kredit, keterangan)
23. `pengeluaran_kampus` (id, nomor_transaksi, kategori, akun_beban_id, akun_kas_id, nominal, keterangan, tanggal_transaksi, nama_vendor, npwp_vendor, jenis_pajak [tanpa_pajak, pph_21, pph_23, ppn_11], tarif_pajak_persen, nominal_pajak, net_dibayarkan, status_pembayaran, file_bukti_bayar, created_by)
24. `laporan_bukti_pelaksanaan` (id, sumber_tipe [pengajuan_pencairan, pengeluaran_kampus], sumber_id, nomor_bukti, tanggal_pelaksanaan, total_realisasi, file_nota_kuitansi, rincian_keterangan, status_verifikasi [pending, disetujui, ditolak], diverifikasi_oleh, catatan_verifikasi)

#### [NEW] Eloquent Models di [app/Models/Sikeu](file:///Users/it/Project/RAG/backend/app/Models/Sikeu)
- `JenisBiaya.php`, `TarifUkt.php`, `Beasiswa.php`, `MahasiswaBeasiswa.php`
- `TagihanMahasiswa.php`, `DetailTagihan.php`, `PotonganTagihan.php`, `DendaTagihan.php`, `DispensasiTagihan.php`
- `VirtualAccount.php`, `Pembayaran.php`, `CallbackPaymentGateway.php`, `RekonsiliasiPembayaran.php`
- `PemasukanKampus.php`, `UnitKas.php`, `PengajuanPencairanKas.php`, `TransaksiKasUnit.php`, `ApprovalHistoryPencairan.php`
- `AkunKeuangan.php`, `PeriodeAkuntansi.php`, `JurnalUmum.php`, `DetailJurnalUmum.php`, `PengeluaranKampus.php`, `LaporanBuktiPelaksanaan.php`

#### [NEW] Seeders di [database/seeders/Sikeu](file:///Users/it/Project/RAG/backend/database/seeders/Sikeu)
- `SikeuAkuntansiSeeder.php` (Seed Chart of Accounts Kampus LENGKAP: Aset Lancar, Kas, Bank, Piutang UKT, Aset Tetap, Liabilitas Lancar, Utang Pajak PPh/PPN, Ekuitas Dana, Pendapatan SPP/UKT, Pendapatan Hibah SIPPM, Beban Gaji/SIMPEG, Beban Operasional)
- `SikeuMasterSeeder.php` (Seed Jenis Biaya, Tarif UKT, Beasiswa, Unit Kas Utama & Fakultas, Periode Akuntansi Aktif)
- `SikeuSampleDataSeeder.php` (Seed Sample Transaksi, Jurnal Auto-Sync, Adjustment Journal, Posted State, Pelaporan Pajak).
- Update [database/seeders/IAM/RoleSeeder.php](file:///Users/it/Project/RAG/backend/database/seeders/IAM/RoleSeeder.php) (Role `pimpinan`, `staf_keuangan`, `operator_sikeu`).
- Update [database/seeders/IAM/ModuleSeeder.php](file:///Users/it/Project/RAG/backend/database/seeders/IAM/ModuleSeeder.php) (Modul `sikeu`)
- Update [database/seeders/IAM/PermissionSeeder.php](file:///Users/it/Project/RAG/backend/database/seeders/IAM/PermissionSeeder.php) (Permissions `sikeu.*`, `sikeu.akuntansi.*`, `sikeu.akuntansi.posting`, `sikeu.akuntansi.closing`, `sikeu.laporan.*`)
- Update [database/seeders/IAM/MenuSeeder.php](file:///Users/it/Project/RAG/backend/database/seeders/IAM/MenuSeeder.php) (Registrasi Menu Dinamis SIKEU dengan URL `#sikeu`, `#layanan_sikeu`, `#kas_unit`, `#akuntansi_pajak`, `#laporan_keuangan`, `#master_sikeu`)

#### [NEW] Controllers di [app/Http/Controllers/Sikeu](file:///Users/it/Project/RAG/backend/app/Http/Controllers/Sikeu)
- `SikeuDashboardController.php`: Metrics ringkasan penerimaan, pengeluaran, saldo kas, surplus/defisit, pajak terutang, & status KRS.
- `AkuntansiController.php`: COA management, list & entry Jurnal Umum / Penyesuaian, Posting Control (`postJurnal`), Buku Besar (*General Ledger*), Neraca Saldo (*Trial Balance*).
- `PeriodeAkuntansiController.php`: Pengelolaan & penutupan periode akuntansi (`closePeriod`).
- `LaporanKeuanganController.php`: Generator 4 Laporan Keuangan (Laba Rugi, Neraca, Arus Kas, Perubahan Ekuitas).
- `PemasukanKampusController.php`, `TagihanController.php`, `ExternalTagihanController.php`, `DispensasiTagihanController.php`, `TagihanApprovalController.php`, `PembayaranController.php`, `PaymentGatewayController.php`, `UnitKasController.php`, `PengajuanPencairanController.php`, `PengeluaranKampusController.php`, `BuktiPelaksanaanController.php`, `PajakController.php`.

#### [MODIFY] [routes/api.php](file:///Users/it/Project/RAG/backend/routes/api.php)
Tambahkan endpoint SIKEU lengkap under prefix `/api/v1/sikeu` dan `/api/sikeu`.

---

### Frontend Implementation (Next.js 15)

#### [NEW] Pages di [frontend/app/(main)/sikeu](file:///Users/it/Project/RAG/frontend/app/\(main\)/sikeu)
1. `page.tsx`: **Dashboard SIKEU & Executive Financial Overview**.
2. `pemasukan/page.tsx`: **Daftar Pemasukan Kampus & Hibah**.
3. `pemasukan/create/page.tsx`: **Form Input Pemasukan Hibah/Kerjasama** (> 5 input, Grid 3 Kolom + Back Button).
4. `tagihan/page.tsx`: **Daftar Tagihan Mahasiswa**.
5. `tagihan/create/page.tsx`: **Form Generate Tagihan** (> 5 input, Grid 3 Kolom + Back Button).
6. `pembayaran/page.tsx`: **Riwayat Pembayaran & Virtual Account**.
7. `dispensasi/page.tsx`: **Portal Pengajuan & Status Dispensasi Pembayaran**.
8. `approval/page.tsx`: **Portal Approval Pimpinan**.
9. `krs-status/page.tsx`: **Portal Cek Status Perizinan KRS**.
10. `unit-kas/page.tsx`: **Kelola Kas Unit (Petty Cash)** (Modal <= 5 input Grid 2 Kolom).
11. `pencairan/page.tsx`: **Daftar Pengajuan Pencairan Dana Kas**.
12. `pencairan/create/page.tsx`: **Form Pengajuan Pencairan Dana** (> 5 input, Grid 3 Kolom + Back Button).
13. `pencairan/[id]/approval/page.tsx`: **Detail & Halaman Approval Pencairan**.
14. `pengeluaran/page.tsx`: **Daftar Pengeluaran Manual Kampus**.
15. `pengeluaran/create/page.tsx`: **Form Input Pengeluaran Keperluan Kampus** (> 5 input, Grid 3 Kolom + Back Button).
16. `bukti-pelaksanaan/page.tsx`: **Modul Upload & Verifikasi Bukti LPJ / Kuitansi**.
17. `akuntansi/coa/page.tsx`: **Chart of Accounts (COA / Master Akun Keuangan)** (Modal <= 5 Input Grid 2 Kolom).
18. **[NEW]** `akuntansi/periode/page.tsx`: **Pengelolaan & Penutupan Periode Akuntansi** (Modal <= 5 Input Grid 2 Kolom).
19. `akuntansi/jurnal/page.tsx`: **Jurnal Umum, Penyesuaian, & Posting Control Feed** (Tombol Review & Post ke Buku Besar).
20. `akuntansi/jurnal/create/page.tsx`: **Form Entry Jurnal Manual / Penyesuaian** (> 5 input, Grid 3 Kolom + Dynamic Debet/Kredit Lines + Back Button).
21. **[NEW]** `akuntansi/buku-besar/page.tsx`: **Buku Besar (General Ledger) & Neraca Saldo**.
22. **[NEW]** `akuntansi/laporan/page.tsx`: **Portal 4 Laporan Keuangan** (Tab: Laba/Rugi, Neraca, Arus Kas, Perubahan Ekuitas + Export PDF/Excel).
23. `pajak/page.tsx`: **Laporan & Rekapitulasi Pajak Kampus** (PPh 21, PPh 23, PPN 11%).
24. `master/page.tsx`: **Master Biaya & Beasiswa**.

---

## Verification Plan

### Automated Verification
1. Running Laravel Database Migration & Seeder tanpa `migrate:fresh`:
   ```bash
   cd /Users/it/Project/RAG/backend && php artisan migrate
   cd /Users/it/Project/RAG/backend && php artisan db:seed --class=Database\Seeders\IAM\RoleSeeder
   cd /Users/it/Project/RAG/backend && php artisan db:seed --class=Database\Seeders\IAM\PermissionSeeder
   cd /Users/it/Project/RAG/backend && php artisan db:seed --class=Database\Seeders\Sikeu\SikeuMasterSeeder
   cd /Users/it/Project/RAG/backend && php artisan db:seed --class=Database\Seeders\Sikeu\SikeuAkuntansiSeeder
   ```
2. Running Route List check:
   ```bash
   cd /Users/it/Project/RAG/backend && php artisan route:list --path=api/v1/sikeu
   ```

### Manual Verification
1. Tes posting Jurnal dan verifikasi di Buku Besar (`/sikeu/akuntansi/buku-besar`).
2. Tes penjurnalan otomatis saat Pembayaran UKT, Pemasukan Hibah, dan Pengeluaran Kampus.
3. Tes penutupan periode akuntansi (`/sikeu/akuntansi/periode`) dan verifikasi pembatasan edit/post transaksi lama.
4. Tes unduh/buka 4 Laporan Keuangan Utama (`/sikeu/akuntansi/laporan`).

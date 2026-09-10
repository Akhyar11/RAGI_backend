# Panduan Operasional SIKEU (Sistem Informasi Keuangan Kampus)

> **Versi**: 1.0  
> **Terakhir Diperbarui**: 10 September 2026  
> **Modul**: SIKEU — Sistem Informasi Keuangan, Akuntansi & Pajak  
> **Proyek**: RAG (Sistem Informasi Terpadu Kampus)

---

## 1. Daftar Akun Test & Cara Login

Gunakan akun berikut untuk mengakses modul SIKEU saat pengujian:

| Email | Username | Password | Role | Akses Utama |
|---|---|---|---|---|
| `kasir.sikeu@kampus.ac.id` | `kasir_sikeu` | `password` | Operator SIKEU | Master, Tagihan, Pembayaran, Piutang, Dispensasi |
| `kabag.keuangan@kampus.ac.id` | `kabag_keuangan` | `password` | Kabag Keuangan | Semua menu + Kas Utama + Tutup Buku |
| `pimpinan@kampus.ac.id` | `pimpinan` | `password` | Pimpinan | Approval Tagihan & Dispensasi |
| `mahasiswa.test@kampus.ac.id` | `mahasiswa_test` | `password` | Mahasiswa | Portal Tagihan Mandiri + Cetak Invoice |
| `superadmin@kampus.ac.id` | `superadmin` | `password` | Super Admin | Seluruh akses tanpa batas |

**Cara Login**:
1. Buka halaman SSO Campus: `http://localhost:3000`
2. Masukkan email/username + password
3. Setelah login, navigasi ke menu **SIKEU** di sidebar

---

## 2. Panduan Setting Master Biaya

Master Biaya mendefinisikan **jenis-jenis komponen biaya** yang berlaku di seluruh sistem (SPP, Praktikum, Wisuda, dll.).

### Langkah-langkah:
1. Buka **`/sikeu/master`** → Klik tab **"Jenis Biaya"**
2. Klik **"+ Tambah Jenis Biaya"**
3. Isi formulir:
   - **Kode** (unik, contoh: `UKT_REG`, `PRAK_TI`, `WISUDA_FEE`)
   - **Nama** (contoh: "Uang Kuliah Tunggal Reguler")
   - **Tipe**: `spp`, `sks`, `praktikum`, `wisuda`, `spmb_adm`, `lainnya`
   - **Nominal Standar** (opsional, nilai default)
   - **Delegasi Modul**: Centang modul mana saja yang menggunakan biaya ini (SIKEU, SIAKAD, SPMB)
4. Klik **"Simpan"**

> ⚠️ **Penting**: Master biaya yang sudah digunakan dalam tagihan **tidak dapat dihapus**. Anda hanya bisa menonaktifkannya.

---

## 3. Panduan Setting Tarif per Angkatan/Prodi/Semester

Setting Tarif menentukan **berapa nominal biaya** yang dikenakan untuk kombinasi tertentu (Angkatan + Prodi + Semester + Jalur Kelas).

### Langkah-langkah:
1. Buka **`/sikeu/master`** → Klik tab **"Setting Tarif"**
2. Klik **"+ Tambah Setting Tarif"**
3. Isi formulir:
   - **Jenis Biaya**: Pilih dari master biaya (contoh: UKT Reguler)
   - **Tahun Angkatan**: `2025`
   - **Program Studi**: Teknik Informatika (opsional, kosongkan = berlaku semua prodi)
   - **Semester**: `3` (opsional, kosongkan = berlaku semua semester)
   - **Jalur Kelas**: `Reguler` / `Karyawan` / `Internasional`
   - **Nominal**: `3.500.000`
   - **Keterangan**: (opsional)
4. Klik **"Simpan"**

### Contoh Konfigurasi:

| Jenis Biaya | Angkatan | Prodi | Semester | Jalur | Nominal |
|---|---|---|---|---|---|
| UKT Reguler | 2025 | Teknik Informatika | - | Reguler | Rp 3.500.000 |
| UKT Reguler | 2025 | Teknik Informatika | - | Karyawan | Rp 5.500.000 |
| Praktikum | 2025 | Teknik Informatika | 3 | Reguler | Rp 750.000 |
| UKT Reguler | 2024 | Manajemen | - | Reguler | Rp 3.000.000 |

> ⚠️ **Kombinasi unik**: Satu jenis biaya + angkatan + prodi + semester + jalur hanya boleh ada satu record. Sistem akan menolak duplikat.

---

## 4. Panduan Generate Tagihan Masal

Tagihan masal digunakan untuk menerbitkan tagihan semester sekaligus untuk seluruh mahasiswa dalam satu angkatan/jalur.

### Prasyarat:
- ✅ Setting Tarif sudah dikonfigurasi untuk angkatan & jalur yang ditargetkan
- ✅ Tipe Tagihan Mahasiswa sudah ditetapkan (tab "Student Types" di Master)

### Langkah-langkah:
1. Buka **`/sikeu/tagihan`**
2. Klik tombol **"Aktifkan Tagihan Masal"** (ikon Sparkles ✨)
3. Isi formulir:
   - **Target Angkatan**: `2025`
   - **Target Jalur Kelas**: `Reguler`
   - **Semester Aktif**: `Semester Ganjil 2026/2027`
   - **Batas Jatuh Tempo**: `2026-08-31`
4. Klik **"Terbitkan Tagihan Masal"**
5. Sistem akan:
   - Mengambil semua setting tarif yang cocok
   - Membuat tagihan (`sikeu_tagihan_mahasiswa`) + detail per komponen
   - Menampilkan jumlah tagihan yang ter-generate

### Catatan:
- Jika mahasiswa **sudah memiliki tagihan** dengan nomor yang sama, sistem akan melewatinya (tidak duplikat)
- Hasil bisa dilihat di tabel tagihan atau di halaman **Piutang**

---

## 5. Panduan Pembayaran Kasir / Loket

Menu ini digunakan oleh **Kasir Kampus** untuk memproses pembayaran langsung di loket.

### Langkah-langkah:
1. Buka **`/sikeu/tagihan/create`**
2. **Step 1 — Cari Mahasiswa**: Ketik NIM atau nama mahasiswa di kolom pencarian
3. Pilih mahasiswa dari dropdown autocomplete
4. **Step 2 — Pilih Komponen**: Centang komponen tagihan yang akan dilunasi
5. **Step 3 — Metode Pembayaran**:
   - **Virtual Account BNI**: Terbitkan nomor VA untuk transfer ATM/Mobile Banking
   - **Bayar Tunai Loket Kasir**: Pelunasan tunai langsung di tempat
6. Isi **Catatan Transaksi** (opsional)
7. Klik **"Proses Pembayaran Loket"** atau **"Terbitkan Nomor VA"**
8. Setelah berhasil:
   - Sistem menampilkan **kode transaksi** atau **nomor VA**
   - Klik **"Cetak Kuitansi"** untuk mencetak bukti

### Validasi Otomatis:
- ✅ Jumlah bayar tidak boleh melebihi sisa tagihan
- ✅ Periode akuntansi harus berstatus **Terbuka** (bukan Ditutup)
- ✅ Jurnal akuntansi (Debet Kas / Kredit Pendapatan) ter-generate otomatis

### Koreksi Pembayaran:
Jika terjadi salah input, gunakan fitur **Koreksi Transaksi**:
1. Buka halaman **Riwayat Pembayaran** (`/sikeu/pembayaran`)
2. Cari transaksi yang ingin dikoreksi
3. Klik **"Koreksi"**
4. Isi alasan koreksi (min. 10 karakter)
5. Sistem akan:
   - Membalik status pembayaran ke `reversed`
   - Mengurangi total bayar pada tagihan
   - Membuat jurnal koreksi pembalik

---

## 6. Panduan Penerbitan Virtual Account

### Alur Otomatis (Saat Tagihan Dibuat):
- Jika tagihan **tidak memerlukan approval** (`requires_approval = false`), VA langsung ter-generate otomatis saat tagihan diterbitkan
- Terhubung dengan **Xendit API** (jika konfigurasi Payment Gateway aktif)

### Alur Manual (Kasir):
1. Buka **`/sikeu/tagihan/create`**
2. Pilih mahasiswa → Pilih komponen → Pilih **"Virtual Account BNI"**
3. Klik **"Terbitkan Nomor VA"**
4. Salin nomor VA yang ditampilkan dan berikan ke mahasiswa

### Portal Mandiri Mahasiswa:
1. Mahasiswa login dengan akun sendiri
2. Buka **`/sikeu/mahasiswa/tagihan`**
3. Lihat tagihan → Klik **"Cetak Invoice & VA"**
4. Invoice resmi dengan Nomor VA akan tampil dan bisa dicetak

---

## 7. Panduan Dispensasi Pembayaran

Dispensasi diberikan kepada mahasiswa yang belum bisa melunasi tagihan tepat waktu.

### Pengajuan:
1. Buka **`/sikeu/dispensasi`**
2. Klik **"Ajukan Dispensasi Baru"**
3. Cari mahasiswa (NIM/Nama)
4. Pilih tagihan yang akan didispensasi
5. Isi:
   - **Tipe Dispensasi**: Penundaan Jatuh Tempo / Cicilan / Keringanan Khusus
   - **Jatuh Tempo Baru**: Tanggal perpanjangan
   - **Alasan**: Deskripsi alasan dispensasi
   - **Dokumen Pendukung**: Upload jika ada
6. Klik **"Ajukan"**

> ⚠️ **Peringatan Otomatis**: Jika mahasiswa masih memiliki **dispensasi lama yang belum lunas**, sistem akan menampilkan peringatan. Hal ini wajib diperhatikan oleh Pimpinan sebelum menyetujui.

### Approval oleh Pimpinan:
1. Login sebagai **pimpinan**
2. Buka **`/sikeu/approval`**
3. Lihat daftar dispensasi pending
4. Klik **"Approve"** atau **"Reject"** + catatan

### Cetak Bukti Dispensasi:
- Di halaman `/sikeu/dispensasi`, klik **"Cetak Bukti"** pada dispensasi yang sudah approved

---

## 8. Panduan Piutang & Export Laporan

1. Buka **`/sikeu/piutang`**
2. Gunakan filter:
   - **Angkatan**: Filter per tahun angkatan
   - **Status**: Piutang (belum lunas), Belum Bayar, Sebagian, Dispensasi, Lunas, Semua
   - **Program Studi**: Filter per prodi
   - **Pencarian**: NIM atau nama mahasiswa
3. Lihat ringkasan di atas tabel:
   - Total Tagihan, Total Potongan, Total Denda, Total Bayar, Total Piutang
   - Total Mahasiswa Tunggakan
4. Klik **"Export Excel"** untuk mengunduh laporan dalam format CSV

---

## 9. Panduan Tutup Buku Periode Akuntansi

Tutup buku mengunci periode akuntansi sehingga transaksi pada periode tersebut **tidak bisa diinput atau dikoreksi** lagi.

### Langkah-langkah:
1. Buka **`/sikeu/akuntansi`**
2. Navigasi ke tab/menu **Periode Akuntansi**
3. Cari periode yang ingin ditutup (contoh: "Periode Agustus 2026")
4. Ubah status dari **Terbuka** ke **Ditutup**
5. Konfirmasi tutup buku

### Dampak:
- ❌ Pembayaran kasir dengan tanggal dalam periode tersebut akan **ditolak**
- ❌ Koreksi pembayaran dalam periode tersebut akan **ditolak**
- ✅ Laporan keuangan periode tersebut sudah **final**

---

## 10. Peta Menu SIKEU per Role

| Menu / Halaman | Operator SIKEU | Kabag Keuangan | Pimpinan | Mahasiswa |
|---|:---:|:---:|:---:|:---:|
| Dashboard SIKEU | ✅ | ✅ | ✅ | ❌ |
| Master Biaya | ✅ | ✅ | ❌ | ❌ |
| Setting Tarif | ✅ | ✅ | ❌ | ❌ |
| Jalur Kelas & Beasiswa | ✅ | ✅ | ❌ | ❌ |
| Student Billing Types | ✅ | ✅ | ❌ | ❌ |
| Tarif SPMB | ✅ | ✅ | ❌ | ❌ |
| Unit Kas | ❌ | ✅ | ❌ | ❌ |
| Set Tagihan & Invoice | ✅ | ✅ | ❌ | ❌ |
| Generate Tagihan Masal | ✅ | ✅ | ❌ | ❌ |
| Pembayaran Kasir/VA | ✅ | ✅ | ❌ | ❌ |
| Riwayat Pembayaran | ✅ | ✅ | ❌ | ❌ |
| Piutang Mahasiswa | ✅ | ✅ | ❌ | ❌ |
| Export Piutang Excel | ✅ | ✅ | ❌ | ❌ |
| Dispensasi | ✅ | ✅ | ❌ | ❌ |
| Approval Tagihan | ❌ | ⚠️ | ✅ | ❌ |
| Approval Dispensasi | ❌ | ⚠️ | ✅ | ❌ |
| Akuntansi (COA, Jurnal, Buku Besar) | ❌ | ✅ | ❌ | ❌ |
| Pemasukan Kampus | ❌ | ✅ | ❌ | ❌ |
| Pengeluaran Kampus | ❌ | ✅ | ❌ | ❌ |
| Pajak Kampus | ❌ | ✅ | ❌ | ❌ |
| Payment Gateway | ❌ | ✅ | ❌ | ❌ |
| Kas Kabag Keuangan | ❌ | ✅ | ❌ | ❌ |
| Portal Tagihan Mandiri | ❌ | ❌ | ❌ | ✅ |
| Cetak Invoice & VA | ❌ | ❌ | ❌ | ✅ |

> ⚠️ = Akses terbatas / kondisional (bisa lihat tapi fungsi utama di Pimpinan)

---

## 11. FAQ & Troubleshooting

### Q1: Kenapa tidak bisa input pembayaran di kasir?
**A**: Pastikan periode akuntansi untuk bulan berjalan berstatus **Terbuka**. Cek di `/sikeu/akuntansi` → Periode. Jika sudah ditutup, hubungi Kabag Keuangan untuk membuka kembali.

### Q2: Mahasiswa tidak bisa mengisi KRS, kenapa?
**A**: Sistem SIAKAD melakukan pengecekan ke SIKEU. Pastikan mahasiswa sudah melunasi tagihan atau memiliki dispensasi aktif. Cek di `/sikeu/piutang`.

### Q3: Bagaimana koreksi pembayaran yang salah input?
**A**: Buka `/sikeu/pembayaran` → Cari transaksi → Klik "Koreksi" → Isi alasan (min. 10 karakter). Sistem akan membuat jurnal koreksi pembalik.

### Q4: Apakah tagihan bisa dibatalkan setelah diterbitkan?
**A**: Ya, status tagihan bisa diubah ke `batal` oleh Kabag Keuangan. Namun tagihan yang sudah memiliki pembayaran harus dikoreksi terlebih dahulu.

### Q5: Bagaimana cara menambah potongan/beasiswa ke tagihan mahasiswa?
**A**: Buka `/sikeu/master` → Tab "Beasiswa" → Tambah beasiswa, lalu mapping ke mahasiswa di tab "Mapping Beasiswa".

### Q6: Tagihan masal tidak ter-generate, kenapa?
**A**: Periksa:
1. Setting Tarif sudah ada untuk angkatan + jalur yang dipilih
2. Tipe Tagihan Mahasiswa sudah ditetapkan (tab "Student Types")
3. Belum ada tagihan dengan nomor yang sama (duplikat)

### Q7: Nomor Virtual Account tidak muncul, kenapa?
**A**: Periksa konfigurasi Payment Gateway di `/sikeu/payment-gateway`. Pastikan API Key Xendit sudah diisi dan statusnya aktif. Jika gateway tidak aktif, sistem menggunakan VA lokal (dummy).

### Q8: Bagaimana cara melihat jurnal akuntansi yang ter-generate dari pembayaran?
**A**: Buka `/sikeu/akuntansi/jurnal`. Cari jurnal dengan keterangan yang memuat kode transaksi pembayaran.

### Q9: Apakah dispensasi otomatis membuka akses KRS?
**A**: Ya, selama tanggal berjalan masih dalam jangka dispensasi (sebelum jatuh tempo baru), sistem SIAKAD akan menganggap syarat pembayaran terpenuhi.

### Q10: Bagaimana cara mencetak laporan piutang per angkatan?
**A**: Buka `/sikeu/piutang` → Set filter Angkatan → Klik "Export Excel". File CSV akan otomatis terunduh.

### Q11: Saya login sebagai mahasiswa tapi tidak melihat tagihan?
**A**: Pastikan tagihan sudah diterbitkan untuk mahasiswa tersebut. Cek apakah `mahasiswa_id` pada tagihan sesuai dengan ID user yang login.

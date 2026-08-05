# Panduan Konfigurasi Payment Gateway (Xendit & Duitku)

Dokumen ini menjelaskan langkah-langkah konfigurasi akun Payment Gateway (Xendit dan Duitku) agar dapat terintegrasi dengan baik ke dalam Sistem Informasi Terpadu Kampus (SIKEU).

---

## 1. Konfigurasi Xendit

### A. Mendapatkan API Keys
1. Login ke Dashboard Xendit Anda (https://dashboard.xendit.co/).
2. Pastikan Anda berada di mode yang tepat: **Test Mode** (untuk testing/sandbox) atau **Live Mode** (untuk production).
3. Masuk ke menu **Settings** (Pengaturan) di menu sebelah kiri bawah.
4. Pilih **API Keys** di bawah bagian Developers.
5. Anda akan menemukan **Public Key** yang bisa langsung disalin.
6. Untuk **Secret Key**, klik tombol **Generate secret key** (Buat secret key baru).

### B. Mengatur Hak Akses (Permissions) API Key
Saat membuat Secret Key baru, Anda **wajib** mengatur hak akses yang tepat agar SIKEU dapat berjalan (menagih UKT, mencairkan dana Kas, dan mengecek saldo).

Atur permission berikut pada halaman pembuatan API Key:

#### Wajib Diaktifkan:
*   **Saldo**: `READ` (Untuk melihat dan sinkronisasi ketersediaan saldo secara Real-Time).
*   **Produk Menerima Pembayaran > Virtual Accounts**: `WRITE` (Krusial untuk sistem tagihan mahasiswa SIKEU).
*   **Produk Mengirim Pembayaran > Disbursements**: `WRITE` (Krusial untuk fitur ACC Kabag dan pencairan dana langsung ke rekening Unit Kas).

#### Sangat Disarankan (Untuk Ekosistem Penuh):
*   **Produk Menerima Pembayaran > Invoices**: `WRITE` (Untuk payment link lengkap dengan berbagai channel pembayaran).
*   **Produk Menerima Pembayaran > Retail Outlets (OTC)**: `WRITE` (Untuk pembayaran via Alfamart/Indomaret).
*   **Produk Menerima Pembayaran > E-wallets**: `WRITE` (OVO, Dana, ShopeePay, dsb).
*   **Report**: `READ`
*   **Transaction**: `READ`

*Catatan: Bagian `xenPlatform`, `xenShield`, dan `Pembuktian Identitas` dapat dibiarkan `None` atau tidak diaktifkan karena tidak relevan dengan SIKEU saat ini.*

### C. Webhook (Callback URL)
1. Di Dashboard Xendit, masuk ke **Settings > Webhooks**.
2. Masukkan URL Webhook SIKEU Anda (Misal: `https://api.domain-kampus.ac.id/v1/sikeu/callbacks/xendit`).
3. Centang event yang dibutuhkan, minimal:
   - `Virtual Account Paid`
   - `Invoice Paid`
   - `Disbursement Sent/Failed`
4. Dapatkan **Webhook Token** (Callback Token) dari halaman ini untuk verifikasi keamanan di SIKEU.

---

## 2. Konfigurasi Duitku

### A. Mendapatkan API Keys (Merchant Code & API Key)
1. Login ke Merchant Dashboard Duitku (https://merchant.duitku.com/).
2. Pilih environment yang digunakan (**Sandbox** atau **Production**).
3. Buat/Daftarkan **Proyek Baru** (Project) untuk SIKEU.
4. Masuk ke menu **My Project** (Proyek Saya).
5. Di detail proyek, Anda akan mendapatkan informasi berikut:
   - **Merchant Code**: Kode unik merchant Anda.
   - **API Key**: Kunci rahasia untuk otorisasi API.

### B. Mengatur Callback (Webhook) & Return URL
Di dalam halaman konfigurasi Proyek Duitku yang sama, atur URL berikut:
1. **Callback URL**: `https://api.domain-kampus.ac.id/v1/sikeu/callbacks/duitku` (URL ini akan ditembak oleh Duitku secara _background_ saat pembayaran mahasiswa berhasil).
2. **Return URL**: `https://sikeu.domain-kampus.ac.id/pembayaran/sukses` (URL tujuan redirect setelah mahasiswa selesai melakukan pembayaran di halaman Duitku).

### C. Hak Akses Duitku
Berbeda dengan Xendit yang mengatur permission di level API Key, Duitku biasanya mengatur jenis pembayaran (Payment Method) yang aktif melalui Dashboard:
1. Masuk ke menu **Payment Method**.
2. Aktifkan metode pembayaran yang diinginkan (Bank Transfer VA, E-Wallet, Retail, dsb).
3. Untuk fitur pencairan dana (jika Anda menggunakan layanan Disbursement Duitku), hubungi tim support Duitku untuk aktivasi fitur **Transfer Dana** (Disbursement) pada akun Anda.

---

## 3. Integrasi ke Aplikasi SIKEU

Setelah Anda mendapatkan API Key dari Xendit atau Duitku:
1. Buka aplikasi SIKEU (Frontend).
2. Masuk ke Menu **Keuangan > Pengaturan > Payment Gateway**.
3. Pilih Tab **Xendit** atau **Duitku**.
4. Masukkan **Public Key**, **Secret/API Key**, dan **Webhook Token/Merchant Code** ke dalam form yang tersedia.
5. Klik **Aktifkan Gateway Ini** (hanya satu gateway yang bisa aktif secara bersamaan).
6. Klik **Simpan Konfigurasi**.
7. Lakukan **Test Sinkronisasi Saldo** untuk memastikan API Key sudah dimasukkan dengan benar dan izin (permissions) sudah sesuai.

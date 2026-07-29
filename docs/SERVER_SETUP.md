# Panduan Konfigurasi Server (Deployment)

Dokumen ini berisi panduan teknis terkait pengaturan server untuk backend Sistem Terintegrasi Kampus.

---

## Menjalankan Laravel Queue Secara Otomatis (Supervisor)

Karena aplikasi kita menggunakan mekanisme *asynchronous* (seperti pengiriman email Reset Password di latar belakang), proses `php artisan queue:work` harus terus berjalan 24/7 di server. 

Jika server direstart atau terjadi *crash*, proses tersebut akan mati jika hanya dijalankan secara manual. Oleh karena itu, kita **wajib menggunakan Supervisor** pada lingkungan *production* (seperti server VPS dengan panel HestiaCP / Ubuntu).

### Langkah-Langkah Konfigurasi Supervisor

Pastikan Anda memiliki akses **SSH sebagai root** ke server Anda.

#### 1. Install Supervisor
Masuk ke terminal server Anda, lalu jalankan perintah berikut:
```bash
sudo apt-get update
sudo apt-get install supervisor
```

#### 2. Buat File Konfigurasi Baru
Buat file konfigurasi khusus untuk *worker* aplikasi kita di direktori `/etc/supervisor/conf.d/`. Anda bisa menamainya bebas, contoh: `integrasisistem-worker.conf`.

Gunakan *text editor* seperti `nano`:
```bash
sudo nano /etc/supervisor/conf.d/integrasisistem-worker.conf
```

Lalu, salin dan tempel kode berikut ke dalamnya:

```ini
[program:integrasisistem-worker]
process_name=%(program_name)s_%(process_num)02d
# SESUAIKAN: Path absolut menuju root folder Laravel Anda
command=php /home/USER_HESTIA/web/DOMAIN_ANDA/public_html/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
# SESUAIKAN: Ganti dengan username panel HestiaCP Anda (bukan root)
user=USER_HESTIA
numprocs=1
redirect_stderr=true
# SESUAIKAN: Path absolut tempat menyimpan log worker
stdout_logfile=/home/USER_HESTIA/web/DOMAIN_ANDA/public_html/storage/logs/worker.log
```

> **⚠️ Perhatian:**
> Pastikan Anda mengganti kata `USER_HESTIA` dan `DOMAIN_ANDA` agar sesuai dengan *path* (lokasi) web Anda di server. Menjalankan *worker* sebagai `user=root` sangat **tidak disarankan**.

#### 3. Simpan dan Aktifkan Konfigurasi
Setelah file disimpan, beri tahu Supervisor untuk membaca ulang file konfigurasi dan menyalakannya:

```bash
# Membaca ulang konfigurasi baru
sudo supervisorctl reread

# Memperbarui status program
sudo supervisorctl update

# Memulai worker (jika belum otomatis berjalan)
sudo supervisorctl start integrasisistem-worker:*
```

#### 4. Perintah Berguna (Maintenance)
Jika ke depannya Anda memperbarui kode (misalnya mengubah isi tampilan email atau *logic* di dalam `PasswordResetMail`), Anda **wajib me-restart** *worker* agar perubahan kode tersebut terbaca.

- **Restart worker:**
  ```bash
  sudo supervisorctl restart integrasisistem-worker:*
  ```
  *(Atau jalankan `php artisan queue:restart` dari dalam folder proyek).*
- **Cek status apakah worker sedang berjalan:**
  ```bash
  sudo supervisorctl status
  ```

---

*Dokumen ini dibuat agar konfigurasi background job di production server (seperti HestiaCP) tidak terlupakan.*

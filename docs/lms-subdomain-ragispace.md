# Runbook Subdomain LMS — lms.ragispace.com

Dokumen ini merangkum konfigurasi yang diperlukan agar subdomain `lms.ragispace.com` mengarahkan ke aplikasi LMS tanpa memisahkan arsitektur backend/frontend secara terpisah (mengacu mekanisme generik yang sudah ada di repositori).

## 1. Tujuan
- Menyediakan akses modul LMS via subdomain terdedikasi
- Tetap menggunakan routing/module registry yang ada (tanpa duplikasi backend)
- Konsisten dengan pola tenant/subdomain generik proyek RAG

## 2. Prasyarat
- Akses DNS provider untuk domain `ragispace.com`
- Akses Vercel (atau hosting FE) untuk project frontend
- Akses server/backend (nginx/traefik) untuk routing API
- Akses konfigurasi env FE/BE

## 3. Konfigurasi DNS
Tambahkan record berikut di DNS provider:

| Tipe | Host | Nilai | TTL | Keterangan |
|---|---|---|---|---|
| CNAME | lms | `<frontend-vercel-alias>` / IP sesuai hosting FE | 3600/Auto | Arahkan ke deployment frontend |
| A/CNAME | api-lms atau biarkan API melalui domain utama | Sesuai kebutuhan proxy | 3600/Auto | Jika ingin API via subdomain terpisah, sesuaikan reverse proxy. Secara default gunakan `NEXT_PUBLIC_API_URL` mengarah ke API utama. |

> Catatan: Project saat ini menggunakan mekanisme generik (proxy/tenant-config/SYSTEM_MODULES/MODULE_LABELS/backend module). Verifikasi terlebih dahulu konfigurasi aktif di environment target.

## 4. Konfigurasi Frontend (Next.js/Vercel)
Tambahkan environment variable pada deployment Vercel untuk `lms.ragispace.com` (Production/Preview sesuai kebutuhan):

```env
NEXT_PUBLIC_APP_NAME=RAG LMS
NEXT_PUBLIC_API_URL=https://api.<domain-utama>/api
NEXT_PUBLIC_MODULE=LMS
NEXT_PUBLIC_BASE_PATH=
NEXT_PUBLIC_DOMAIN=lms.ragispace.com
```

Langkah Vercel:
1. Project Settings → Domains → Tambahkan `lms.ragispace.com`
2. Environment Variables → Atur nilai di atas untuk environment terkait
3. Redeploy setelah perubahan domain/env

## 5. Konfigurasi Backend
Backend tidak memerlukan routing terpisah jika menggunakan middleware/tenant logic yang sudah ada. Pastikan hal berikut:

- `APP_URL` tetap mengarah ke domain API utama
- CORS mengizinkan origin `https://lms.ragispace.com`
- Module LMS terdaftar di `modules`/`core_menus` (sudah ter-cover migrasi)
- Permission `siakad.kelas.read`/`siakad.kelas.manage` tersedia sesuai role

Contoh CORS (sesuai konfigurasi proyek):
```php
'allowed_origins' => [
    'https://lms.ragispace.com',
    // domain lain yang dibutuhkan
],
```

## 6. Reverse Proxy (Nginx/Traefik) — Opsional
Jika menggunakan reverse proxy terpisah untuk subdomain:

```nginx
server {
    server_name lms.ragispace.com;
    location / {
        proxy_pass http://frontend_lms_upstream;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

API tetap diarahkan melalui `NEXT_PUBLIC_API_URL` ke endpoint API utama (disarankan untuk menghindari kompleksitas auth/CORS).

## 7. Checklist Verifikasi
- [ ] DNS resolve `lms.ragispace.com` ke frontend
- [ ] Halaman LMS terbuka tanpa redirect aneh
- [ ] Login/auth berfungsi (token/cookie sesuai konfigurasi)
- [ ] Menu module LMS muncul sesuai role (`/lms`, `/lms/pertemuan`, `/lms/tryout`, `/lms/forum`, `/lms/pengaturan`)
- [ ] API call berhasil (network 200/403 sesuai permission)
- [ ] File upload/download berfungsi via `FileStorageService`
- [ ] Cek console browser tanpa CORS error

## 8. Troubleshooting
| Masalah | Penyebab | Solusi |
|---|---|---|
| CORS error | Origin belum diizinkan | Tambahkan `lms.ragispace.com` ke allowed origins backend |
| Menu tidak muncul | Role belum dapat permission/menu | Jalankan migrasi/seeder terkait menu LMS atau tambahkan role mapping |
| 404 ke API | `NEXT_PUBLIC_API_URL` salah | Perbaiki env frontend ke domain API yang benar |
| Redirect loop | Konfigurasi auth/session domain salah | Sesuaikan cookie domain / auth config |

## Referensi
- `docs/api/LMS/` — Dokumentasi endpoint LMS
- `routes/lms.php` — Route modul LMS
- `backend/database/migrations/2026_10_05_080000_add_lms_module_level_menus.php` — Menu module-level

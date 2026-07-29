---
name: event-listener-standard
description: Standar penggunaan Laravel Events dan Listeners untuk menangani proses-proses reaktif antar-modul di ekosistem kampus terintegrasi.
---

# Standar Event & Listener

Gunakan Events dan Listeners untuk proses yang bersifat **reaktif** (terjadi sebagai dampak dari aksi lain) dan **antar-modul**, agar modul-modul tetap terpisah (decoupled) dan tidak saling bergantung langsung.

---

## 1. Kapan Harus Menggunakan Event?

Gunakan Event jika:
- Sebuah aksi di modul A perlu memicu aksi di modul B.
- Aksi lanjutan tidak boleh menghambat response ke user (bisa di-queue).
- Ada lebih dari 1 "reaksi" terhadap satu kejadian.

### Contoh Kasus di Ekosistem Kampus:

| Event | Listener(s) yang Terpicu |
|---|---|
| Mahasiswa diterima SPMB | Buat akun user, generate NIM, kirim email selamat datang |
| KRS disetujui Dosen Wali | Kirim notifikasi ke mahasiswa, unlock status akademik |
| Nilai diinput final | Hitung ulang IPK mahasiswa, update KHS |
| User login berhasil | Catat audit log, update `last_login_at` |
| Mahasiswa lulus sidang | Update status ke `lulus`, buat data alumni |

---

## 2. Struktur Direktori

```
app/
├── Events/
│   ├── IAM/
│   │   └── UserLoggedIn.php
│   ├── SPMB/
│   │   └── MahasiswaDiterima.php
│   └── SIAKAD/
│       └── NilaiDifinalisasi.php
└── Listeners/
    ├── IAM/
    │   └── RecordLoginAuditLog.php
    ├── SPMB/
    │   ├── CreateUserAccount.php
    │   ├── GenerateNIM.php
    │   └── SendWelcomeEmail.php
    └── SIAKAD/
        └── RecalculateIPK.php
```

---

## 3. Membuat Event

```bash
php artisan make:event SPMB/MahasiswaDiterima
```

```php
<?php
namespace App\Events\SPMB;

use App\Models\HasilSeleksi;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MahasiswaDiterima
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly HasilSeleksi $hasilSeleksi
    ) {}
}
```

---

## 4. Membuat Listener

```bash
php artisan make:listener SPMB/CreateUserAccount --event=MahasiswaDiterima
```

```php
<?php
namespace App\Listeners\SPMB;

use App\Events\SPMB\MahasiswaDiterima;
use App\Services\IAM\UserService;
use Illuminate\Contracts\Queue\ShouldQueue;

// Implement ShouldQueue agar diproses di background (tidak block response)
class CreateUserAccount implements ShouldQueue
{
    public string $queue = 'spmb';  // Tentukan queue yang digunakan

    public function __construct(private UserService $userService) {}

    public function handle(MahasiswaDiterima $event): void
    {
        $pendaftaran = $event->hasilSeleksi->pendaftaran;

        $this->userService->create([
            'username'  => $pendaftaran->no_pendaftaran,
            'email'     => $pendaftaran->email ?? null,
            'user_type' => 'mahasiswa',
        ]);
    }
}
```

---

## 5. Mendaftarkan Event ke Listener

Di `app/Providers/AppServiceProvider.php`:

```php
use Illuminate\Support\Facades\Event;

public function boot(): void
{
    Event::listen(
        \App\Events\SPMB\MahasiswaDiterima::class,
        [
            \App\Listeners\SPMB\CreateUserAccount::class,
            \App\Listeners\SPMB\GenerateNIM::class,
            \App\Listeners\SPMB\SendWelcomeEmail::class,
        ]
    );
}
```

---

## 6. Men-dispatch Event dari Service

```php
use App\Events\SPMB\MahasiswaDiterima;

// Di dalam HasilSeleksiService atau Controller
MahasiswaDiterima::dispatch($hasilSeleksi);
```

---

## 7. Aturan Penting

- Listener yang memerlukan waktu lama (kirim email, hitung IPK) **WAJIB** implement `ShouldQueue`.
- **JANGAN** dispatch Event dari dalam Listener (hindari event loop).
- Satu Event boleh punya banyak Listener.
- Gunakan nama Event dalam Bahasa Indonesia + konteks yang jelas: `MahasiswaDiterima`, bukan `SelectionPassed`.

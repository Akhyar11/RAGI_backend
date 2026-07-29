---
name: audit-log-standard
description: Standar kapan dan bagaimana mencatat jejak perubahan data sensitif ke tabel audit_logs untuk keperluan akuntabilitas sistem.
---

# Standar Audit Log

Tabel `audit_logs` sudah tersedia di database. Gunakan standar berikut untuk mencatat setiap perubahan data yang signifikan secara konsisten.

> [!IMPORTANT]
> **INSTRUKSI KRITIKAL UNTUK AGEN (AI):**
> Setiap kali Anda (Agen) diminta untuk membuat **Modul Baru, Fitur CRUD Baru, atau Model Eloquent Baru** yang menyimpan data operasional/sensitif (misalnya modul SPMB, SIAKAD, dsb), Anda **WAJIB** membuat `Observer` untuk model tersebut (misal `MahasiswaObserver`) dan mendaftarkannya di `AppServiceProvider.php` agar setiap aksi Create, Update, dan Delete langsung terintegrasi dengan `AuditLogService`. Jangan menunggu instruksi spesifik dari pengguna untuk melakukan ini.

---

## 1. Data Apa yang Wajib Dicatat?

| Aksi | Wajib Dicatat? | Contoh |
|---|---|---|
| Login berhasil | ✅ Ya | User masuk sistem |
| Login gagal | ✅ Ya | Percobaan login dengan password salah |
| Create data sensitif | ✅ Ya | Buat user, buat role, input nilai |
| Update data sensitif | ✅ Ya | Ubah password, ubah status mahasiswa |
| Delete / Soft Delete | ✅ Ya | Hapus user, hapus nilai |
| Read / View biasa | ❌ Tidak | Melihat daftar mahasiswa |
| Export data | ✅ Ya | Export laporan ke PDF/Excel |
| Persetujuan/Validasi | ✅ Ya | Dosen wali menyetujui KRS, ACC Dokumen |

*Catatan: Semua tabel operasional di modul OBE, SIMPI, SIMANTA, SIMPRESKUL, SIKEU (seperti data mahasiswa, mata kuliah, transaksi, pendaftaran) masuk dalam kategori data sensitif.*

---

## 2. Struktur AuditLog Service

Buat helper service yang bisa dipanggil dari mana saja:

```php
<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogService
{
    public static function record(
        string $module,
        string $action,
        string $tableName,
        int $recordId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null
    ): void {
        AuditLog::create([
            'user_id'    => auth()->id(),
            'module'     => $module,
            'action'     => $action,
            'table_name' => $tableName,
            'record_id'  => $recordId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
```

---

## 3. Cara Memanggil di Controller / Service

```php
use App\Services\AuditLogService;

// Saat update user
AuditLogService::record(
    module: 'IAM',
    action: 'update',
    tableName: 'users',
    recordId: $user->id,
    oldValues: $user->getOriginal(),
    newValues: $user->getChanges(),
    request: $request
);

// Saat login berhasil
AuditLogService::record(
    module: 'IAM',
    action: 'login',
    tableName: 'users',
    recordId: $user->id,
    request: $request
);
```

---

## 4. Konvensi Nilai `module` dan `action`

**Module** mengikuti nama modul di ERD:
`IAM`, `SPMB`, `SIAKAD`, `SIKEU`, `SIMPEG`, `LMS`, `UPM`

**Action** menggunakan nilai standar berikut:
`login`, `logout`, `create`, `update`, `delete`, `restore`, `approve`, `reject`, `export`

---

## 5. Menggunakan Laravel Observer (Otomatis)

Untuk model-model sensitif, gunakan Observer agar tidak perlu manual setiap saat:

```bash
php artisan make:observer UserObserver --model=User
```

```php
<?php
namespace App\Observers;

use App\Models\User;
use App\Services\AuditLogService;

class UserObserver
{
    public function updated(User $user): void
    {
        if ($user->wasChanged()) {
            AuditLogService::record(
                module: 'IAM',
                action: 'update',
                tableName: 'users',
                recordId: $user->id,
                oldValues: $user->getOriginal(),
                newValues: $user->getChanges(),
            );
        }
    }

    public function deleted(User $user): void
    {
        AuditLogService::record(
            module: 'IAM',
            action: 'delete',
            tableName: 'users',
            recordId: $user->id,
        );
    }
}
```

Daftarkan Observer di `AppServiceProvider`:
```php
User::observe(UserObserver::class);
```

---

## 6. Aturan Penting

- `old_values` dan `new_values` **WAJIB** di-filter — jangan pernah menyertakan kolom `password` atau token sensitif.
- Gunakan helper `$model->getOriginal()` SEBELUM `save()` untuk menangkap nilai lama.
- Audit log **TIDAK BOLEH** menyebabkan request gagal. Bungkus pencatatan dalam `try-catch` jika diperlukan.

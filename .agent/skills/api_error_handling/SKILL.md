---
name: api-error-handling
description: Standar global Exception Handler untuk memastikan semua error API dikembalikan dalam format JSON yang konsisten sesuai standar proyek.
---

# Standar API Error Handling

Semua error dan exception di proyek ini **WAJIB** dikembalikan dalam format JSON yang konsisten. Jangan biarkan error PHP mentah atau HTML stack trace bocor ke response API.

---

## 1. Format Error Response Standar

Semua error harus mengikuti format ini:

```json
{
    "status": "error",
    "message": "Pesan error yang human-readable",
    "errors": {
        "field_name": ["Deskripsi error spesifik"]
    }
}
```

Field `errors` bersifat opsional, hanya ada untuk error validasi (422).

---

## 2. Konfigurasi Global Exception Handler

Edit file `bootstrap/app.php` untuk menangkap semua exception:

```php
->withExceptions(function (Exceptions $exceptions) {
    // Tangani Validation Exception (422)
    $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data yang diberikan tidak valid.',
                'errors'  => $e->errors(),
            ], 422);
        }
    });

    // Tangani Model Not Found (404)
    $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
        if ($request->expectsJson()) {
            $model = class_basename($e->getModel());
            return response()->json([
                'status'  => 'error',
                'message' => "{$model} tidak ditemukan.",
            ], 404);
        }
    });

    // Tangani Authorization Exception (403)
    $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Anda tidak memiliki izin untuk melakukan aksi ini.',
            ], 403);
        }
    });

    // Tangani Authentication Exception (401)
    $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Token tidak valid atau sesi telah berakhir.',
            ], 401);
        }
    });

    // Tangani semua error tidak terduga (500)
    $exceptions->render(function (\Throwable $e, $request) {
        if ($request->expectsJson() && !config('app.debug')) {
            \Log::error($e);
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan internal pada server.',
            ], 500);
        }
    });
})
```

---

## 3. Tabel HTTP Status Code yang Digunakan

| Kode | Situasi |
|---|---|
| `200` | Request berhasil (GET, PUT, PATCH, DELETE) |
| `201` | Resource berhasil dibuat (POST) |
| `400` | Request tidak valid (bad request) |
| `401` | Belum login / token kedaluwarsa |
| `403` | Sudah login tapi tidak punya izin |
| `404` | Resource tidak ditemukan |
| `422` | Validasi data gagal |
| `429` | Terlalu banyak request (rate limit) |
| `500` | Error tidak terduga di server |

---

## 4. Aturan Penting

- **JANGAN** gunakan `try-catch` di controller hanya untuk menangkap exception umum yang sudah ditangani oleh global handler.
- **BOLEH** gunakan `try-catch` untuk exception bisnis spesifik yang perlu response khusus.
- Semua error level `500` **WAJIB** di-log menggunakan `\Log::error($e)`.
- Aktifkan `APP_DEBUG=false` di environment production.

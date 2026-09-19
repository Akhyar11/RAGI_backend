---
name: api-crud-standard
description: Standar baku pembuatan API CRUD di Laravel untuk proyek ini, mencakup struktur response, filter, pagination, validasi, dan error handling yang konsisten.
---

# Standar API CRUD — Integrated Sistem Backend

Setiap kali membuat atau memodifikasi endpoint API CRUD di proyek ini, **WAJIB** mengikuti semua ketentuan di bawah ini tanpa pengecualian.

---

## 1. Struktur Response Wajib

Semua response API harus terbungkus dalam envelope JSON yang konsisten:

### Success Response (List)
```json
{
    "status": "success",
    "message": "Data retrieved successfully",
    "data": [...],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 100,
        "last_page": 7,
        "from": 1,
        "to": 15
    },
    "filters": {
        "search": "keyword",
        "sort_by": "created_at",
        "sort_order": "desc"
    }
}
```

### Success Response (Single / Create / Update)
```json
{
    "status": "success",
    "message": "Data created successfully",
    "data": {...}
}
```

### Error Response
```json
{
    "status": "error",
    "message": "Deskripsi error",
    "errors": {...}
}
```

---

## 2. Pagination Wajib

- **WAJIB** menggunakan `->paginate($perPage)` pada setiap endpoint `index`.
- **WAJIB** mendukung query parameter `?per_page=` dengan nilai default `15` dan maksimum `100`.
- Gunakan `$request->integer('per_page', 15)` dan `min(100, ...)` untuk membatasi nilai.

### Contoh Implementasi:
```php
public function index(Request $request)
{
    $perPage = min(100, $request->integer('per_page', 15));
    $query = Model::query();
    // ... tambahkan filter di sini
    $data = $query->paginate($perPage);

    return response()->json([
        'status' => 'success',
        'message' => 'Data retrieved successfully',
        'data' => $data->items(),
        'meta' => [
            'current_page' => $data->currentPage(),
            'per_page' => $data->perPage(),
            'total' => $data->total(),
            'last_page' => $data->lastPage(),
            'from' => $data->firstItem(),
            'to' => $data->lastItem(),
        ],
    ]);
}
```

---

## 3. Filter Wajib

Setiap endpoint `index` **WAJIB** mendukung setidaknya filter berikut:

| Query Parameter | Fungsi | Contoh |
|---|---|---|
| `?search=` | Pencarian teks pada kolom yang relevan | `?search=Akhyar` |
| `?sort_by=` | Kolom yang dipakai untuk pengurutan | `?sort_by=created_at` |
| `?sort_order=` | Arah pengurutan (`asc` atau `desc`) | `?sort_order=desc` |

Tambahkan filter spesifik per modul sesuai kebutuhan (contoh: `?user_type=` untuk endpoint `/users`).

### Contoh Implementasi Filter:
```php
$query = Model::query();

// Filter pencarian teks
if ($request->filled('search')) {
    $search = $request->search;
    $query->where(function ($q) use ($search) {
        $q->where('nama_kolom', 'like', "%{$search}%")
          ->orWhere('kolom_lain', 'like', "%{$search}%");
    });
}

// Sorting
$allowedSortColumns = ['created_at', 'updated_at', 'nama']; // Kolom whitelist
$sortBy = in_array($request->sort_by, $allowedSortColumns) ? $request->sort_by : 'created_at';
$sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
$query->orderBy($sortBy, $sortOrder);
```

---

## 4. Validasi Request

- **WAJIB** menggunakan `Form Request` terpisah (`php artisan make:request`) untuk metode `store` dan `update`, **bukan** `$request->validate()` langsung di controller.
- Nama file Form Request: `Store{ModelName}Request.php` dan `Update{ModelName}Request.php`.
- Lokasi: `app/Http/Requests/`.

---

## 5. Error Handling & Try-Catch Policy

- **DILARANG** menggunakan blok `try-catch (\Throwable $e)` umum di dalam Controller.
- Seluruh error tak terduga (seperti database error, unhandled logic) WAJIB didelegasikan ke Global Exception Handler di `bootstrap/app.php` agar format respons JSON konsisten dan error validasi (422), model not found (404), serta hak akses (403) tidak tertelan menjadi 500.
- Kembalikan status HTTP yang tepat: `200` (OK), `201` (Created), `400` (Bad Request), `403` (Forbidden), `404` (Not Found), `422` (Unprocessable Entity).
- Blok `try-catch` HANYA diizinkan pada kondisi berikut:
  1. **Di Service Layer**: Untuk operasi non-blocking yang tidak boleh menggagalkan transaksi utama (misalnya pencatatan audit log `AuditLogService::record(...)` atau pengiriman notifikasi/email eksternal).
  2. **Exception Bisnis Spesifik**: Di Controller/Service jika menangkap domain exception tertentu untuk memetakan respons khusus (misalnya `catch (PaymentGatewayException $e)`).
- DILARANG mengembalikan HTTP 500 manual tanpa pencatatan log via `\Log::error($e)`.

---

## 6. Soft Delete

- Setiap model yang menggunakan `SoftDeletes` harus dikembalikan statusnya lewat response saat endpoint `destroy` berhasil.
- Sediakan endpoint restore opsional `POST /resource/{id}/restore` jika diperlukan.

---

## 7. Checklist Sebelum Membuat Endpoint CRUD Baru

- [ ] Controller sudah menggunakan Form Request untuk validasi
- [ ] Endpoint `index` mendukung `search`, `sort_by`, `sort_order`, dan `per_page`
- [ ] Response mengikuti struktur envelope standar (`status`, `message`, `data`, `meta`)
- [ ] Route sudah dilindungi middleware `auth:sanctum`
- [ ] Kolom yang bisa di-sort sudah di-*whitelist* di controller

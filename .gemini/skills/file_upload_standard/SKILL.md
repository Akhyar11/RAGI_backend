---
name: file-upload-standard
description: Standar validasi, penyimpanan, dan akses file upload di Laravel untuk proyek ini, termasuk penanganan dokumen SPMB, foto profil, dan lampiran lainnya.
---

# Standar File Upload

Setiap fitur yang membutuhkan upload file **WAJIB** mengikuti standar berikut untuk keamanan, konsistensi, dan kemudahan pengelolaan.

---

## 1. Validasi File Upload

**WAJIB** memvalidasi file sebelum menyimpannya:

```php
$request->validate([
    'file' => [
        'required',
        'file',
        'mimes:pdf,jpg,jpeg,png',  // Tipe yang diizinkan
        'max:2048',                 // Maksimum 2MB (dalam kilobytes)
    ],
]);
```

### Referensi Tipe File per Konteks:

| Konteks | Tipe yang Diizinkan | Ukuran Maks |
|---|---|---|
| Foto profil / pas foto | `jpg,jpeg,png` | 1 MB |
| Dokumen (ijazah, rapor, KTP) | `pdf,jpg,jpeg,png` | 5 MB |
| Surat resmi | `pdf` | 5 MB |
| Berkas lainnya | `pdf,doc,docx,xls,xlsx` | 10 MB |

---

## 2. Struktur Direktori Penyimpanan

Simpan file di direktori yang terorganisir berdasarkan **modul** dan **tahun/bulan**:

```
storage/app/public/
├── iam/
│   └── profile_photos/
│       └── 2026/07/
├── spmb/
│   └── dokumen_pendaftaran/
│       └── 2026/07/
└── siakad/
    └── dokumen_mahasiswa/
        └── 2026/07/
```

---

## 3. Cara Menyimpan File

```php
use Illuminate\Support\Str;

// Simpan dengan nama yang aman (jangan gunakan nama asli dari user)
$fileName = Str::uuid() . '.' . $request->file('file')->getClientOriginalExtension();
$filePath = $request->file('file')->storeAs(
    'spmb/dokumen_pendaftaran/' . date('Y/m'),
    $fileName,
    'public'  // Disk: storage/app/public
);

// Simpan path relatifnya ke database
$model->update(['file_path' => $filePath]);
```

---

## 4. Cara Mengakses URL File

```php
use Illuminate\Support\Facades\Storage;

// Menghasilkan URL publik
$url = Storage::url($model->file_path);
// Output: /storage/spmb/dokumen_pendaftaran/2026/07/uuid.pdf

// Dalam API response
return response()->json([
    'data' => [
        'file_url' => $url ? asset($url) : null,
    ]
]);
```

Pastikan sudah menjalankan `php artisan storage:link`.

---

## 5. Menghapus File Lama Saat Update

```php
use Illuminate\Support\Facades\Storage;

if ($model->file_path && Storage::disk('public')->exists($model->file_path)) {
    Storage::disk('public')->delete($model->file_path);
}

// Simpan file baru
$filePath = $request->file('file')->store('modul/direktori', 'public');
$model->update(['file_path' => $filePath]);
```

---

## 6. Aturan Keamanan

- **JANGAN** pernah menyimpan file di luar direktori `storage/`. Jangan izinkan path traversal.
- **JANGAN** gunakan nama file asli dari user secara langsung — selalu generate nama unik dengan `Str::uuid()`.
- **JANGAN** menyimpan file yang dapat dieksekusi (`.php`, `.sh`, `.exe`).
- Kolom di database yang menyimpan path file diberi nama akhiran `_path` (contoh: `file_path`, `foto_path`).
- Kolom di database **TIDAK BOLEH** menyimpan URL absolut atau nama domain, hanya path relatif.

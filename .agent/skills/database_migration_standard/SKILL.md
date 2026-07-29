---
name: database-migration-standard
description: Standar konvensi penamaan, pembuatan indeks, foreign key, dan strategi rollback untuk migrasi database di seluruh ekosistem kampus terintegrasi.
---

# Standar Database Migration

Dengan total ~137 tabel di seluruh ekosistem, konsistensi dalam pembuatan migrasi sangat krusial. Ikuti semua ketentuan berikut.

---

## 1. Konvensi Penamaan Tabel

- Gunakan `snake_case` jamak dalam Bahasa Indonesia sesuai ERD.
- Prefix modul **TIDAK** diperlukan untuk tabel utama modul, tapi diperlukan untuk tabel relasi antar-modul.
- Contoh: `mahasiswa`, `dosen`, `mata_kuliah`, `krs`, `krs_detail`

---

## 2. Konvensi Penamaan Kolom

| Jenis Kolom | Konvensi | Contoh |
|---|---|---|
| Primary Key | `id` (bigint, auto increment) | `id` |
| Foreign Key | `{nama_tabel_singular}_id` | `user_id`, `role_id` |
| Boolean | Awalan `is_` atau `has_` | `is_active`, `has_paid` |
| Timestamp status | Akhiran `_at` | `verified_at`, `approved_at` |
| Kolom path file | Akhiran `_path` | `foto_path`, `file_surat_path` |
| Kolom soft delete | `deleted_at` (via `softDeletes()`) | — |
| Enum | Nama deskriptif + komentar nilai | `status`, `jenis_kelamin` |

---

## 3. Wajib Ada di Setiap Tabel

```php
// Tabel yang menyimpan data master/transaksi:
$table->id();
// ... kolom-kolom lain
$table->timestamps();    // created_at & updated_at
$table->softDeletes();   // deleted_at (jika data penting dan tidak boleh benar-benar terhapus)
```

Tabel pivot/relasi cukup:
```php
$table->id();
$table->timestamp('created_at')->nullable();
// Tidak perlu updated_at dan deleted_at
```

---

## 4. Strategi Indeks

**WAJIB** menambahkan indeks pada kolom yang sering di-query:

```php
// Unique constraint (akan juga membuat index)
$table->string('email')->unique();
$table->string('nim')->unique();

// Index biasa untuk kolom FK yang sering di-JOIN
$table->foreignId('user_id')->constrained()->index();

// Composite index untuk query kombinasi
$table->index(['modul', 'action', 'created_at']); // Untuk audit_logs
$table->unique(['krs_id', 'kelas_id']);            // Business guard di krs_detail
```

---

## 5. Konvensi Foreign Key

Selalu gunakan `constrained()` dengan `onDelete` yang tepat:

```php
// Data anak IKUT TERHAPUS jika parent dihapus
$table->foreignId('gelombang_id')->constrained('gelombang_penerimaan')->onDelete('cascade');

// Data anak SET NULL jika parent dihapus (untuk optional FK)
$table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null');

// Data anak TIDAK BOLEH dihapus jika masih ada referensi
$table->foreignId('program_studi_id')->constrained()->onDelete('restrict');
```

---

## 6. Strategi Rollback yang Aman

Urutan `Schema::dropIfExists` di method `down()` **WAJIB** kebalikan dari urutan pembuatan di `up()`:

```php
public function down(): void
{
    // Hapus tabel yang punya FK duluan
    Schema::dropIfExists('role_permissions');
    Schema::dropIfExists('user_roles');
    // Baru hapus tabel induk
    Schema::dropIfExists('permissions');
    Schema::dropIfExists('roles');
}
```

---

## 7. Konvensi Tipe Data

| Data | Tipe yang Digunakan |
|---|---|
| Uang / Biaya | `decimal(15, 2)` — bukan float |
| Nilai akademik | `decimal(5, 2)` |
| IPK / IPS | `decimal(4, 2)` |
| Teks panjang | `text` |
| Konfigurasi JSON | `json` |
| Nomor HP | `string(20)` |
| NIK / NIM / NIDN | `string(20)` — bukan integer |

---

## 8. Contoh Migrasi Lengkap yang Ideal

```php
Schema::create('mahasiswa', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('program_studi_id')->constrained()->onDelete('restrict');
    $table->string('nim', 20)->unique();
    $table->string('nama_lengkap', 100);
    $table->string('nik', 20)->unique()->nullable();
    $table->date('tanggal_lahir');
    $table->enum('jenis_kelamin', ['L', 'P']);
    $table->enum('status', ['aktif', 'cuti', 'mangkir', 'dropout', 'lulus'])->default('aktif');
    $table->integer('angkatan')->index();
    $table->decimal('ipk', 4, 2)->default(0.00);
    $table->timestamps();
    $table->softDeletes();

    $table->index(['program_studi_id', 'angkatan', 'status']);
});
```

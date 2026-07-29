---
name: unit-testing
description: Standard operating procedures and guidelines for creating unit tests in Laravel.
---

# Pedoman Unit Testing di Laravel

Setiap kali Anda diminta untuk membuat atau memperbaiki pengujian (*unit testing*) di proyek ini, ikuti pedoman berikut dengan ketat:

1. **Lokasi Pengujian**: 
   - Letakkan pengujian untuk API di `tests/Feature/`.
   - Letakkan pengujian spesifik fungsi internal (tanpa rute HTTP) di `tests/Unit/`.
2. **Setup Database**: 
   - Selalu impor dan gunakan trait `use Illuminate\Foundation\Testing\RefreshDatabase;` pada setiap kelas *Feature Test* yang memerlukan operasi ke database.
3. **Data Dummy (Factories)**: 
   - Sebelum menggunakan `Model::factory()->create()`, perhatikan definisi Factory dan pastikan ia memiliki `state` atau susunan atribut yang valid. 
   - Jangan melakukan *hardcode* nilai yang semestinya dibuat dinamis oleh Faker kecuali diperlukan untuk validasi `assertDatabaseHas`.
4. **Validasi Respons**: 
   - Pastikan selalu memverifikasi status HTTP balikan, contoh: `$response->assertStatus(200)`.
   - Uji juga kerangka balikan JSON menggunakan method `$response->assertJsonStructure([...])`.
5. **Verifikasi Database**: 
   - Untuk metode `POST`, `PUT`, atau `DELETE`, selalu verifikasi apakah data benar-benar tersimpan/terubah/terhapus di database menggunakan `$this->assertDatabaseHas()` atau `$this->assertDatabaseMissing()`.

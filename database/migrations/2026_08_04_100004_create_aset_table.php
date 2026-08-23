<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sinapra_aset', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kategori_id')->constrained('sinapra_kategori_aset')->onDelete('restrict');
            $table->foreignId('ruangan_id')->nullable()->constrained('sinapra_ruangan')->onDelete('set null');
            $table->string('kode_aset', 100)->unique();
            $table->string('nama', 150);
            $table->string('merk', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->date('tanggal_perolehan')->nullable();
            $table->decimal('harga_perolehan', 15, 2)->default(0.00);
            $table->decimal('nilai_buku', 15, 2)->default(0.00);
            $table->enum('kondisi', ['baik', 'rusak_ringan', 'rusak_berat', 'hilang'])->default('baik');
            $table->enum('status', ['tersedia', 'dipinjam', 'maintenance', 'dihapus'])->default('tersedia');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kategori_id', 'ruangan_id', 'kondisi', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sinapra_aset');
    }
};

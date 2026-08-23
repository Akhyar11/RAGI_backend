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
        Schema::create('sinapra_kategori_aset', function (Blueprint $table) {
            $table->id();
            $table->foreignId('induk_id')->nullable()->constrained('sinapra_kategori_aset')->onDelete('set null');
            $table->string('kode', 50)->unique();
            $table->string('nama', 100);
            $table->integer('masa_manfaat_tahun')->nullable();
            $table->decimal('tarif_penyusutan_persen', 5, 2)->nullable();
            $table->timestamps();

            $table->index(['induk_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sinapra_kategori_aset');
    }
};

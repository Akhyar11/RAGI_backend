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
        Schema::create('sinapra_master_kategori_bhp', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama', 150);
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('urutan')->default(1);
            $table->timestamps();

            $table->index(['is_active', 'urutan']);
            $table->index('nama');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sinapra_master_kategori_bhp');
    }
};

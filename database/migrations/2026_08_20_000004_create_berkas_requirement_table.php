<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persyaratan berkas per jalur masuk — menentukan dokumen wajib saat finalisasi pendaftaran.
     */
    public function up(): void
    {
        Schema::create('berkas_requirement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jalur_masuk_id')->constrained('jalur_masuk')->cascadeOnDelete();
            $table->string('jenis_dokumen', 100);
            $table->string('label');
            $table->boolean('wajib')->default(false);
            $table->integer('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['jalur_masuk_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('berkas_requirement');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan field detail RPS:
 * - `bahan_kajian_mk` (Textarea)
 * - `mata_kuliah_syarat` (Input text)
 * - `jenis_pembelajaran` (Pilihan jenis pembelajaran, mis. Luring, Daring, Hybrid)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siakad_rps', function (Blueprint $table) {
            $table->text('bahan_kajian_mk')->nullable()->after('deskripsi_singkat');
            $table->string('mata_kuliah_syarat', 255)->nullable()->default('-')->after('bahan_kajian_mk');
            $table->string('jenis_pembelajaran', 100)->nullable()->after('mata_kuliah_syarat');
        });
    }

    public function down(): void
    {
        Schema::table('siakad_rps', function (Blueprint $table) {
            $table->dropColumn(['bahan_kajian_mk', 'mata_kuliah_syarat', 'jenis_pembelajaran']);
        });
    }
};

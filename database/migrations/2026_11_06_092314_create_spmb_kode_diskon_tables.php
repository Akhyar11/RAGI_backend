<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spmb_kode_diskon', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama', 150);
            $table->string('tipe_diskon', 20)->comment('persen atau nominal');
            $table->decimal('nilai', 15, 2);
            $table->decimal('maksimum_potongan', 15, 2)->nullable();
            $table->unsignedInteger('batas_penggunaan')->nullable();
            $table->date('berlaku_mulai')->nullable();
            $table->date('berlaku_sampai')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'berlaku_mulai', 'berlaku_sampai'], 'spmb_kode_diskon_validity_idx');
        });

        Schema::create('spmb_kode_diskon_penggunaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kode_diskon_id')->constrained('spmb_kode_diskon')->cascadeOnDelete();
            $table->foreignId('pendaftaran_id')->unique()->constrained('spmb_pendaftaran_calon_mhs')->cascadeOnDelete();
            $table->string('kode_diskon', 50)->comment('Snapshot kode saat digunakan');
            $table->decimal('subtotal_komponen', 15, 2);
            $table->decimal('nominal_diskon', 15, 2);
            $table->foreignId('tagihan_id')->nullable()->constrained('sikeu_tagihan_mahasiswa')->nullOnDelete();
            $table->timestamp('digunakan_at');
            $table->timestamps();
            $table->index(['kode_diskon_id', 'digunakan_at'], 'spmb_kode_diskon_penggunaan_count_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spmb_kode_diskon_penggunaan');
        Schema::dropIfExists('spmb_kode_diskon');
    }
};

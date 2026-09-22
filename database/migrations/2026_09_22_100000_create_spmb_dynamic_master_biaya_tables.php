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
        // 1. Master Komponen Biaya SPMB (Dinamis)
        if (!Schema::hasTable('spmb_master_komponen_biaya')) {
            Schema::create('spmb_master_komponen_biaya', function (Blueprint $table) {
                $table->id();
                $table->string('kode', 50)->unique();
                $table->string('nama', 150);
                $table->string('kategori', 50)->default('daftar_ulang')->comment('pendaftaran, daftar_ulang, perkuliahan, lainnya');
                $table->boolean('tipe_potongan')->default(false)->comment('true jika komponen berupa potongan/diskon');
                $table->integer('urutan')->default(1);
                $table->boolean('is_active')->default(true);
                $table->text('keterangan')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 2. Master Biaya SPMB (Header per TA, Prodi, Jalur Masuk)
        if (!Schema::hasTable('spmb_master_biaya')) {
            Schema::create('spmb_master_biaya', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tahun_akademik_id')->index();
                $table->unsignedBigInteger('program_studi_id')->index();
                $table->unsignedBigInteger('jalur_masuk_id')->nullable()->index();
                $table->decimal('total_biaya', 15, 2)->default(0.00);
                $table->boolean('is_active')->default(true);
                $table->text('keterangan')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tahun_akademik_id', 'program_studi_id', 'jalur_masuk_id'], 'spmb_master_biaya_unique_idx');
            });
        }

        // 3. Detail Item Nominal per Komponen Biaya
        if (!Schema::hasTable('spmb_master_biaya_item')) {
            Schema::create('spmb_master_biaya_item', function (Blueprint $table) {
                $table->id();
                $table->foreignId('master_biaya_id')->constrained('spmb_master_biaya')->cascadeOnDelete();
                $table->foreignId('komponen_biaya_id')->constrained('spmb_master_komponen_biaya')->cascadeOnDelete();
                $table->decimal('nominal', 15, 2)->default(0.00);
                $table->string('keterangan', 255)->nullable();
                $table->timestamps();

                $table->unique(['master_biaya_id', 'komponen_biaya_id'], 'spmb_biaya_item_unique_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spmb_master_biaya_item');
        Schema::dropIfExists('spmb_master_biaya');
        Schema::dropIfExists('spmb_master_komponen_biaya');
    }
};

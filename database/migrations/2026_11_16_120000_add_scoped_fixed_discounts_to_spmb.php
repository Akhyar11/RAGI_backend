<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spmb_kode_diskon', function (Blueprint $table): void {
            $table->index('tipe_diskon', 'spmb_kode_diskon_tipe_idx');
        });

        Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table): void {
            $table->string('kode_diskon', 50)->nullable()->after('used_referral_code');
        });

        Schema::create('spmb_kode_diskon_jalur_masuk', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kode_diskon_id')->constrained('spmb_kode_diskon')->cascadeOnDelete();
            $table->foreignId('jalur_masuk_id')->constrained('spmb_jalur_masuk')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['kode_diskon_id', 'jalur_masuk_id'], 'spmb_kode_diskon_jalur_unique');
        });

        Schema::create('spmb_kode_diskon_jenis_pendaftaran', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kode_diskon_id')->constrained('spmb_kode_diskon')->cascadeOnDelete();
            $table->foreignId('master_tipe_jalur_id')->constrained('core_master_tipe_jalur')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['kode_diskon_id', 'master_tipe_jalur_id'], 'spmb_kode_diskon_tipe_jalur_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spmb_kode_diskon_jenis_pendaftaran');
        Schema::dropIfExists('spmb_kode_diskon_jalur_masuk');

        Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table): void {
            $table->dropColumn('kode_diskon');
        });

        Schema::table('spmb_kode_diskon', function (Blueprint $table): void {
            $table->dropIndex('spmb_kode_diskon_tipe_idx');
        });
    }
};
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
        if (!Schema::hasTable('spmb_template_surat')) {
            Schema::create('spmb_template_surat', function (Blueprint $table) {
                $table->id();
                $table->string('kode', 50)->unique();
                $table->string('nama', 150);
                $table->string('jenis_surat', 50)->default('sk_lulus');
                $table->foreignId('jalur_masuk_id')->nullable()->constrained('spmb_jalur_masuk')->nullOnDelete();
                $table->foreignId('gelombang_id')->nullable()->constrained('spmb_gelombang_penerimaan')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->string('kop_nama_institusi', 255)->nullable();
                $table->string('kop_nama_sub', 255)->nullable();
                $table->text('kop_alamat_kontak')->nullable();
                $table->string('format_nomor_surat', 150)->nullable();
                $table->string('judul_surat', 255)->nullable();
                $table->text('teks_pembuka')->nullable();
                $table->text('teks_keputusan')->nullable();
                $table->text('petunjuk_daftar_ulang')->nullable();
                $table->string('kota_penetapan', 100)->nullable();
                $table->string('nama_penandatangan', 255)->nullable();
                $table->string('jabatan_penandatangan', 255)->nullable();
                $table->string('nip_penandatangan', 100)->nullable();
                $table->text('catatan_kaki')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spmb_template_surat');
    }
};

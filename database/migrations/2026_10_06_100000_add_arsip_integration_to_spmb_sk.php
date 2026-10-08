<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Integrasi penerbitan SK Tanda Lulus SPMB dengan modul Arsip:
     * - Template surat menyimpan referensi dinamis ke Master Modul, Master Klasifikasi Surat, dan Master Kode Unit.
     * - Pendaftaran menyimpan nomor SK definitif, berkas PDF terarsip, serta request nomor arsip terkait.
     */
    public function up(): void
    {
        Schema::table('spmb_template_surat', function (Blueprint $table) {
            $table->foreignId('module_id')->nullable()->after('jenis_surat')
                ->constrained('core_modules')->onDelete('set null');
            $table->foreignId('klasifikasi_surat_id')->nullable()->after('module_id')
                ->constrained('core_arsip_klasifikasi')->onDelete('set null');
            $table->foreignId('unit_surat_id')->nullable()->after('klasifikasi_surat_id')
                ->constrained('core_arsip_klasifikasi')->onDelete('set null');
        });

        Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table) {
            $table->string('nomor_sk', 150)->nullable()->after('nim');
            $table->string('sk_file_path')->nullable()->after('nomor_sk');
            $table->foreignId('sk_arsip_request_id')->nullable()->after('sk_file_path')
                ->constrained('core_arsip_request_nomor')->onDelete('set null');
            $table->timestamp('sk_generated_at')->nullable()->after('sk_arsip_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sk_arsip_request_id');
            $table->dropColumn(['nomor_sk', 'sk_file_path', 'sk_generated_at']);
        });

        Schema::table('spmb_template_surat', function (Blueprint $table) {
            $table->dropConstrainedForeignId('module_id');
            $table->dropConstrainedForeignId('klasifikasi_surat_id');
            $table->dropConstrainedForeignId('unit_surat_id');
        });
    }
};

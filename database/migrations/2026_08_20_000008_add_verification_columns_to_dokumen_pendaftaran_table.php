<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom verifikasi & relasi requirement ke dokumen_pendaftaran
     * (bagian dari penggabungan tabel pendaftaran_berkas → dokumen_pendaftaran).
     */
    public function up(): void
    {
        Schema::table('spmb_dokumen_pendaftaran', function (Blueprint $table) {
            $table->foreignId('berkas_requirement_id')->nullable()->after('pendaftaran_id')
                ->constrained('spmb_berkas_requirement')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->after('is_verified')
                ->constrained('core_users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('spmb_dokumen_pendaftaran', function (Blueprint $table) {
            $table->dropConstrainedForeignId('berkas_requirement_id');
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn('verified_at');
        });
    }
};

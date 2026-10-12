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
        Schema::table('core_arsip_nomor_surat', function (Blueprint $table) {
            $table->foreignId('pegawai_id')
                ->nullable()
                ->after('request_id')
                ->constrained('simpeg_pegawai')
                ->onDelete('set null');

            $table->string('file_surat_path')
                ->nullable()
                ->after('kop_surat_id');

            $table->timestamp('file_surat_uploaded_at')
                ->nullable()
                ->after('file_surat_path');

            $table->foreignId('file_surat_uploaded_by')
                ->nullable()
                ->after('file_surat_uploaded_at')
                ->constrained('core_users')
                ->onDelete('set null');

            $table->index(['pegawai_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('core_arsip_nomor_surat', function (Blueprint $table) {
            $table->dropForeign(['core_arsip_nomor_surat_pegawai_id_foreign']);
            $table->dropForeign(['core_arsip_nomor_surat_file_surat_uploaded_by_foreign']);
            $table->dropIndex(['core_arsip_nomor_surat_pegawai_id_status_index']);
            $table->dropColumn([
                'pegawai_id',
                'file_surat_path',
                'file_surat_uploaded_at',
                'file_surat_uploaded_by',
            ]);
        });
    }
};

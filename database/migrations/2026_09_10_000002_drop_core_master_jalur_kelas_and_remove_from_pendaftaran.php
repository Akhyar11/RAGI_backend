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
        if (Schema::hasTable('spmb_pendaftaran_calon_mhs') && Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'master_jalur_kelas_id')) {
            Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table) {
                try {
                    $table->dropForeign(['master_jalur_kelas_id']);
                } catch (\Throwable $e) {
                    // Foreign key already dropped or named differently
                }
                $table->dropColumn('master_jalur_kelas_id');
            });
        }

        if (Schema::hasTable('core_master_jalur_kelas')) {
            Schema::dropIfExists('core_master_jalur_kelas');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('core_master_jalur_kelas')) {
            Schema::create('core_master_jalur_kelas', function (Blueprint $table) {
                $table->id();
                $table->string('kode')->unique();
                $table->string('nama_jalur');
                $table->text('deskripsi')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('spmb_pendaftaran_calon_mhs') && !Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'master_jalur_kelas_id')) {
            Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table) {
                $table->foreignId('master_jalur_kelas_id')->nullable()->after('master_tipe_jalur_id')->constrained('core_master_jalur_kelas')->nullOnDelete();
            });
        }
    }
};

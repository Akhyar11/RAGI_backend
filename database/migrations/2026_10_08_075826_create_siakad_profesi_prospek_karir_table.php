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
        // 1. Tabel Profesi / Prospek Karir
        if (!Schema::hasTable('siakad_profesi_karir')) {
            Schema::create('siakad_profesi_karir', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_studi_id')->nullable()->constrained('siakad_program_studi')->nullOnDelete();
                $table->string('nama', 255);
                $table->string('sumber', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 2. Tambah kurikulum_id pada profil lulusan jika belum ada
        if (Schema::hasTable('siakad_profil_lulusan') && !Schema::hasColumn('siakad_profil_lulusan', 'kurikulum_id')) {
            Schema::table('siakad_profil_lulusan', function (Blueprint $table) {
                $table->foreignId('kurikulum_id')->nullable()->after('program_studi_id')->constrained('siakad_kurikulum')->nullOnDelete();
            });
        }

        // 3. Tambah kurikulum_id dan jenis_cpls JSON pada CPL jika belum ada
        if (Schema::hasTable('siakad_cpl')) {
            Schema::table('siakad_cpl', function (Blueprint $table) {
                if (!Schema::hasColumn('siakad_cpl', 'kurikulum_id')) {
                    $table->foreignId('kurikulum_id')->nullable()->after('program_studi_id')->constrained('siakad_kurikulum')->nullOnDelete();
                }
                if (!Schema::hasColumn('siakad_cpl', 'jenis_list')) {
                    $table->json('jenis_list')->nullable()->after('kategori');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siakad_profesi_karir');
    }
};


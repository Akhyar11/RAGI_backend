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
        if (!Schema::hasTable('simpeg_master_golongan_pangkat')) {
            Schema::create('simpeg_master_golongan_pangkat', function (Blueprint $table) {
                $table->id();
                $table->string('kode', 50)->unique();
                $table->string('nama', 100);
                $table->string('pangkat', 100)->nullable();
                $table->string('ruang', 20)->nullable();
                $table->integer('urutan')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('simpeg_jabatan_fungsional_akademik')) {
            Schema::table('simpeg_jabatan_fungsional_akademik', function (Blueprint $table) {
                if (!Schema::hasColumn('simpeg_jabatan_fungsional_akademik', 'golongan_pangkat_id')) {
                    $table->foreignId('golongan_pangkat_id')->nullable()->after('golongan')->constrained('simpeg_master_golongan_pangkat')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('simpeg_jabatan_fungsional_akademik')) {
            Schema::table('simpeg_jabatan_fungsional_akademik', function (Blueprint $table) {
                if (Schema::hasColumn('simpeg_jabatan_fungsional_akademik', 'golongan_pangkat_id')) {
                    $table->dropForeign(['golongan_pangkat_id']);
                    $table->dropColumn('golongan_pangkat_id');
                }
            });
        }

        Schema::dropIfExists('simpeg_master_golongan_pangkat');
    }
};

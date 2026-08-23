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
        if (Schema::hasTable('sikeu_jenis_biaya') && !Schema::hasColumn('jenis_biaya', 'nominal_standar')) {
            Schema::table('sikeu_jenis_biaya', function (Blueprint $table) {
                $table->decimal('nominal_standar', 15, 2)->default(0)->after('tipe');
            });
        }



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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sikeu_jenis_biaya') && Schema::hasColumn('jenis_biaya', 'nominal_standar')) {
            Schema::table('sikeu_jenis_biaya', function (Blueprint $table) {
                $table->dropColumn('nominal_standar');
            });
        }



        Schema::dropIfExists('core_master_jalur_kelas');
    }
};

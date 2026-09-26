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
        Schema::table('simpeg_pegawai', function (Blueprint $table) {
            if (!Schema::hasColumn('simpeg_pegawai', 'nama_bank')) {
                $table->string('nama_bank', 50)->nullable()->after('telepon');
            }
            if (!Schema::hasColumn('simpeg_pegawai', 'nomor_rekening')) {
                $table->string('nomor_rekening', 50)->nullable()->after('nama_bank');
            }
            if (!Schema::hasColumn('simpeg_pegawai', 'nama_rekening')) {
                $table->string('nama_rekening', 100)->nullable()->after('nomor_rekening');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_pegawai', function (Blueprint $table) {
            $table->dropColumn(['nama_bank', 'nomor_rekening', 'nama_rekening']);
        });
    }
};

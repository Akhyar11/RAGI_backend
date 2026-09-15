<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('simpeg_riwayat_pendidikan_pegawai', function (Blueprint $table) {
            $table->string('jenjang', 30)->change();
        });
    }

    public function down(): void
    {
        Schema::table('simpeg_riwayat_pendidikan_pegawai', function (Blueprint $table) {
            $table->string('jenjang', 30)->change();
        });
    }
};

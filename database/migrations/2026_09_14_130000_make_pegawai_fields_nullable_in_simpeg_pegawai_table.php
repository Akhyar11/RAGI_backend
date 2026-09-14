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
            $table->string('jenis_kelamin')->nullable()->change();
            $table->string('jenis_pegawai')->nullable()->change();
            $table->string('status_kepegawaian')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_pegawai', function (Blueprint $table) {
            $table->string('jenis_kelamin')->nullable(false)->change();
            $table->string('jenis_pegawai')->nullable(false)->change();
            $table->string('status_kepegawaian')->nullable(false)->change();
        });
    }
};

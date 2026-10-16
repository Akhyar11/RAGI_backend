<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siakad_mahasiswa', function (Blueprint $table) {
            $table->string('kelas', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('siakad_mahasiswa', function (Blueprint $table) {
            $table->string('kelas', 10)->nullable()->change();
        });
    }
};

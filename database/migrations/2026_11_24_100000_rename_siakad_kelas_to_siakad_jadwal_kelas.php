<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('siakad_kelas') && !Schema::hasTable('siakad_jadwal_kelas')) {
            Schema::rename('siakad_kelas', 'siakad_jadwal_kelas');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('siakad_jadwal_kelas') && !Schema::hasTable('siakad_kelas')) {
            Schema::rename('siakad_jadwal_kelas', 'siakad_kelas');
        }
    }
};

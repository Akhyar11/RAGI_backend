<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('siakad_rps_referensi')) {
            Schema::table('siakad_rps_referensi', function (Blueprint $table) {
                $table->string('tipe', 50)->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('siakad_rps_referensi')) {
            Schema::table('siakad_rps_referensi', function (Blueprint $table) {
                $table->enum('tipe', ['bentuk', 'metode', 'kriteria', 'komponen'])->change();
            });
        }
    }
};

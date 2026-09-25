<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siakad_konversi_transfer_detail', function (Blueprint $table) {
            $table->string('status', 20)->default('diakui')->after('nilai_huruf_asal');
            $table->string('catatan_penolakan', 500)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('siakad_konversi_transfer_detail', function (Blueprint $table) {
            $table->dropColumn(['status', 'catatan_penolakan']);
        });
    }
};

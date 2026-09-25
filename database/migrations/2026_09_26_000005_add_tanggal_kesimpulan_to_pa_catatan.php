<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siakad_pa_catatan', function (Blueprint $table) {
            $table->date('tanggal_bimbingan')->nullable()->after('tahun_akademik_id');
            $table->text('kesimpulan')->nullable()->after('isi');
        });
    }

    public function down(): void
    {
        Schema::table('siakad_pa_catatan', function (Blueprint $table) {
            $table->dropColumn(['tanggal_bimbingan', 'kesimpulan']);
        });
    }
};

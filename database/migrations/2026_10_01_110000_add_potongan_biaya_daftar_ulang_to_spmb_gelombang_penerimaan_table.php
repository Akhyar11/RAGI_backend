<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spmb_gelombang_penerimaan', function (Blueprint $table) {
            $table->decimal('potongan_biaya_daftar_ulang', 5, 2)
                ->default(0)
                ->after('biaya_pendaftaran')
                ->comment('Persentase potongan biaya daftar ulang (0-100%)');
        });
    }

    public function down(): void
    {
        Schema::table('spmb_gelombang_penerimaan', function (Blueprint $table) {
            $table->dropColumn('potongan_biaya_daftar_ulang');
        });
    }
};

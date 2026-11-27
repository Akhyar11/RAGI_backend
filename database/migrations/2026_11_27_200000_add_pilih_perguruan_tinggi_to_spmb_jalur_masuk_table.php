<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('spmb_jalur_masuk', 'pilih_perguruan_tinggi')) {
            return;
        }

        Schema::table('spmb_jalur_masuk', function (Blueprint $table) {
            $table->boolean('pilih_perguruan_tinggi')->default(false)->after('ada_wawancara');
        });

        // Jalur yang sudah ada tetap dapat memilih perguruan tinggi seperti sebelumnya.
        DB::table('spmb_jalur_masuk')->update(['pilih_perguruan_tinggi' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('spmb_jalur_masuk', 'pilih_perguruan_tinggi')) {
            Schema::table('spmb_jalur_masuk', function (Blueprint $table) {
                $table->dropColumn('pilih_perguruan_tinggi');
            });
        }
    }
};

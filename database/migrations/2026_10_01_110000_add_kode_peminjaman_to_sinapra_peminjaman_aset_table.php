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
        Schema::table('sinapra_peminjaman_aset', function (Blueprint $table) {
            if (!Schema::hasColumn('sinapra_peminjaman_aset', 'kode_peminjaman')) {
                $table->string('kode_peminjaman', 50)->nullable()->after('id')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sinapra_peminjaman_aset', function (Blueprint $table) {
            if (Schema::hasColumn('sinapra_peminjaman_aset', 'kode_peminjaman')) {
                $table->dropColumn('kode_peminjaman');
            }
        });
    }
};

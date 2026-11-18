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
        Schema::table('sinapra_aset', function (Blueprint $table) {
            if (!Schema::hasColumn('sinapra_aset', 'nomor_urut')) {
                $table->unsignedInteger('nomor_urut')->nullable()->after('kode_aset')->index();
            }
            if (!Schema::hasColumn('sinapra_aset', 'spesifikasi')) {
                $table->text('spesifikasi')->nullable()->after('is_lab_asset');
            }
            if (!Schema::hasColumn('sinapra_aset', 'keterangan')) {
                $table->text('keterangan')->nullable()->after('spesifikasi');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sinapra_aset', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('sinapra_aset', 'nomor_urut')) {
                $cols[] = 'nomor_urut';
            }
            if (Schema::hasColumn('sinapra_aset', 'spesifikasi')) {
                $cols[] = 'spesifikasi';
            }
            if (Schema::hasColumn('sinapra_aset', 'keterangan')) {
                $cols[] = 'keterangan';
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siakad_konversi_transfer', function (Blueprint $table) {
            $table->foreignId('disetujui_pa_oleh')->nullable()->after('diproses_oleh')
                ->constrained('core_users')->nullOnDelete();
            $table->timestamp('disetujui_pa_pada')->nullable()->after('disetujui_pa_oleh');
            $table->foreignId('disetujui_kaprodi_oleh')->nullable()->after('disetujui_pa_pada')
                ->constrained('core_users')->nullOnDelete();
            $table->timestamp('disetujui_kaprodi_pada')->nullable()->after('disetujui_kaprodi_oleh');
        });

        if (DB::getDriverName() !== 'sqlite' && Schema::hasTable('siakad_konversi_transfer')) {
            DB::statement(
                "ALTER TABLE siakad_konversi_transfer MODIFY COLUMN status " .
                "ENUM('draft','diajukan','menunggu_pa','menunggu_kaprodi','disetujui','ditolak') " .
                "NOT NULL DEFAULT 'draft'"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite' && Schema::hasTable('siakad_konversi_transfer')) {
            DB::table('siakad_konversi_transfer')
                ->whereIn('status', ['menunggu_pa', 'menunggu_kaprodi'])
                ->update(['status' => 'diajukan']);
            DB::statement(
                "ALTER TABLE siakad_konversi_transfer MODIFY COLUMN status " .
                "ENUM('draft','diajukan','disetujui','ditolak') NOT NULL DEFAULT 'draft'"
            );
        }

        Schema::table('siakad_konversi_transfer', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disetujui_pa_oleh');
            $table->dropColumn('disetujui_pa_pada');
            $table->dropConstrainedForeignId('disetujui_kaprodi_oleh');
            $table->dropColumn('disetujui_kaprodi_pada');
        });
    }
};

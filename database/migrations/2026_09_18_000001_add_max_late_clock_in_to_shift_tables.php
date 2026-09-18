<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah konfigurasi batas maksimal keterlambatan clock-in
     * (cerminan max_early_clock_in_minutes) + override per-hari.
     */
    public function up(): void
    {
        Schema::table('simpeg_shift_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('simpeg_shift_templates', 'max_late_clock_in_minutes')) {
                $table->integer('max_late_clock_in_minutes')->default(240)->after('max_early_clock_in_minutes');
            }
        });

        Schema::table('simpeg_shift_schedule_days', function (Blueprint $table) {
            if (!Schema::hasColumn('simpeg_shift_schedule_days', 'max_late_clock_in_minutes')) {
                $table->integer('max_late_clock_in_minutes')->nullable()->after('early_leave_tolerance_minutes');
            }
            if (!Schema::hasColumn('simpeg_shift_schedule_days', 'max_early_clock_in_minutes')) {
                $table->integer('max_early_clock_in_minutes')->nullable()->after('max_late_clock_in_minutes');
            }
        });

        // Default global agar instalasi lama langsung punya nilai tanpa re-seed.
        // 240 menit = cerminan batas buka-awal, 0 = tanpa batas (selalu terima telat).
        try {
            \App\Models\SystemSetting::set(
                'max_late_clock_in_minutes',
                \App\Models\SystemSetting::get('max_late_clock_in_minutes', '240'),
                'Batas maksimal keterlambatan presensi masuk setelah jam shift, 0 = tanpa batas (dalam menit)'
            );
        } catch (\Throwable $e) {
            // Abaikan bila tabel settings belum ada pada urutan migrasi fresh.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_shift_schedule_days', function (Blueprint $table) {
            if (Schema::hasColumn('simpeg_shift_schedule_days', 'max_early_clock_in_minutes')) {
                $table->dropColumn('max_early_clock_in_minutes');
            }
            if (Schema::hasColumn('simpeg_shift_schedule_days', 'max_late_clock_in_minutes')) {
                $table->dropColumn('max_late_clock_in_minutes');
            }
        });

        Schema::table('simpeg_shift_templates', function (Blueprint $table) {
            if (Schema::hasColumn('simpeg_shift_templates', 'max_late_clock_in_minutes')) {
                $table->dropColumn('max_late_clock_in_minutes');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah konfigurasi batas scan pulang (clock out window) sebelum & sesudah jam shift
     * pada template shift dan hari jadwal kerja.
     */
    public function up(): void
    {
        Schema::table('simpeg_shift_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('simpeg_shift_templates', 'max_early_clock_out_minutes')) {
                $table->integer('max_early_clock_out_minutes')->nullable()->after('max_late_clock_in_minutes');
            }
            if (!Schema::hasColumn('simpeg_shift_templates', 'max_late_clock_out_minutes')) {
                $table->integer('max_late_clock_out_minutes')->default(240)->after('max_early_clock_out_minutes');
            }
        });

        Schema::table('simpeg_shift_schedule_days', function (Blueprint $table) {
            if (!Schema::hasColumn('simpeg_shift_schedule_days', 'max_early_clock_out_minutes')) {
                $table->integer('max_early_clock_out_minutes')->nullable()->after('max_early_clock_in_minutes');
            }
            if (!Schema::hasColumn('simpeg_shift_schedule_days', 'max_late_clock_out_minutes')) {
                $table->integer('max_late_clock_out_minutes')->nullable()->after('max_early_clock_out_minutes');
            }
        });

        // Inisialisasi default setting global bila SystemSetting tersedia
        try {
            \App\Models\SystemSetting::set(
                'max_early_clock_out_minutes',
                \App\Models\SystemSetting::get('max_early_clock_out_minutes', '0'),
                'Batas buka presensi pulang lebih awal sebelum jam pulang shift, 0 = otomatis setelah batas masuk berakhir (dalam menit)'
            );
            \App\Models\SystemSetting::set(
                'max_late_clock_out_minutes',
                \App\Models\SystemSetting::get('max_late_clock_out_minutes', '240'),
                'Batas maksimal presensi pulang terecord setelah jam pulang shift, 0 = tanpa batas (dalam menit)'
            );
        } catch (\Throwable $e) {
            // Abaikan bila tabel settings belum ada pada tahapan migrasi
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_shift_schedule_days', function (Blueprint $table) {
            if (Schema::hasColumn('simpeg_shift_schedule_days', 'max_late_clock_out_minutes')) {
                $table->dropColumn('max_late_clock_out_minutes');
            }
            if (Schema::hasColumn('simpeg_shift_schedule_days', 'max_early_clock_out_minutes')) {
                $table->dropColumn('max_early_clock_out_minutes');
            }
        });

        Schema::table('simpeg_shift_templates', function (Blueprint $table) {
            if (Schema::hasColumn('simpeg_shift_templates', 'max_late_clock_out_minutes')) {
                $table->dropColumn('max_late_clock_out_minutes');
            }
            if (Schema::hasColumn('simpeg_shift_templates', 'max_early_clock_out_minutes')) {
                $table->dropColumn('max_early_clock_out_minutes');
            }
        });
    }
};

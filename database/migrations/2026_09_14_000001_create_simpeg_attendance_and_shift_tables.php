<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Office Locations (Lokasi & Geofence Kantor)
        if (!Schema::hasTable('simpeg_office_locations')) {
            Schema::create('simpeg_office_locations', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('address')->nullable();
                $table->decimal('latitude', 10, 8);
                $table->decimal('longitude', 11, 8);
                $table->decimal('radius_meters', 8, 2)->default(100.0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. Shift Templates (Template Shift Jam Kerja)
        if (!Schema::hasTable('simpeg_shift_templates')) {
            Schema::create('simpeg_shift_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('late_tolerance_minutes')->default(15);
                $table->integer('early_leave_tolerance_minutes')->default(15);
                $table->integer('max_early_clock_in_minutes')->default(60);
                $table->boolean('applies_national_holidays')->default(true);
                $table->timestamps();
            });
        }

        // 3. Shift Schedule Days (Jadwal Harian Senin - Minggu)
        if (!Schema::hasTable('simpeg_shift_schedule_days')) {
            Schema::create('simpeg_shift_schedule_days', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shift_template_id')->constrained('simpeg_shift_templates')->cascadeOnDelete();
                $table->tinyInteger('day_of_week'); // 0=Minggu, 1=Senin, ..., 6=Sabtu
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->time('break_start')->nullable();
                $table->time('break_end')->nullable();
                $table->boolean('is_day_off')->default(false);
                $table->integer('late_tolerance_minutes')->nullable();
                $table->integer('early_leave_tolerance_minutes')->nullable();
                $table->boolean('applies_national_holidays')->nullable();
                $table->timestamps();

                $table->unique(['shift_template_id', 'day_of_week']);
            });
        }

        // 4. National Holidays (Kalender Libur Nasional)
        if (!Schema::hasTable('simpeg_national_holidays')) {
            Schema::create('simpeg_national_holidays', function (Blueprint $table) {
                $table->id();
                $table->date('holiday_date')->unique();
                $table->string('name');
                $table->boolean('is_mass_leave')->default(false);
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // 5. Tambah kolom biometrik & relasi shift/lokasi pada simpeg_pegawai
        Schema::table('simpeg_pegawai', function (Blueprint $table) {
            if (!Schema::hasColumn('simpeg_pegawai', 'office_location_id')) {
                $table->foreignId('office_location_id')->nullable()->constrained('simpeg_office_locations')->nullOnDelete();
            }
            if (!Schema::hasColumn('simpeg_pegawai', 'shift_template_id')) {
                $table->foreignId('shift_template_id')->nullable()->constrained('simpeg_shift_templates')->nullOnDelete();
            }
            if (!Schema::hasColumn('simpeg_pegawai', 'face_embedding')) {
                $table->longText('face_embedding')->nullable();
            }
            if (!Schema::hasColumn('simpeg_pegawai', 'face_enrolled_at')) {
                $table->timestamp('face_enrolled_at')->nullable();
            }
            if (!Schema::hasColumn('simpeg_pegawai', 'consent_pdp_at')) {
                $table->timestamp('consent_pdp_at')->nullable();
            }
            if (!Schema::hasColumn('simpeg_pegawai', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });

        // 6. Sesuaikan simpeg_presensi_pegawai agar mendukung fitur lengkap Attendance
        Schema::table('simpeg_presensi_pegawai', function (Blueprint $table) {
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'office_location_id')) {
                $table->foreignId('office_location_id')->nullable()->constrained('simpeg_office_locations')->nullOnDelete();
            }
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'clock_in')) {
                $table->dateTime('clock_in')->nullable();
            }
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'clock_in_latitude')) {
                $table->decimal('clock_in_latitude', 10, 8)->nullable();
            }
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'clock_in_longitude')) {
                $table->decimal('clock_in_longitude', 11, 8)->nullable();
            }
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'clock_in_distance_meters')) {
                $table->decimal('clock_in_distance_meters', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'clock_in_accuracy')) {
                $table->decimal('clock_in_accuracy', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'clock_in_face_score')) {
                $table->decimal('clock_in_face_score', 6, 4)->nullable();
            }
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'clock_in_is_mock_location')) {
                $table->boolean('clock_in_is_mock_location')->default(false);
            }
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'clock_out')) {
                $table->dateTime('clock_out')->nullable();
            }
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'clock_out_latitude')) {
                $table->decimal('clock_out_latitude', 10, 8)->nullable();
            }
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'clock_out_longitude')) {
                $table->decimal('clock_out_longitude', 11, 8)->nullable();
            }
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'clock_out_distance_meters')) {
                $table->decimal('clock_out_distance_meters', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'clock_out_accuracy')) {
                $table->decimal('clock_out_accuracy', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'clock_out_face_score')) {
                $table->decimal('clock_out_face_score', 6, 4)->nullable();
            }
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'clock_out_is_mock_location')) {
                $table->boolean('clock_out_is_mock_location')->default(false);
            }
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'late_minutes')) {
                $table->integer('late_minutes')->default(0);
            }
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'notes')) {
                $table->text('notes')->nullable();
            }
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'is_approved_by_admin')) {
                $table->boolean('is_approved_by_admin')->nullable();
            }
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->constrained('core_users')->nullOnDelete();
            }
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'status')) {
                $table->string('status', 30)->default('hadir');
            }
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
        });

        // 7. Compatibility Views untuk nama tabel standar blueprint (attendances, office_locations, dsb.)
        // Catatan: view diskip pada SQLite (database test :memory:) karena SQLite
        // me-rebuild tabel saat ALTER TABLE, dan view yang menggantung ke
        // simpeg_pegawai menggagalkan migrasi berikutnya (nidn, nuptk, dsb.)
        // dengan error "no such table: main.simpeg_pegawai". View hanya
        // compatibility layer dan tidak dipakai oleh kode aplikasi/test.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        try {
            DB::statement('DROP VIEW IF EXISTS attendances');
            DB::statement('CREATE VIEW attendances AS SELECT id, pegawai_id AS employee_id, office_location_id, tanggal AS attendance_date, clock_in, clock_in_latitude, clock_in_longitude, clock_in_distance_meters, clock_in_accuracy, clock_in_face_score, clock_in_is_mock_location, clock_out, clock_out_latitude, clock_out_longitude, clock_out_distance_meters, clock_out_accuracy, clock_out_face_score, clock_out_is_mock_location, status_kehadiran AS status, late_minutes, rejection_reason, notes, is_approved_by_admin, approved_by, approved_at, created_at, updated_at FROM simpeg_presensi_pegawai');

            DB::statement('DROP VIEW IF EXISTS office_locations');
            DB::statement('CREATE VIEW office_locations AS SELECT * FROM simpeg_office_locations');

            DB::statement('DROP VIEW IF EXISTS shift_templates');
            DB::statement('CREATE VIEW shift_templates AS SELECT * FROM simpeg_shift_templates');

            DB::statement('DROP VIEW IF EXISTS shift_schedule_days');
            DB::statement('CREATE VIEW shift_schedule_days AS SELECT * FROM simpeg_shift_schedule_days');

            DB::statement('DROP VIEW IF EXISTS national_holidays');
            DB::statement('CREATE VIEW national_holidays AS SELECT * FROM simpeg_national_holidays');

            DB::statement('DROP VIEW IF EXISTS employees');
            DB::statement('CREATE VIEW employees AS SELECT id, user_id, office_location_id, shift_template_id, nip AS employee_code, jenis_pegawai AS position, (SELECT nama FROM simpeg_unit_kerja WHERE simpeg_unit_kerja.id = simpeg_pegawai.unit_kerja_id) AS department, telepon AS phone, face_embedding, face_enrolled_at, consent_pdp_at, is_active, created_at, updated_at FROM simpeg_pegawai');
        } catch (\Throwable $e) {
            // Jika view tidak didukung database driver, abaikan
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement('DROP VIEW IF EXISTS attendances');
            DB::statement('DROP VIEW IF EXISTS office_locations');
            DB::statement('DROP VIEW IF EXISTS shift_templates');
            DB::statement('DROP VIEW IF EXISTS shift_schedule_days');
            DB::statement('DROP VIEW IF EXISTS national_holidays');
            DB::statement('DROP VIEW IF EXISTS employees');
        } catch (\Throwable $e) {}

        Schema::dropIfExists('simpeg_shift_schedule_days');
        Schema::dropIfExists('simpeg_shift_templates');
        Schema::dropIfExists('simpeg_national_holidays');
        Schema::dropIfExists('simpeg_office_locations');
    }
};

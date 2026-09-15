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
        // SQLite: drop dependent VIEW before table rebuild (->change()).
        try {
            DB::statement('DROP VIEW IF EXISTS employees');
        } catch (\Throwable $e) {
        }

        Schema::table('simpeg_pegawai', function (Blueprint $table) {
            $table->string('jenis_kelamin')->nullable()->change();
            $table->string('jenis_pegawai')->nullable()->change();
            $table->string('status_kepegawaian')->nullable()->change();
        });

        // Recreate compatibility VIEW.
        try {
            DB::statement('CREATE VIEW employees AS SELECT id, user_id, office_location_id, shift_template_id, nip AS employee_code, jenis_pegawai AS position, (SELECT nama FROM simpeg_unit_kerja WHERE simpeg_unit_kerja.id = simpeg_pegawai.unit_kerja_id) AS department, telepon AS phone, face_embedding, face_enrolled_at, consent_pdp_at, is_active, created_at, updated_at FROM simpeg_pegawai');
        } catch (\Throwable $e) {
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement('DROP VIEW IF EXISTS employees');
        } catch (\Throwable $e) {
        }

        Schema::table('simpeg_pegawai', function (Blueprint $table) {
            $table->string('jenis_kelamin')->nullable(false)->change();
            $table->string('jenis_pegawai')->nullable(false)->change();
            $table->string('status_kepegawaian')->nullable(false)->change();
        });

        try {
            DB::statement('CREATE VIEW employees AS SELECT id, user_id, office_location_id, shift_template_id, nip AS employee_code, jenis_pegawai AS position, (SELECT nama FROM simpeg_unit_kerja WHERE simpeg_unit_kerja.id = simpeg_pegawai.unit_kerja_id) AS department, telepon AS phone, face_embedding, face_enrolled_at, consent_pdp_at, is_active, created_at, updated_at FROM simpeg_pegawai');
        } catch (\Throwable $e) {
        }
    }
};

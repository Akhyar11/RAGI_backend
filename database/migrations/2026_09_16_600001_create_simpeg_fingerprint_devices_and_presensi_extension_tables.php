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
        // 1. Master Mesin Presensi Sidik Jari / Terminal Biometrik
        if (!Schema::hasTable('simpeg_fingerprint_devices')) {
            Schema::create('simpeg_fingerprint_devices', function (Blueprint $table) {
                $table->id();
                $table->string('device_name');
                $table->string('device_code', 50)->unique();
                $table->string('ip_address', 50);
                $table->integer('port')->default(4370);
                $table->string('location')->nullable();
                $table->foreignId('office_location_id')->nullable()->constrained('simpeg_office_locations')->nullOnDelete();
                $table->string('device_model', 100)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_sync_at')->nullable();
                $table->string('last_status', 30)->default('online');
                $table->timestamps();
            });
        }

        // 2. Kolom Ekstensi Presensi Pegawai
        Schema::table('simpeg_presensi_pegawai', function (Blueprint $table) {
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'source')) {
                $table->string('source', 30)->default('mobile_gps')->nullable();
            }
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'device_id')) {
                $table->string('device_id', 50)->nullable();
            }
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'device_ip')) {
                $table->string('device_ip', 50)->nullable();
            }
            if (!Schema::hasColumn('simpeg_presensi_pegawai', 'early_leave_minutes')) {
                $table->integer('early_leave_minutes')->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_presensi_pegawai', function (Blueprint $table) {
            if (Schema::hasColumn('simpeg_presensi_pegawai', 'early_leave_minutes')) {
                $table->dropColumn('early_leave_minutes');
            }
            if (Schema::hasColumn('simpeg_presensi_pegawai', 'device_ip')) {
                $table->dropColumn('device_ip');
            }
            if (Schema::hasColumn('simpeg_presensi_pegawai', 'device_id')) {
                $table->dropColumn('device_id');
            }
            if (Schema::hasColumn('simpeg_presensi_pegawai', 'source')) {
                $table->dropColumn('source');
            }
        });

        Schema::dropIfExists('simpeg_fingerprint_devices');
    }
};

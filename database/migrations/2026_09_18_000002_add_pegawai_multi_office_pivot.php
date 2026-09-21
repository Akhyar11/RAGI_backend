<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lokasi absen tambahan per pegawai (multi-lokasi).
     * Contoh: dosen yang kantor utamanya di titik X tetapi mengajar di titik Y
     * dapat clock-in/out yang sah dari kedua titik tanpa bolak-balik.
     * Lokasi utama tetap di simpeg_pegawai.office_location_id.
     */
    public function up(): void
    {
        if (!Schema::hasTable('simpeg_pegawai_office_locations')) {
            Schema::create('simpeg_pegawai_office_locations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pegawai_id')->constrained('simpeg_pegawai')->cascadeOnDelete();
                $table->foreignId('office_location_id')->constrained('simpeg_office_locations')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['pegawai_id', 'office_location_id'], 'pegawai_office_loc_unique');
                $table->index('office_location_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_pegawai_office_locations');
    }
};

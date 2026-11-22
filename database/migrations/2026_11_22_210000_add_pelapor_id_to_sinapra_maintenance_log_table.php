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
        Schema::table('sinapra_maintenance_log', function (Blueprint $table) {
            // Relasi ke tabel user core kampus (core_users, model App\Models\User)
            $table->foreignId('pelapor_id')
                ->nullable()
                ->after('teknisi_id')
                ->constrained('core_users')
                ->onDelete('set null');
            $table->string('foto_path')->nullable()->after('deskripsi_kerusakan');
            $table->index('pelapor_id', 'idx_maintenance_log_pelapor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sinapra_maintenance_log', function (Blueprint $table) {
            $table->dropForeign(['pelapor_id']);
            $table->dropIndex('idx_maintenance_log_pelapor_id');
            $table->dropColumn(['pelapor_id', 'foto_path']);
        });
    }
};

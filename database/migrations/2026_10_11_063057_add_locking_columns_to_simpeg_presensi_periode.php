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
        Schema::table('simpeg_presensi_periode', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('total_record');
            $table->timestamp('locked_at')->nullable()->after('is_locked');
            $table->foreignId('locked_by')->nullable()->constrained('core_users')->nullOnDelete()->after('locked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_presensi_periode', function (Blueprint $table) {
            $table->dropForeign(['locked_by']);
            $table->dropColumn(['is_locked', 'locked_at', 'locked_by']);
        });
    }
};

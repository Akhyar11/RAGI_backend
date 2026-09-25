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
        Schema::table('sinapra_ruangan', function (Blueprint $table) {
            $table->foreignId('tipe_ruangan_id')
                ->nullable()
                ->after('lantai')
                ->constrained('sinapra_master_tipe_ruangan')
                ->nullOnDelete();

            $table->index('tipe_ruangan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sinapra_ruangan', function (Blueprint $table) {
            $table->dropForeign(['tipe_ruangan_id']);
            $table->dropIndex(['tipe_ruangan_id']);
            $table->dropColumn('tipe_ruangan_id');
        });
    }
};

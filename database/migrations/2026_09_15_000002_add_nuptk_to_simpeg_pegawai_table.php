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
        Schema::table('simpeg_pegawai', function (Blueprint $table) {
            $table->string('nuptk', 30)->nullable()->after('nidn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_pegawai', function (Blueprint $table) {
            $table->dropColumn('nuptk');
        });
    }
};

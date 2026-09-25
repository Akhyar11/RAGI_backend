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
        Schema::table('simpeg_jabatan_fungsional_akademik', function (Blueprint $table) {
            $table->string('golongan', 50)->default('asisten_ahli')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_jabatan_fungsional_akademik', function (Blueprint $table) {
            $table->enum('golongan', ['asisten_ahli', 'lektor', 'lektor_kepala', 'guru_besar'])->change();
        });
    }
};

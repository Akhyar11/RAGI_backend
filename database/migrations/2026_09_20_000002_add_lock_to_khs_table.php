<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siakad_khs', function (Blueprint $table) {
            if (!Schema::hasColumn('siakad_khs', 'is_locked')) {
                $table->boolean('is_locked')->default(false)->after('ipk');
            }
        });
    }

    public function down(): void
    {
        Schema::table('siakad_khs', function (Blueprint $table) {
            if (Schema::hasColumn('siakad_khs', 'is_locked')) {
                $table->dropColumn('is_locked');
            }
        });
    }
};

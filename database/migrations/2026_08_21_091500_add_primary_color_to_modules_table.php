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
        Schema::table('core_modules', function (Blueprint $table) {
            $table->string('primary_color', 20)->default('#3b82f6')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('core_modules', function (Blueprint $table) {
            $table->dropColumn('primary_color');
        });
    }
};

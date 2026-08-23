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
        Schema::table('core_users', function (Blueprint $table) {
            $table->string('email_kampus')->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('core_users', function (Blueprint $table) {
            $table->dropColumn('email_kampus');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spmb_pendaftaran_pihak_ketiga', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('nama')->constrained('core_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('spmb_pendaftaran_pihak_ketiga', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};

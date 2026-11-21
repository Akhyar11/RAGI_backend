<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('siakad_program_studi', 'is_prefix_manual')) {
            Schema::table('siakad_program_studi', function (Blueprint $table): void {
                $table->boolean('is_prefix_manual')->default(false)->after('prefix_nim');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('siakad_program_studi', 'is_prefix_manual')) {
            Schema::table('siakad_program_studi', function (Blueprint $table): void {
                $table->dropColumn('is_prefix_manual');
            });
        }
    }
};

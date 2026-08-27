<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spmb_master_program_studi', function (Blueprint $table) {
            if (!Schema::hasColumn('spmb_master_program_studi', 'kaprodi_id')) {
                $table->foreignId('kaprodi_id')->nullable()->after('fakultas_id')->constrained('siakad_dosen')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('spmb_master_program_studi', function (Blueprint $table) {
            if (Schema::hasColumn('spmb_master_program_studi', 'kaprodi_id')) {
                $table->dropConstrainedForeignId('kaprodi_id');
            }
        });
    }
};

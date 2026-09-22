<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('spmb_master_program_studi') && !Schema::hasTable('siakad_program_studi')) {
            Schema::rename('spmb_master_program_studi', 'siakad_program_studi');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('siakad_program_studi') && !Schema::hasTable('spmb_master_program_studi')) {
            Schema::rename('siakad_program_studi', 'spmb_master_program_studi');
        }
    }
};

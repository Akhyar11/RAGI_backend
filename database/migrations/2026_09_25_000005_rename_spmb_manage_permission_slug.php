<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Standarkan slug permission kelola SPMB ke format {modul}.{aksi}.
     */
    public function up(): void
    {
        DB::table('core_permissions')
            ->where('slug', 'spmb.admin.manage')
            ->where('module', 'spmb')
            ->update(['slug' => 'spmb.manage']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('core_permissions')
            ->where('slug', 'spmb.manage')
            ->where('module', 'spmb')
            ->update(['slug' => 'spmb.admin.manage']);
    }
};

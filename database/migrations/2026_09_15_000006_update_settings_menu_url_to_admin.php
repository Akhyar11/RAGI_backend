<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pindahkan URL menu Pengaturan Sistem dari /iam/settings ke /admin/settings
     * agar selaras dengan route frontend dan MenuSeeder.
     */
    public function up(): void
    {
        if (Schema::hasTable('core_menus')) {
            DB::table('core_menus')
                ->where('url', '/iam/settings')
                ->update(['url' => '/admin/settings']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('core_menus')) {
            DB::table('core_menus')
                ->where('url', '/admin/settings')
                ->where('name', 'Pengaturan Sistem')
                ->update(['url' => '/iam/settings']);
        }
    }
};

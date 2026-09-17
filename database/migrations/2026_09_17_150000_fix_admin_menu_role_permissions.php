<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('core_menus')) {
            return;
        }

        // Kaitkan permission_id pada master-referensi dan master-tipe-referensi ke permission iam.roles.update
        $perm = Permission::where('slug', 'iam.roles.update')->first();
        $permId = $perm ? $perm->id : null;

        DB::table('core_menus')
            ->whereIn('url', ['/admin/master-referensi', '/admin/master-tipe-referensi'])
            ->update([
                'permission_id' => $permId,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('core_menus')) {
            DB::table('core_menus')
                ->whereIn('url', ['/admin/master-referensi', '/admin/master-tipe-referensi'])
                ->update([
                    'permission_id' => null,
                    'updated_at' => now(),
                ]);
        }
    }
};

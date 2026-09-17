<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('spmb_master_referensi')) {
            if (!Schema::hasColumn('spmb_master_referensi', 'modul')) {
                Schema::table('spmb_master_referensi', function (Blueprint $table) {
                    $table->string('modul', 50)->default('global')->index()->after('tipe');
                });
            }

            // Update existing data with appropriate modules
            DB::table('spmb_master_referensi')
                ->whereIn('tipe', ['status_sipil', 'agama', 'kewarganegaraan', 'golongan_darah'])
                ->update(['modul' => 'global']);

            DB::table('spmb_master_referensi')
                ->whereIn('tipe', ['asal_lulusan', 'jenis_pt', 'jenjang_pt', 'info_daftar', 'penghasilan_ortu'])
                ->update(['modul' => 'spmb']);
        }

        // Register Master Referensi menus in core_menus for all modules
        if (Schema::hasTable('core_menus')) {
            // 1. SSO
            $iamSection = DB::table('core_menus')->where('module', 'sso')->where('url', '#iam_section')->first();
            $ssoParentId = $iamSection ? $iamSection->id : null;
            $maxOrderSso = DB::table('core_menus')->where('module', 'sso')->where('parent_id', $ssoParentId)->max('order_index') ?? 10;
            
            DB::table('core_menus')->updateOrInsert(
                ['module' => 'sso', 'url' => '/admin/master-referensi'],
                [
                    'name' => 'Master Data Referensi',
                    'icon' => 'FaDatabase',
                    'parent_id' => $ssoParentId,
                    'order_index' => $maxOrderSso + 1,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // 2. SPMB
            $spmbMasterSection = DB::table('core_menus')->where('module', 'spmb')->where('url', '#master_spmb')->first();
            $spmbParentId = $spmbMasterSection ? $spmbMasterSection->id : null;
            $maxOrderSpmb = DB::table('core_menus')->where('module', 'spmb')->where('parent_id', $spmbParentId)->max('order_index') ?? 6;

            DB::table('core_menus')->updateOrInsert(
                ['module' => 'spmb', 'url' => '/spmb/master/referensi'],
                [
                    'name' => 'Master Referensi',
                    'icon' => 'FaDatabase',
                    'parent_id' => $spmbParentId,
                    'order_index' => $maxOrderSpmb + 1,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // 3. SIAKAD
            $siakadMasterSection = DB::table('core_menus')->where('module', 'siakad')->where('url', '#master_siakad')->first();
            $siakadParentId = $siakadMasterSection ? $siakadMasterSection->id : null;
            $maxOrderSiakad = DB::table('core_menus')->where('module', 'siakad')->where('parent_id', $siakadParentId)->max('order_index') ?? 3;

            DB::table('core_menus')->updateOrInsert(
                ['module' => 'siakad', 'url' => '/siakad/master/referensi'],
                [
                    'name' => 'Master Referensi',
                    'icon' => 'FaDatabase',
                    'parent_id' => $siakadParentId,
                    'order_index' => $maxOrderSiakad + 1,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // 4. SIMPEG
            $simpegMasterSection = DB::table('core_menus')->where('module', 'simpeg')->where('url', '#master_simpeg')->first();
            if (!$simpegMasterSection) {
                $simpegParentId = DB::table('core_menus')->insertGetId([
                    'name' => 'MASTER DATA SDM',
                    'url' => '#master_simpeg',
                    'icon' => 'FaDatabase',
                    'module' => 'simpeg',
                    'parent_id' => null,
                    'order_index' => 10,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $simpegParentId = $simpegMasterSection->id;
            }

            DB::table('core_menus')->updateOrInsert(
                ['module' => 'simpeg', 'url' => '/simpeg/master/referensi'],
                [
                    'name' => 'Master Referensi',
                    'icon' => 'FaDatabase',
                    'parent_id' => $simpegParentId,
                    'order_index' => 1,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // 5. SIKEU
            $sikeuMasterSection = DB::table('core_menus')->where('module', 'sikeu')->where('url', '#master_sikeu')->first();
            if (!$sikeuMasterSection) {
                $sikeuParentId = DB::table('core_menus')->insertGetId([
                    'name' => 'MASTER KEUANGAN',
                    'url' => '#master_sikeu',
                    'icon' => 'FaDatabase',
                    'module' => 'sikeu',
                    'parent_id' => null,
                    'order_index' => 10,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $sikeuParentId = $sikeuMasterSection->id;
            }

            DB::table('core_menus')->updateOrInsert(
                ['module' => 'sikeu', 'url' => '/sikeu/master/referensi'],
                [
                    'name' => 'Master Referensi',
                    'icon' => 'FaDatabase',
                    'parent_id' => $sikeuParentId,
                    'order_index' => 1,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // By default, grant access to Super Admin role for all these new menus
            $superAdminRole = DB::table('core_roles')->whereIn('slug', ['superadmin', 'super-admin'])->first();
            if ($superAdminRole) {
                $newMenuIds = DB::table('core_menus')
                    ->whereIn('url', [
                        '/admin/master-referensi',
                        '/spmb/master/referensi',
                        '/siakad/master/referensi',
                        '/simpeg/master/referensi',
                        '/sikeu/master/referensi',
                        '#master_simpeg',
                        '#master_sikeu',
                    ])
                    ->pluck('id')
                    ->toArray();

                foreach ($newMenuIds as $menuId) {
                    DB::table('core_menu_role')->updateOrInsert([
                        'role_id' => $superAdminRole->id,
                        'menu_id' => $menuId,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('core_menus')) {
            $menuIds = DB::table('core_menus')
                ->whereIn('url', [
                    '/admin/master-referensi',
                    '/spmb/master/referensi',
                    '/siakad/master/referensi',
                    '/simpeg/master/referensi',
                    '/sikeu/master/referensi',
                ])
                ->pluck('id')
                ->toArray();

            DB::table('core_menu_role')->whereIn('menu_id', $menuIds)->delete();
            DB::table('core_menus')->whereIn('id', $menuIds)->delete();
        }

        if (Schema::hasTable('spmb_master_referensi') && Schema::hasColumn('spmb_master_referensi', 'modul')) {
            Schema::table('spmb_master_referensi', function (Blueprint $table) {
                $table->dropColumn('modul');
            });
        }
    }
};

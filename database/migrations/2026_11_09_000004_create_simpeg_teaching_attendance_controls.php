<?php

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        [
            'slug' => 'simpeg.presensi.pengajaran.read',
            'name' => 'Lihat Monitoring Presensi Pengajaran',
            'action' => 'read',
            'description' => 'Melihat rekap pertemuan dan kelengkapan presensi dosen dari LMS/SIAKAD',
        ],
        [
            'slug' => 'simpeg.presensi.pengajaran.manage',
            'name' => 'Kelola Cut-off Presensi Pengajaran',
            'action' => 'update',
            'description' => 'Mengatur cut-off periode serta memberikan atau mencabut izin khusus pengisian presensi',
        ],
    ];

    public function up(): void
    {
        Schema::create('simpeg_presensi_pengajaran_cutoffs', function (Blueprint $table) {
            $table->id();
            $table->char('periode', 7);
            $table->dateTime('cutoff_at');
            $table->foreignId('set_by')->nullable()->constrained('core_users')->nullOnDelete();
            $table->timestamps();

            $table->unique('periode');
            $table->index('cutoff_at');
        });

        Schema::create('simpeg_presensi_pengajaran_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dosen_id')->constrained('siakad_dosen')->restrictOnDelete();
            $table->char('periode', 7);
            $table->dateTime('valid_until');
            $table->text('reason');
            $table->foreignId('granted_by')->nullable()->constrained('core_users')->nullOnDelete();
            $table->dateTime('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('core_users')->nullOnDelete();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();

            $table->index(['periode', 'dosen_id', 'valid_until'], 'simpeg_presensi_exception_period_dosen_idx');
            $table->index(['periode', 'revoked_at'], 'simpeg_presensi_exception_active_idx');
        });

        foreach (self::PERMISSIONS as $definition) {
            Permission::updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'module' => 'simpeg',
                    'action' => $definition['action'],
                    'description' => $definition['description'],
                ]
            );
        }

        $roles = Role::whereIn('slug', ['superadmin', 'admin', 'admin_simpeg', 'operator_sdm'])->get();
        foreach (self::PERMISSIONS as $definition) {
            $permission = Permission::where('slug', $definition['slug'])->first();
            foreach ($roles as $role) {
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
        }

        $readPermission = Permission::where('slug', 'simpeg.presensi.pengajaran.read')->first();
        $parent = Menu::where('url', '#layanan_simpeg')->where('module', 'simpeg')->first();
        if ($parent && $readPermission) {
            $menu = Menu::updateOrCreate(
                ['url' => '/simpeg/presensi/pengajaran', 'module' => 'simpeg'],
                [
                    'parent_id' => $parent->id,
                    'name' => 'Monitoring Pengajaran',
                    'icon' => 'FaChalkboardTeacher',
                    'permission_id' => $readPermission->id,
                    'order_index' => 10,
                    'is_active' => true,
                ]
            );
            $menu->roles()->sync(
                $roles->pluck('id')->all()
            );
        }
    }

    public function down(): void
    {
        Menu::where('url', '/simpeg/presensi/pengajaran')->where('module', 'simpeg')->delete();

        $permissionIds = Permission::whereIn('slug', array_column(self::PERMISSIONS, 'slug'))->pluck('id');
        if ($permissionIds->isNotEmpty()) {
            foreach (Role::all() as $role) {
                $role->permissions()->detach($permissionIds);
            }
        }
        Permission::whereIn('slug', array_column(self::PERMISSIONS, 'slug'))->delete();

        Schema::dropIfExists('simpeg_presensi_pengajaran_exceptions');
        Schema::dropIfExists('simpeg_presensi_pengajaran_cutoffs');
    }
};

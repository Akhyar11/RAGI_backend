<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permission khusus modul LMS untuk forum diskusi.
 *
 * Latar belakang: sebelumnya endpoint forum memakai permission SIAKAD
 * (`siakad.kelas.read` / `siakad.kelas.manage`). Itu tidak membedakan aksi baca
 * dari aksi tulis — menulis pesan (POST) hanya diminta izin baca, padahal itu
 * mutasi data. Forum kini punya permission sendiri:
 *
 *   - `lms.forum.read`   → GET topik & pesan
 *   - `lms.forum.create` → POST pesan/balasan, hapus pesan milik sendiri
 *   - `lms.forum.manage` → buat/hapus topik, moderasi pesan siapa pun
 *
 * Permission didefinisikan di `PermissionSeeder` (upsert idempoten). Migrasi ini
 * hanya melakukan insert-if-missing agar database yang sudah ada (dan tidak di-seed
 * ulang) tetap konsisten dengan seeder.
 *
 * Pembagian role:
 *   - mahasiswa → read + create (boleh berdiskusi, tak boleh buat/hapus topik)
 *   - dosen     → read + create + manage
 *   - admin/superadmin/kaprodi/wakil_prodi → read + create + manage
 */
return new class extends Migration
{
    /**
     * Definisi permission baru beserta action-nya.
     *
     * @var array<string, array{module: string, action: string, name: string, description: string}>
     */
    private const PERMISSIONS = [
        'lms.forum.read' => [
            'module' => 'lms',
            'action' => 'read',
            'name' => 'Lihat Forum Diskusi',
            'description' => 'Membaca topik & pesan forum kelas',
        ],
        'lms.forum.create' => [
            'module' => 'lms',
            'action' => 'create',
            'name' => 'Kirim Pesan Forum',
            'description' => 'Mengirim pesan & balasan pada forum kelas',
        ],
        'lms.forum.manage' => [
            'module' => 'lms',
            'action' => 'update',
            'name' => 'Kelola Forum Diskusi',
            'description' => 'Membuat/hapus topik & moderasi pesan forum kelas',
        ],
    ];

    /**
     * Role yang mendapat seluruh permission forum.
     *
     * @var array<int, string>
     */
    private const ROLES_FULL = [
        'admin',
        'dosen',
        'kaprodi',
        'wakil_prodi',
    ];

    /**
     * Role yang hanya boleh berdiskusi (baca + kirim pesan).
     *
     * @var array<int, string>
     */
    private const ROLES_DISCUSS = [
        'mahasiswa',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('core_permissions') || !Schema::hasTable('core_role_permissions')) {
            return;
        }

        $permissionIds = [];

        foreach (self::PERMISSIONS as $slug => $definition) {
            $existing = DB::table('core_permissions')->where('slug', $slug)->first();

            if ($existing) {
                $permissionIds[$slug] = $existing->id;

                continue;
            }

            // core_permissions & core_role_permissions hanya punya created_at
            // (tanpa updated_at), jadi jangan mengirim kolom yang tidak ada.
            $permissionIds[$slug] = DB::table('core_permissions')->insertGetId([
                'name' => $definition['name'],
                'slug' => $slug,
                'module' => $definition['module'],
                'action' => $definition['action'],
                'description' => $definition['description'],
                'created_at' => now(),
            ]);
        }

        $grants = [];

        foreach (self::ROLES_FULL as $roleSlug) {
            foreach (array_keys(self::PERMISSIONS) as $permissionSlug) {
                $grants[] = [$roleSlug, $permissionSlug];
            }
        }

        foreach (self::ROLES_DISCUSS as $roleSlug) {
            $grants[] = [$roleSlug, 'lms.forum.read'];
            $grants[] = [$roleSlug, 'lms.forum.create'];
        }

        foreach ($grants as [$roleSlug, $permissionSlug]) {
            $roleId = DB::table('core_roles')->where('slug', $roleSlug)->value('id');

            if (! $roleId || ! isset($permissionIds[$permissionSlug])) {
                continue;
            }

            $alreadyGranted = DB::table('core_role_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionIds[$permissionSlug])
                ->exists();

            if (! $alreadyGranted) {
                DB::table('core_role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionIds[$permissionSlug],
                    'created_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('core_permissions') || !Schema::hasTable('core_role_permissions')) {
            return;
        }

        $ids = DB::table('core_permissions')
            ->whereIn('slug', array_keys(self::PERMISSIONS))
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('core_role_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('core_permissions')->whereIn('id', $ids)->delete();
        }
    }
};
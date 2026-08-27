<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Insert Kaprodi and Wakil Prodi roles if they don't exist
        $roles = [
            [
                'name' => 'Ketua Program Studi',
                'slug' => 'kaprodi',
                'description' => 'Ketua Program Studi (Kaprodi) dengan wewenang verifikasi RPS & OBE di SIAKAD',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Wakil Ketua Program Studi',
                'slug' => 'wakil_prodi',
                'description' => 'Wakil Ketua Program Studi (Wakil Kaprodi) dengan wewenang verifikasi RPS & OBE di SIAKAD',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($roles as $role) {
            if (!DB::table('core_roles')->where('slug', $role['slug'])->exists()) {
                DB::table('core_roles')->insert($role);
            }
        }
    }

    public function down(): void
    {
        DB::table('core_roles')->whereIn('slug', ['kaprodi', 'wakil_prodi'])->delete();
    }
};

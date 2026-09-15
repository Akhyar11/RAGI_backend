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
        if (!Schema::hasTable('simpeg_pegawai_roles')) {
            Schema::create('simpeg_pegawai_roles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pegawai_id')->constrained('simpeg_pegawai')->onDelete('cascade');
                $table->foreignId('role_id')->constrained('core_roles')->onDelete('cascade');
                $table->timestamps();

                $table->unique(['pegawai_id', 'role_id']);
            });
        }

        // Auto-assign role 'dosen' (slug 'dosen') to all existing dosen in simpeg_pegawai
        $dosenRole = DB::table('core_roles')->where('slug', 'dosen')->first();
        if ($dosenRole) {
            $pegawais = DB::table('simpeg_pegawai')->get();
            $now = now();
            $inserts = [];
            foreach ($pegawais as $peg) {
                $inserts[] = [
                    'pegawai_id' => $peg->id,
                    'role_id' => $dosenRole->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if (!empty($inserts)) {
                DB::table('simpeg_pegawai_roles')->insertOrIgnore($inserts);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_pegawai_roles');
    }
};

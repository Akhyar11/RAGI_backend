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
        if (!Schema::hasColumn('core_users', 'name')) {
            Schema::table('core_users', function (Blueprint $table) {
                $table->string('name')->nullable()->after('username');
            });
        }

        // Backfill existing users' names if empty
        $users = DB::table('core_users')->whereNull('name')->orWhere('name', '')->get();
        foreach ($users as $user) {
            $name = null;
            // Check if user is linked to a pegawai
            if (Schema::hasTable('pegawai')) {
                $pegawai = DB::table('pegawai')->where('user_id', $user->id)->first();
                if ($pegawai && !empty($pegawai->nama_lengkap)) {
                    $name = $pegawai->nama_lengkap;
                }
            }
            if (!$name && Schema::hasTable('simpeg_pegawai')) {
                $simpeg = DB::table('simpeg_pegawai')->where('user_id', $user->id)->first();
                if ($simpeg && !empty($simpeg->nama_lengkap)) {
                    $name = $simpeg->nama_lengkap;
                }
            }
            if (!$name && Schema::hasTable('siakad_mahasiswa')) {
                $mhs = DB::table('siakad_mahasiswa')->where('user_id', $user->id)->first();
                if ($mhs && !empty($mhs->nama)) {
                    $name = $mhs->nama;
                }
            }
            if (!$name) {
                if ($user->username === 'superadmin') {
                    $name = 'Super Administrator';
                } else {
                    $name = ucwords(str_replace(['.', '_', '-'], ' ', $user->username));
                }
            }

            DB::table('core_users')->where('id', $user->id)->update([
                'name' => $name,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('core_users', 'name')) {
            Schema::table('core_users', function (Blueprint $table) {
                $table->dropColumn('name');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('core_users') && Schema::hasTable('simpeg_pegawai')) {
            $pegawais = DB::table('simpeg_pegawai')
                ->whereNotNull('user_id')
                ->whereNotNull('nama_lengkap')
                ->where('nama_lengkap', '!=', '')
                ->get(['user_id', 'nama_lengkap']);

            foreach ($pegawais as $p) {
                DB::table('core_users')
                    ->where('id', $p->user_id)
                    ->where(function ($q) {
                        $q->whereNull('name')->orWhere('name', '');
                    })
                    ->update(['name' => $p->nama_lengkap]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Operasi backfill nama tidak di-revert agar tidak menghapus nama user yang sudah sinkron
    }
};

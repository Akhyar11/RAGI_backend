<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('simpeg_surat_tugas', function (Blueprint $table) {
            if (!Schema::hasColumn('simpeg_surat_tugas', 'jam_pelaksanaan')) {
                $table->string('jam_pelaksanaan', 100)->nullable()->after('tanggal_selesai');
            }
            if (!Schema::hasColumn('simpeg_surat_tugas', 'penyelenggara')) {
                $table->string('penyelenggara', 255)->nullable()->after('jam_pelaksanaan');
            }
        });

        // Pastikan role direktur terdaftar di core_roles jika belum ada
        if (Schema::hasTable('core_roles')) {
            $existing = DB::table('core_roles')->where('slug', 'direktur')->first();
            if (!$existing) {
                DB::table('core_roles')->insert([
                    'name' => 'Direktur Politeknik',
                    'slug' => 'direktur',
                    'description' => 'Pimpinan tertinggi institusi (Direktur Politeknik Indonusa Surakarta)',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_surat_tugas', function (Blueprint $table) {
            $table->dropColumn(['jam_pelaksanaan', 'penyelenggara']);
        });

        if (Schema::hasTable('core_roles')) {
            DB::table('core_roles')->where('slug', 'direktur')->delete();
        }
    }
};

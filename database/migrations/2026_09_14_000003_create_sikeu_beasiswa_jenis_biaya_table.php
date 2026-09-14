<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi Komponen Biaya untuk Program Beasiswa.
 *
 * Satu program beasiswa dapat berlaku untuk lebih dari satu komponen biaya
 * (sikeu_master_biaya). Data lama pada kolom sikeu_beasiswa.jenis_biaya_id
 * dipindahkan ke tabel pivot sikeu_beasiswa_jenis_biaya sebelum kolom dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sikeu_beasiswa_jenis_biaya')) {
            Schema::create('sikeu_beasiswa_jenis_biaya', function (Blueprint $table) {
                $table->id();
                $table->foreignId('beasiswa_id')->constrained('sikeu_beasiswa')->cascadeOnDelete();
                $table->foreignId('jenis_biaya_id')->constrained('sikeu_master_biaya')->cascadeOnDelete();
                $table->timestamp('created_at')->nullable();

                $table->unique(['beasiswa_id', 'jenis_biaya_id']);
            });
        }

        // Migrasi data lama: kolom sikeu_beasiswa.jenis_biaya_id -> pivot
        if (Schema::hasTable('sikeu_beasiswa') && Schema::hasColumn('sikeu_beasiswa', 'jenis_biaya_id')) {
            $rows = DB::table('sikeu_beasiswa')
                ->whereNotNull('jenis_biaya_id')
                ->select('id', 'jenis_biaya_id')
                ->get();

            foreach ($rows as $row) {
                $exists = DB::table('sikeu_beasiswa_jenis_biaya')
                    ->where('beasiswa_id', $row->id)
                    ->where('jenis_biaya_id', $row->jenis_biaya_id)
                    ->exists();
                if (!$exists) {
                    DB::table('sikeu_beasiswa_jenis_biaya')->insert([
                        'beasiswa_id' => $row->id,
                        'jenis_biaya_id' => $row->jenis_biaya_id,
                        'created_at' => now(),
                    ]);
                }
            }

            // Hapus kolom lama. SQLite tidak mendukung DROP COLUMN pada kolom ber-FK,
            // sehingga pembersihan kolom hanya dilakukan pada database MySQL.
            if (DB::getDriverName() === 'mysql') {
                Schema::table('sikeu_beasiswa', function (Blueprint $table) {
                    $table->dropForeign(['jenis_biaya_id']);
                    $table->dropColumn('jenis_biaya_id');
                });
            }
        }
    }

    public function down(): void
    {
        // Kembalikan kolom jenis_biaya_id (ambil komponen pertama dari pivot)
        if (Schema::hasTable('sikeu_beasiswa') && !Schema::hasColumn('sikeu_beasiswa', 'jenis_biaya_id')) {
            Schema::table('sikeu_beasiswa', function (Blueprint $table) {
                $table->foreignId('jenis_biaya_id')->nullable()->constrained('sikeu_master_biaya')->nullOnDelete();
            });

            $mappings = DB::table('sikeu_beasiswa_jenis_biaya')
                ->orderBy('id')
                ->get();

            foreach ($mappings as $map) {
                DB::table('sikeu_beasiswa')
                    ->where('id', $map->beasiswa_id)
                    ->whereNull('jenis_biaya_id')
                    ->update(['jenis_biaya_id' => $map->jenis_biaya_id]);
            }
        }

        Schema::dropIfExists('sikeu_beasiswa_jenis_biaya');
    }
};
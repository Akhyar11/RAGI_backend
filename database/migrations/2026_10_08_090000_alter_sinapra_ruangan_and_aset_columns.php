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
        // 1. sinapra_ruangan: ubah tipe dari enum kaku ke string fleksibel (menghilangkan MySQL warning 1265)
        Schema::table('sinapra_ruangan', function (Blueprint $table) {
            $table->string('tipe', 50)->nullable()->default('lainnya')->change();
            $table->string('status', 50)->default('aktif')->change();
        });

        // 2. sinapra_aset: ubah status dan kondisi dari enum ke string fleksibel
        Schema::table('sinapra_aset', function (Blueprint $table) {
            $table->string('status', 50)->default('tersedia')->change();
            $table->string('kondisi', 50)->default('baik')->change();
        });

        // 3. sinapra_gedung: ubah status ke string fleksibel
        Schema::table('sinapra_gedung', function (Blueprint $table) {
            $table->string('status', 50)->default('aktif')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Normalisasi data sebelum mengembalikan ke enum kaku agar lolos check constraint
        DB::table('sinapra_gedung')
            ->whereNotIn('status', ['aktif', 'tidak_aktif', 'renovasi'])
            ->update(['status' => 'aktif']);

        DB::table('sinapra_aset')
            ->whereNotIn('kondisi', ['baik', 'rusak_ringan', 'rusak_berat', 'hilang'])
            ->update(['kondisi' => 'baik']);

        DB::table('sinapra_aset')
            ->whereNotIn('status', ['tersedia', 'dipinjam', 'maintenance', 'dihapus'])
            ->update(['status' => 'tersedia']);

        DB::table('sinapra_ruangan')
            ->whereNotIn('tipe', ['kelas', 'lab', 'aula', 'kantor', 'gudang', 'toilet', 'lainnya'])
            ->update(['tipe' => 'lainnya']);

        DB::table('sinapra_ruangan')
            ->whereNotIn('status', ['aktif', 'maintenance', 'tidak_aktif'])
            ->update(['status' => 'aktif']);

        Schema::table('sinapra_gedung', function (Blueprint $table) {
            $table->enum('status', ['aktif', 'tidak_aktif', 'renovasi'])->default('aktif')->change();
        });

        Schema::table('sinapra_aset', function (Blueprint $table) {
            $table->enum('kondisi', ['baik', 'rusak_ringan', 'rusak_berat', 'hilang'])->default('baik')->change();
            $table->enum('status', ['tersedia', 'dipinjam', 'maintenance', 'dihapus'])->default('tersedia')->change();
        });

        Schema::table('sinapra_ruangan', function (Blueprint $table) {
            $table->enum('tipe', ['kelas', 'lab', 'aula', 'kantor', 'gudang', 'toilet', 'lainnya'])->default('kelas')->change();
            $table->enum('status', ['aktif', 'maintenance', 'tidak_aktif'])->default('aktif')->change();
        });
    }
};

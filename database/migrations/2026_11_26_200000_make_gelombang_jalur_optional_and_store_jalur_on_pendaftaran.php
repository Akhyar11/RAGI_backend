<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gelombang tanpa jalur = gelombang umum yang dapat dipakai jalur mana pun.
        Schema::table('spmb_gelombang_penerimaan', function (Blueprint $table) {
            $table->unsignedBigInteger('jalur_masuk_id')->nullable()->change();
        });

        // Jalur pilihan pendaftar disimpan sendiri karena tidak selalu dapat diturunkan dari gelombang.
        Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table) {
            $table->foreignId('jalur_masuk_id')->nullable()->after('gelombang_id')
                ->constrained('spmb_jalur_masuk')->nullOnDelete();
        });

        DB::table('spmb_pendaftaran_calon_mhs')
            ->whereNotNull('gelombang_id')
            ->update(['jalur_masuk_id' => DB::raw(
                '(select g.jalur_masuk_id from spmb_gelombang_penerimaan g where g.id = spmb_pendaftaran_calon_mhs.gelombang_id)'
            )]);
    }

    public function down(): void
    {
        Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jalur_masuk_id');
        });
    }
};

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
        // 1. Bersihkan data trashed yang tersangkut di database sebelum menghapus kolom deleted_at
        DB::table('core_arsip_klasifikasi')->whereNotNull('deleted_at')->delete();
        DB::table('core_arsip_kop_surat')->whereNotNull('deleted_at')->delete();

        // 2. Drop kolom soft delete agar entitas beralih ke penghapusan permanen (hard delete)
        Schema::table('core_arsip_klasifikasi', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('core_arsip_kop_surat', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('core_arsip_kop_surat', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('core_arsip_klasifikasi', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};

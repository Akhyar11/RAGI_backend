<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('siakad_pertemuan', function (Blueprint $table) {
            if (!Schema::hasColumn('siakad_pertemuan', 'catatan_pertemuan')) {
                $table->text('catatan_pertemuan')->nullable()->after('materi');
            }
            if (!Schema::hasColumn('siakad_pertemuan', 'status_pertemuan')) {
                $table->enum('status_pertemuan', ['belum', 'berlangsung', 'selesai'])->default('belum')->after('catatan_pertemuan');
            }
            if (!Schema::hasColumn('siakad_pertemuan', 'token_absensi')) {
                $table->string('token_absensi', 10)->nullable()->after('status_pertemuan');
            }
            if (!Schema::hasColumn('siakad_pertemuan', 'token_expired_at')) {
                $table->timestamp('token_expired_at')->nullable()->after('token_absensi');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('siakad_pertemuan', function (Blueprint $table) {
            $columns = ['catatan_pertemuan', 'status_pertemuan', 'token_absensi', 'token_expired_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('siakad_pertemuan', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

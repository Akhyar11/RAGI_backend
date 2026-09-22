<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Model kanal sumber dana: tiap UnitKas adalah satu kanal
     * (tunai / bank_manual / bank_h2h / xendit) yang dipetakan ke
     * satu akun COA kas-bank, agar laporan per kanal tidak tercampur.
     */
    public function up(): void
    {
        Schema::table('sikeu_unit_kas', function (Blueprint $table) {
            $table->string('kanal', 20)->default('bank_manual')->after('tipe_kas');
            $table->foreignId('akun_keuangan_id')->nullable()->after('unit_kerja_id')
                ->constrained('sikeu_akun_keuangan')->onDelete('set null');
            $table->index('kanal');
            $table->index('akun_keuangan_id');
        });

        Schema::table('sikeu_pengajuan_pencairan_kas', function (Blueprint $table) {
            $table->string('kanal', 20)->nullable()->after('unit_kas_id');
            $table->string('referensi_eksternal', 100)->nullable()->after('bukti_pencairan_path');
            $table->index('kanal');
        });
    }

    public function down(): void
    {
        Schema::table('sikeu_pengajuan_pencairan_kas', function (Blueprint $table) {
            $table->dropIndex(['kanal']);
            $table->dropColumn(['kanal', 'referensi_eksternal']);
        });

        Schema::table('sikeu_unit_kas', function (Blueprint $table) {
            $table->dropForeign(['akun_keuangan_id']);
            $table->dropIndex(['kanal']);
            $table->dropIndex(['akun_keuangan_id']);
            $table->dropColumn(['kanal', 'akun_keuangan_id']);
        });
    }
};

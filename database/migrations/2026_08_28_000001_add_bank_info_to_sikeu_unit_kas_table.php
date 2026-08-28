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
        Schema::table('sikeu_unit_kas', function (Blueprint $table) {
            if (!Schema::hasColumn('sikeu_unit_kas', 'tipe_kas')) {
                $table->string('tipe_kas')->default('operasional')->after('nama_kas');
            }
            if (!Schema::hasColumn('sikeu_unit_kas', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('tipe_kas');
            }
            if (!Schema::hasColumn('sikeu_unit_kas', 'bank_account_number')) {
                $table->string('bank_account_number')->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('sikeu_unit_kas', 'bank_account_name')) {
                $table->string('bank_account_name')->nullable()->after('bank_account_number');
            }
            if (!Schema::hasColumn('sikeu_unit_kas', 'penanggung_jawab')) {
                $table->string('penanggung_jawab')->nullable()->after('bank_account_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sikeu_unit_kas', function (Blueprint $table) {
            $table->dropColumn([
                'tipe_kas',
                'bank_name',
                'bank_account_number',
                'bank_account_name',
                'penanggung_jawab',
            ]);
        });
    }
};

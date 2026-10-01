<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Selaraskan alur pencairan reward referral SPMB dengan alur Pengajuan
     * Operasional SIKEU: pending_keuangan -> pending_direktur -> disetujui ->
     * dicairkan (+ ditolak). Tambahkan jejak approval keuangan & direktur.
     */
    public function up(): void
    {
        if (! Schema::hasTable('spmb_referral_payouts')) {
            return;
        }

        Schema::table('spmb_referral_payouts', function (Blueprint $table) {
            if (! Schema::hasColumn('spmb_referral_payouts', 'approved_keuangan_by')) {
                $table->foreignId('approved_keuangan_by')->nullable()->after('verified_at')->constrained('core_users')->nullOnDelete();
            }
            if (! Schema::hasColumn('spmb_referral_payouts', 'approved_keuangan_at')) {
                $table->timestamp('approved_keuangan_at')->nullable()->after('approved_keuangan_by');
            }
            if (! Schema::hasColumn('spmb_referral_payouts', 'approved_direktur_by')) {
                $table->foreignId('approved_direktur_by')->nullable()->after('approved_keuangan_at')->constrained('core_users')->nullOnDelete();
            }
            if (! Schema::hasColumn('spmb_referral_payouts', 'approved_direktur_at')) {
                $table->timestamp('approved_direktur_at')->nullable()->after('approved_direktur_by');
            }
        });

        if (Schema::hasColumn('spmb_referral_payouts', 'status')) {
            // Migrasi data status lama -> alur pengajuan operasional.
            DB::table('spmb_referral_payouts')->where('status', 'menunggu_verifikasi')->update(['status' => 'pending_keuangan']);
            DB::table('spmb_referral_payouts')->where('status', 'terverifikasi')->update(['status' => 'pending_direktur']);
            DB::table('spmb_referral_payouts')->where('status', 'dibayar')->update(['status' => 'dicairkan']);

            Schema::table('spmb_referral_payouts', function (Blueprint $table) {
                $table->string('status', 30)->default('pending_keuangan')->change();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('spmb_referral_payouts')) {
            return;
        }

        if (Schema::hasColumn('spmb_referral_payouts', 'status')) {
            DB::table('spmb_referral_payouts')->where('status', 'pending_keuangan')->update(['status' => 'menunggu_verifikasi']);
            DB::table('spmb_referral_payouts')->where('status', 'pending_direktur')->update(['status' => 'terverifikasi']);
            DB::table('spmb_referral_payouts')->where('status', 'disetujui')->update(['status' => 'terverifikasi']);
            DB::table('spmb_referral_payouts')->where('status', 'dicairkan')->update(['status' => 'dibayar']);

            Schema::table('spmb_referral_payouts', function (Blueprint $table) {
                $table->string('status', 30)->default('menunggu_verifikasi')->change();
            });
        }

        Schema::table('spmb_referral_payouts', function (Blueprint $table) {
            $columns = ['approved_keuangan_by', 'approved_keuangan_at', 'approved_direktur_by', 'approved_direktur_at'];
            foreach (['approved_keuangan_by', 'approved_direktur_by'] as $fk) {
                if (Schema::hasColumn('spmb_referral_payouts', $fk)) {
                    $table->dropForeign([$fk]);
                }
            }
            $toDrop = array_filter($columns, fn ($c) => Schema::hasColumn('spmb_referral_payouts', $c));
            if (! empty($toDrop)) {
                $table->dropColumn(array_values($toDrop));
            }
        });
    }
};

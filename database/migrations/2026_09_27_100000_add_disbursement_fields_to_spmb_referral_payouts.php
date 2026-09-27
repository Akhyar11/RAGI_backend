<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom siklus pencairan (invoice -> verifikasi -> bayar/tolak)
     * dan data rekening referrer pada bukti pencairan referral SPMB.
     */
    public function up(): void
    {
        if (! Schema::hasTable('spmb_referral_payouts')) {
            return;
        }

        Schema::table('spmb_referral_payouts', function (Blueprint $table) {
            $columns = [
                'status', 'verified_by', 'verified_at', 'paid_by', 'paid_at',
                'sikeu_reference', 'nomor_referensi_transfer', 'bukti_transfer_path',
                'catatan_penolakan', 'nama_bank', 'nomor_rekening', 'nama_pemilik_rekening',
            ];

            $missing = array_filter($columns, fn ($c) => ! Schema::hasColumn('spmb_referral_payouts', $c));
            if (empty($missing)) {
                return;
            }

            if (! Schema::hasColumn('spmb_referral_payouts', 'status')) {
                $table->string('status', 30)->default('menunggu_verifikasi')->after('keterangan');
            }
            if (! Schema::hasColumn('spmb_referral_payouts', 'verified_by')) {
                $table->foreignId('verified_by')->nullable()->after('status')->constrained('core_users')->nullOnDelete();
            }
            if (! Schema::hasColumn('spmb_referral_payouts', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verified_by');
            }
            if (! Schema::hasColumn('spmb_referral_payouts', 'paid_by')) {
                $table->foreignId('paid_by')->nullable()->after('verified_at')->constrained('core_users')->nullOnDelete();
            }
            if (! Schema::hasColumn('spmb_referral_payouts', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('paid_by');
            }
            if (! Schema::hasColumn('spmb_referral_payouts', 'sikeu_reference')) {
                $table->string('sikeu_reference', 100)->nullable()->after('paid_at');
            }
            if (! Schema::hasColumn('spmb_referral_payouts', 'nomor_referensi_transfer')) {
                $table->string('nomor_referensi_transfer', 100)->nullable()->after('sikeu_reference');
            }
            if (! Schema::hasColumn('spmb_referral_payouts', 'bukti_transfer_path')) {
                $table->string('bukti_transfer_path')->nullable()->after('nomor_referensi_transfer');
            }
            if (! Schema::hasColumn('spmb_referral_payouts', 'catatan_penolakan')) {
                $table->text('catatan_penolakan')->nullable()->after('bukti_transfer_path');
            }
            if (! Schema::hasColumn('spmb_referral_payouts', 'nama_bank')) {
                $table->string('nama_bank', 100)->nullable()->after('catatan_penolakan');
            }
            if (! Schema::hasColumn('spmb_referral_payouts', 'nomor_rekening')) {
                $table->string('nomor_rekening', 60)->nullable()->after('nama_bank');
            }
            if (! Schema::hasColumn('spmb_referral_payouts', 'nama_pemilik_rekening')) {
                $table->string('nama_pemilik_rekening', 150)->nullable()->after('nomor_rekening');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('spmb_referral_payouts')) {
            return;
        }

        Schema::table('spmb_referral_payouts', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropForeign(['paid_by']);
            $table->dropColumn([
                'status', 'verified_by', 'verified_at', 'paid_by', 'paid_at',
                'sikeu_reference', 'nomor_referensi_transfer', 'bukti_transfer_path',
                'catatan_penolakan', 'nama_bank', 'nomor_rekening', 'nama_pemilik_rekening',
            ]);
        });
    }
};

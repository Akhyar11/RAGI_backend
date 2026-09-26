<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reward referral & pencairan (payout):
     *  - Tandai komponen biaya sebagai sumber reward referral.
     *  - Mapping nominal reward per role (Mahasiswa/Dosen/dll).
     *  - Catatan payout (bukti pencairan) + penanda payout pada usage.
     */
    public function up(): void
    {
        // 1. Tandai komponen biaya sebagai sumber reward referral.
        if (Schema::hasTable('spmb_master_komponen_biaya')
            && ! Schema::hasColumn('spmb_master_komponen_biaya', 'is_referral_reward')) {
            Schema::table('spmb_master_komponen_biaya', function (Blueprint $table) {
                $table->boolean('is_referral_reward')
                    ->default(false)
                    ->after('tipe_potongan')
                    ->comment('true jika komponen ini menjadi sumber nominal reward referral');
            });
        }

        // 2. Mapping nominal reward per role untuk komponen biaya.
        if (! Schema::hasTable('spmb_komponen_biaya_role_reward')) {
            Schema::create('spmb_komponen_biaya_role_reward', function (Blueprint $table) {
                $table->id();
                $table->foreignId('komponen_biaya_id')
                    ->constrained('spmb_master_komponen_biaya')
                    ->cascadeOnDelete();
                $table->foreignId('role_id')
                    ->constrained('core_roles')
                    ->cascadeOnDelete();
                $table->decimal('nominal', 15, 2)->default(0.00);
                $table->timestamps();

                $table->unique(['komponen_biaya_id', 'role_id'], 'spmb_komponen_role_reward_unique');
            });
        }

        // 3. Bukti pencairan (payout) per referrer.
        if (! Schema::hasTable('spmb_referral_payouts')) {
            Schema::create('spmb_referral_payouts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('referrer_user_id')->constrained('core_users')->cascadeOnDelete();
                $table->unsignedInteger('referral_count')->default(0);
                $table->decimal('total_nominal', 15, 2)->default(0.00);
                $table->string('nomor_bukti', 50)->unique();
                $table->timestamp('generated_at')->nullable();
                $table->text('keterangan')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['referrer_user_id', 'generated_at']);
            });
        }

        // 4. Penanda payout pada usage referral.
        if (Schema::hasTable('spmb_referral_usages')
            && ! Schema::hasColumn('spmb_referral_usages', 'payout_id')) {
            Schema::table('spmb_referral_usages', function (Blueprint $table) {
                $table->foreignId('payout_id')
                    ->nullable()
                    ->after('reward_ref_id')
                    ->constrained('spmb_referral_payouts')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('spmb_referral_usages')
            && Schema::hasColumn('spmb_referral_usages', 'payout_id')) {
            Schema::table('spmb_referral_usages', function (Blueprint $table) {
                $table->dropForeign(['payout_id']);
                $table->dropColumn('payout_id');
            });
        }

        Schema::dropIfExists('spmb_referral_payouts');

        Schema::dropIfExists('spmb_komponen_biaya_role_reward');

        if (Schema::hasColumn('spmb_master_komponen_biaya', 'is_referral_reward')) {
            Schema::table('spmb_master_komponen_biaya', function (Blueprint $table) {
                $table->dropColumn('is_referral_reward');
            });
        }
    }
};

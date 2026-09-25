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
        Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table) {
            if (! Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'used_referral_code')) {
                $table->string('used_referral_code', 50)->nullable()->after('info_daftar')->index();
            }

            if (! Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'referrer_user_id')) {
                $table->foreignId('referrer_user_id')
                    ->nullable()
                    ->after('used_referral_code')
                    ->constrained('core_users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'referral_validated_at')) {
                $table->timestamp('referral_validated_at')->nullable()->after('referrer_user_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'referrer_user_id')
            && ! Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'used_referral_code')
            && ! Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'referral_validated_at')) {
            return;
        }

        Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table) {
            if (Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'referrer_user_id')) {
                $table->dropForeign(['referrer_user_id']);
                $table->dropColumn('referrer_user_id');
            }

            if (Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'used_referral_code')) {
                $table->dropColumn('used_referral_code');
            }

            if (Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'referral_validated_at')) {
                $table->dropColumn('referral_validated_at');
            }
        });
    }
};

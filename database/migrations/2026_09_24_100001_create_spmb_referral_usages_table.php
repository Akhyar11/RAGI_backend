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
        Schema::create('spmb_referral_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')
                ->unique()
                ->constrained('spmb_pendaftaran_calon_mhs')
                ->cascadeOnDelete();
            $table->foreignId('referee_user_id')
                ->constrained('core_users')
                ->cascadeOnDelete();
            $table->foreignId('referrer_user_id')
                ->constrained('core_users')
                ->cascadeOnDelete();
            $table->string('referral_code', 50);
            $table->string('status', 20)->default('claimed');
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->string('reward_ref_type', 50)->nullable();
            $table->unsignedBigInteger('reward_ref_id')->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['referrer_user_id', 'status']);
            $table->index(['referral_code', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spmb_referral_usages');
    }
};

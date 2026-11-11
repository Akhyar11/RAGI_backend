<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sikeu_payment_gateway_configs', function (Blueprint $table): void {
            $table->boolean('is_default')->default(false)->index();
        });
        $defaultId = DB::table('sikeu_payment_gateway_configs')->where('is_active', true)
            ->whereNotIn('gateway_name', ['bsn_h2h', 'rekening_manual'])->orderBy('id')->value('id');
        if ($defaultId) {
            DB::table('sikeu_payment_gateway_configs')->where('id', $defaultId)->update(['is_default' => true]);
        }
        Schema::table('spmb_master_komponen_biaya', function (Blueprint $table): void {
            $table->foreignId('payment_gateway_config_id')->nullable()->constrained('sikeu_payment_gateway_configs')->restrictOnDelete();
        });
        Schema::table('sikeu_tagihan_mahasiswa', function (Blueprint $table): void {
            $table->foreignId('payment_gateway_config_id')->nullable()->constrained('sikeu_payment_gateway_configs')->restrictOnDelete();
            $table->string('payment_gateway_name', 50)->nullable();
            $table->string('payment_gateway_environment', 20)->nullable();
        });
        Schema::create('sikeu_payment_checkouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tagihan_id')->constrained('sikeu_tagihan_mahasiswa')->restrictOnDelete();
            $table->foreignId('payment_gateway_config_id')->constrained('sikeu_payment_gateway_configs')->restrictOnDelete();
            $table->string('gateway_name', 50);
            $table->string('environment', 20);
            $table->string('order_id', 100)->unique();
            $table->string('provider_id')->nullable()->index();
            $table->decimal('amount', 15, 2);
            $table->text('token')->nullable();
            $table->text('redirect_url')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['tagihan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sikeu_payment_checkouts');
        Schema::table('sikeu_tagihan_mahasiswa', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_gateway_config_id');
            $table->dropColumn(['payment_gateway_name', 'payment_gateway_environment']);
        });
        Schema::table('spmb_master_komponen_biaya', fn (Blueprint $table) => $table->dropConstrainedForeignId('payment_gateway_config_id'));
        Schema::table('sikeu_payment_gateway_configs', function (Blueprint $table): void {
            $table->dropIndex(['is_default']);
            $table->dropColumn('is_default');
        });
    }
};

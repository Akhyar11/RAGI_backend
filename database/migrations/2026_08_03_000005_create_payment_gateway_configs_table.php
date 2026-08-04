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
        Schema::create('payment_gateway_configs', function (Blueprint $table) {
            $table->id();
            $table->string('gateway_name', 50)->default('xendit')->unique(); // xendit, duitku, midtrans
            $table->enum('environment', ['sandbox', 'production'])->default('sandbox');
            $table->text('api_key_encrypted')->nullable(); // Enkripsi AES-256-CBC Secret Key
            $table->text('public_key_encrypted')->nullable();
            $table->text('webhook_token_encrypted')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_disbursement_enabled')->default(true);
            $table->boolean('account_validation_enabled')->default(true);
            $table->decimal('max_disbursement_limit', 15, 2)->default(50000000.00);
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_configs');
    }
};

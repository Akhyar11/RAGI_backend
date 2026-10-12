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
        Schema::create('iam_api_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name', 150);
            $table->string('module', 50)->index();
            $table->string('http_method', 10)->default('GET');
            $table->text('description')->nullable();
            $table->json('expected_schema')->nullable();
            $table->text('url_api')->nullable();
            $table->text('token')->nullable();
            $table->string('auth_type', 30)->default('bearer');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_status', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iam_api_integrations');
    }
};

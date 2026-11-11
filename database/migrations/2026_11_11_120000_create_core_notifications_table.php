<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('core_users')->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained('core_modules')->nullOnDelete();
            $table->string('type', 80);
            $table->string('title', 200);
            $table->text('message')->nullable();
            $table->string('url', 255)->nullable();
            $table->string('entity_type', 100)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->string('dedup_key', 191);
            $table->timestamps();

            $table->unique(['user_id', 'dedup_key']);
            $table->index(['user_id', 'is_read', 'created_at']);
            $table->index(['module_id', 'type', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_notifications');
    }
};

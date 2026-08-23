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
        Schema::create('core_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('core_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('module')->nullable();
            $table->enum('action', ['create', 'read', 'update', 'delete', 'approve']);
            $table->text('description')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('core_user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('core_users')->onDelete('cascade');
            $table->foreignId('role_id')->constrained('core_roles')->onDelete('cascade');
            $table->foreignId('assigned_by')->nullable()->constrained('core_users')->onDelete('set null');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('core_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('core_roles')->onDelete('cascade');
            $table->foreignId('permission_id')->constrained('core_permissions')->onDelete('cascade');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('core_role_permissions');
        Schema::dropIfExists('core_user_roles');
        Schema::dropIfExists('core_permissions');
        Schema::dropIfExists('core_roles');
    }
};

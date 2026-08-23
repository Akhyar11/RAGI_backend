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
        Schema::create('core_menu_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('core_menus')->onDelete('cascade');
            $table->foreignId('role_id')->constrained('core_roles')->onDelete('cascade');
            $table->timestamps();

            // Ensure a role can't have duplicate menus
            $table->unique(['menu_id', 'role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('core_menu_role');
    }
};

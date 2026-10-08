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
        if (!Schema::hasTable('siakad_admin_prodi_menus')) {
            Schema::create('siakad_admin_prodi_menus', function (Blueprint $table) {
                $table->id();
                $table->foreignId('admin_prodi_id')
                    ->constrained('siakad_admin_prodi')
                    ->cascadeOnDelete();
                $table->foreignId('menu_id')
                    ->constrained('core_menus')
                    ->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['admin_prodi_id', 'menu_id'], 'uq_admin_prodi_menu');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siakad_admin_prodi_menus');
    }
};


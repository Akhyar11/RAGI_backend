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
        // Tabel konfigurasi menu khusus Admin OBE berdasarkan Role (Dosen / Pegawai / dll)
        if (!Schema::hasTable('siakad_admin_obe_role_menus')) {
            Schema::create('siakad_admin_obe_role_menus', function (Blueprint $table) {
                $table->id();
                $table->foreignId('role_id')->constrained('core_roles')->cascadeOnDelete();
                $table->foreignId('menu_id')->constrained('core_menus')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['role_id', 'menu_id'], 'uq_obe_role_menu');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siakad_admin_obe_role_menus');
    }
};


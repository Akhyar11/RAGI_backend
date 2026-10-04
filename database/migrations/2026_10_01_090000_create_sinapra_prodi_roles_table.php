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
        if (!Schema::hasTable('sinapra_prodi_roles')) {
            Schema::create('sinapra_prodi_roles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_studi_id')
                    ->constrained('siakad_program_studi')
                    ->onDelete('cascade');
                $table->foreignId('role_id')
                    ->constrained('core_roles')
                    ->onDelete('cascade');
                $table->string('keterangan', 255)->nullable();
                $table->timestamps();

                $table->unique(['program_studi_id', 'role_id'], 'uq_sinapra_prodi_roles');
                $table->index('program_studi_id', 'idx_sinapra_prodi_roles_prodi');
                $table->index('role_id', 'idx_sinapra_prodi_roles_role');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sinapra_prodi_roles');
    }
};

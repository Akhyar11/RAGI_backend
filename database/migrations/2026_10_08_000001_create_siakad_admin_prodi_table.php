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
        if (!Schema::hasTable('siakad_admin_prodi')) {
            Schema::create('siakad_admin_prodi', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_studi_id')
                    ->constrained('siakad_program_studi')
                    ->cascadeOnDelete();
                $table->foreignId('user_id')
                    ->constrained('core_users')
                    ->cascadeOnDelete();
                $table->string('jabatan', 100)->default('Admin OBE / Tim Kurikulum');
                $table->boolean('can_approve_rps')->default(true);
                $table->boolean('is_active')->default(true);
                $table->foreignId('assigned_by')
                    ->nullable()
                    ->constrained('core_users')
                    ->nullOnDelete();
                $table->timestamps();

                $table->unique(['program_studi_id', 'user_id'], 'uq_siakad_admin_prodi');
                $table->index('program_studi_id', 'idx_siakad_admin_prodi_prodi');
                $table->index('user_id', 'idx_siakad_admin_prodi_user');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siakad_admin_prodi');
    }
};

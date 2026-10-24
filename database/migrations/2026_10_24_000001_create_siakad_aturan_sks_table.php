<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('siakad_aturan_sks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_studi_id')
                ->nullable()
                ->constrained('siakad_program_studi')
                ->cascadeOnDelete();
            $table->decimal('ipk_min', 3, 2);
            $table->decimal('ipk_max', 3, 2);
            $table->integer('max_sks');
            $table->string('keterangan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['program_studi_id', 'is_active', 'ipk_min', 'ipk_max'], 'siakad_aturan_sks_lookup_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siakad_aturan_sks');
    }
};

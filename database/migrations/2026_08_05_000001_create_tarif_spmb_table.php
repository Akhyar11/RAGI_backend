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
        if (!Schema::hasTable('sikeu_tarif_spmb')) {
            Schema::create('sikeu_tarif_spmb', function (Blueprint $table) {
                $table->id();
                $table->foreignId('jenis_biaya_id')->nullable()->constrained('sikeu_jenis_biaya')->onDelete('cascade');
                $table->string('jalur_id', 50)->index();
                $table->string('gelombang_id', 50)->index();
                $table->decimal('nominal', 15, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['jenis_biaya_id', 'jalur_id', 'gelombang_id'], 'tarif_spmb_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sikeu_tarif_spmb');
    }
};

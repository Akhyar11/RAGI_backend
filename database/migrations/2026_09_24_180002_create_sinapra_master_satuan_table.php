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
        Schema::create('sinapra_master_satuan', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama', 100);
            $table->text('keterangan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'urutan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sinapra_master_satuan');
    }
};

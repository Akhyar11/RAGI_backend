<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('core_jenis_biaya_modules')) {
            Schema::create('core_jenis_biaya_modules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('jenis_biaya_id')->constrained('sikeu_jenis_biaya')->onDelete('cascade');
                $table->string('module_code', 50)->index();
                $table->timestamps();

                $table->unique(['jenis_biaya_id', 'module_code'], 'jenis_biaya_module_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('core_jenis_biaya_modules');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('jenis_biaya_modules')) {
            Schema::create('jenis_biaya_modules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('jenis_biaya_id')->constrained('jenis_biaya')->onDelete('cascade');
                $table->string('module_code', 50)->index();
                $table->timestamps();

                $table->unique(['jenis_biaya_id', 'module_code'], 'jenis_biaya_module_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jenis_biaya_modules');
    }
};

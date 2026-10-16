<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sikeu_tutup_kas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_kas_id')->constrained('sikeu_unit_kas')->onDelete('cascade');
            $table->date('tanggal');
            $table->decimal('saldo_awal', 15, 2)->default(0);
            $table->decimal('saldo_masuk', 15, 2)->default(0);
            $table->decimal('saldo_keluar', 15, 2)->default(0);
            $table->decimal('saldo_akhir', 15, 2)->default(0);
            $table->boolean('is_locked')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('core_users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['unit_kas_id', 'tanggal']);
            $table->index(['tanggal', 'is_locked']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sikeu_tutup_kas');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sikeu_umk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_kas_id')->nullable()->constrained('sikeu_unit_kas')->onDelete('set null');
            $table->string('nama_penerima', 191);
            $table->date('tanggal');
            $table->date('tanggal_selesai')->nullable();
            $table->text('keterangan')->nullable();
            $table->decimal('total', 15, 2);
            $table->enum('status', ['aktif', 'selesai'])->default('aktif');
            $table->foreignId('created_by')->nullable()->constrained('core_users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'tanggal']);
            $table->index(['unit_kas_id', 'status']);
            $table->index('nama_penerima');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sikeu_umk');
    }
};

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
        Schema::create('maintenance_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aset_id')->nullable()->constrained('aset')->onDelete('set null');
            $table->foreignId('ruangan_id')->nullable()->constrained('ruangan')->onDelete('set null');
            $table->string('judul', 150);
            $table->text('deskripsi_kerusakan')->nullable();
            $table->enum('prioritas', ['rendah', 'sedang', 'tinggi', 'darurat'])->default('sedang');
            $table->date('tanggal_lapor');
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->decimal('biaya', 15, 2)->default(0.00);
            $table->text('hasil_perbaikan')->nullable();
            $table->enum('status', ['dilaporkan', 'dijadwalkan', 'dalam_proses', 'selesai'])->default('dilaporkan');
            $table->foreignId('teknisi_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['aset_id', 'ruangan_id', 'status', 'prioritas']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_log');
    }
};

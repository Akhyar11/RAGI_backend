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
        Schema::create('ruangan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gedung_id')->constrained('gedung')->onDelete('restrict');
            $table->string('kode', 50)->unique();
            $table->string('nama', 100);
            $table->integer('lantai')->default(1);
            $table->enum('tipe', ['kelas', 'lab', 'aula', 'kantor', 'gudang', 'toilet', 'lainnya'])->default('kelas');
            $table->integer('kapasitas')->default(0);
            $table->boolean('ada_ac')->default(false);
            $table->boolean('ada_proyektor')->default(false);
            $table->boolean('ada_wifi')->default(false);
            $table->enum('status', ['aktif', 'maintenance', 'tidak_aktif'])->default('aktif');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['gedung_id', 'tipe', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ruangan');
    }
};

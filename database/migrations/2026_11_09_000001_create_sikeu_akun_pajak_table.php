<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sikeu_akun_pajak', function (Blueprint $table) {
            $table->id();
            $table->foreignId('akun_id')
                ->unique()
                ->constrained('sikeu_akun_keuangan')
                ->cascadeOnDelete();
            $table->string('nama_akun_pajak')->nullable();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('core_users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sikeu_akun_pajak');
    }
};

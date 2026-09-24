<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siakad_bank_soal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rps_id')->constrained('siakad_rps')->cascadeOnDelete();
            $table->foreignId('rps_mingguan_id')->nullable()->constrained('siakad_rps_mingguan')->cascadeOnDelete();
            $table->foreignId('sub_cpmk_id')->nullable()->constrained('siakad_sub_cpmk')->nullOnDelete();
            $table->text('pertanyaan');
            $table->decimal('bobot', 5, 2)->default(0);
            $table->text('kunci_jawaban')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('core_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siakad_bank_soal');
    }
};

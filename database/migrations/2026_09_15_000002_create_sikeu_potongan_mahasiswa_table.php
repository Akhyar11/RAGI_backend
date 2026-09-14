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
        if (!Schema::hasTable('sikeu_potongan_mahasiswa')) {
            Schema::create('sikeu_potongan_mahasiswa', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mahasiswa_id');
                $table->string('nim')->nullable()->index();
                $table->string('nama_mahasiswa')->nullable();
                $table->string('nama_potongan'); // Misal: Keringanan UKT Rektorat, Diskon Saudara Kandung, Potongan Anak Pegawai, dll.
                $table->enum('tipe_potongan', ['nominal', 'persen'])->default('nominal');
                $table->decimal('nilai_potongan', 15, 2)->default(0);
                $table->foreignId('master_biaya_id')->nullable()->constrained('sikeu_master_biaya')->nullOnDelete();
                $table->integer('semester')->nullable(); // null jika berlaku untuk semua semester
                $table->string('tahun_akademik')->nullable(); // misal: 2026/2027
                $table->date('berlaku_mulai')->nullable();
                $table->date('berlaku_sampai')->nullable();
                $table->string('nomor_sk')->nullable();
                $table->text('keterangan')->nullable();
                $table->enum('status', ['aktif', 'nonaktif', 'selesai'])->default('aktif');
                $table->unsignedBigInteger('diinput_oleh')->nullable();
                $table->timestamps();

                $table->index(['mahasiswa_id', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sikeu_potongan_mahasiswa');
    }
};

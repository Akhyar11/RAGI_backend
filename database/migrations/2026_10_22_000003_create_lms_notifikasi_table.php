<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notifikasi in-app modul LMS (Fase 3A).
     *
     * Berbeda dengan notifikasi donor yang dihitung on-the-fly, tabel ini
     * menyimpan notifikasi sebagai record agar status baca/unread konsisten
     * antar perangkat dan dapat di-audit (siapa sudah membaca pengingat).
     *
     * `dedup_key` mencegah notifikasi deadline yang sama terkirim berulang
     * (mis. command harian dijalankan beberapa kali).
     */
    public function up(): void
    {
        if (!Schema::hasTable('lms_notifikasi')) {
            Schema::create('lms_notifikasi', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('core_users')->cascadeOnDelete();
                // Jenis notifikasi (closed-set domain, bukan entitas master).
                $table->string('jenis', 50);
                $table->string('judul', 200);
                $table->text('pesan')->nullable();
                // Rute frontend tujuan (mis. /lms/tugas/123).
                $table->string('rute', 255)->nullable();
                $table->foreignId('kelas_id')->nullable()->constrained('siakad_kelas')->nullOnDelete();
                // Referensi polymorphic sumber notifikasi (mis. lms_tugas, lms_quiz).
                $table->string('entitas_type', 100)->nullable();
                $table->unsignedBigInteger('entitas_id')->nullable();
                $table->string('prioritas', 20)->default('normal');
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->string('dedup_key', 191)->nullable();
                $table->timestamps();

                $table->index(['user_id', 'is_read']);
                $table->index(['user_id', 'created_at']);
                $table->index(['entitas_type', 'entitas_id']);
                $table->unique(['user_id', 'dedup_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_notifikasi');
    }
};

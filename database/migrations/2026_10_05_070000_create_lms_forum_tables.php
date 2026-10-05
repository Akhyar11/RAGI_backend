<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forum diskusi LMS Fase C (ala Project/lms LmsForumTopik/Post).
     * Topik per kelas (opsional per pertemuan); balasan 1 level (parent_id).
     * Nama penulis di-snapshot agar riwayat tetap bermakna bila akun dihapus.
     */
    public function up(): void
    {
        if (!Schema::hasTable('lms_forum_topik')) {
            Schema::create('lms_forum_topik', function (Blueprint $table) {
                $table->id();
                $table->foreignId('kelas_id')->constrained('siakad_kelas')->cascadeOnDelete();
                $table->foreignId('pertemuan_id')->nullable()->constrained('siakad_pertemuan')->cascadeOnDelete();
                $table->string('judul');
                $table->foreignId('dibuat_oleh')->nullable()->constrained('core_users')->nullOnDelete();
                $table->boolean('is_pinned')->default(false);
                $table->timestamps();

                $table->index(['kelas_id', 'is_pinned']);
            });
        }

        if (!Schema::hasTable('lms_forum_post')) {
            Schema::create('lms_forum_post', function (Blueprint $table) {
                $table->id();
                $table->foreignId('topik_id')->constrained('lms_forum_topik')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('lms_forum_post')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('core_users')->nullOnDelete();
                $table->string('nama_penulis', 100);
                $table->text('isi');
                $table->timestamps();

                $table->index(['topik_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_forum_post');
        Schema::dropIfExists('lms_forum_topik');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alur LMS baru:
     * - Kolaborator quiz (dosen pengawas/pemantau/penginput soal per quiz).
     * - Menu kiri Pertemuan (/lms/pertemuan) dinonaktifkan (tanpa delete
     *   agar rollback aman dan relasi role tidak hilang).
     */
    public function up(): void
    {
        if (!Schema::hasTable('lms_quiz_kolaborator')) {
            Schema::create('lms_quiz_kolaborator', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quiz_id')->constrained('lms_quiz')->cascadeOnDelete();
                $table->foreignId('dosen_id')->constrained('siakad_dosen')->cascadeOnDelete();
                $table->enum('peran', ['pengawas', 'pemantau', 'penginput_soal'])->default('pengawas');
                $table->foreignId('ditambah_oleh')->nullable()->constrained('core_users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['quiz_id', 'dosen_id']);
                $table->index(['quiz_id', 'peran']);
            });
        }

        if (Schema::hasTable('core_menus')) {
            DB::table('core_menus')->where('url', '/lms/pertemuan')->update(['is_active' => false]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_quiz_kolaborator');

        if (Schema::hasTable('core_menus')) {
            DB::table('core_menus')->where('url', '/lms/pertemuan')->update(['is_active' => true]);
        }
    }
};

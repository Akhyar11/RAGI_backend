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
        Schema::create('simpeg_campus_announcements', function (Blueprint $table) {
            $table->id();
            // Foreign key merujuk ke tabel akun pengguna ekosistem kampus terintegrasi (tabel model User adalah core_users ber-prefix core_)
            $table->foreignId('sender_id')->constrained('core_users')->cascadeOnDelete();
            $table->string('title');
            $table->string('category', 50)->default('umum');
            $table->string('target_role', 50)->default('all_staff');
            $table->text('content');
            $table->boolean('is_agenda')->default(false);
            $table->date('event_date')->nullable();
            $table->string('event_time', 50)->nullable();
            $table->string('event_location', 255)->nullable();
            $table->string('event_url', 500)->nullable();
            $table->string('priority', 20)->default('normal');
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'target_role']);
            $table->index(['is_agenda', 'event_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_campus_announcements');
    }
};

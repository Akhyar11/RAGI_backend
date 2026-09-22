<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Sesi impersonasi (rasuki pengguna) per-token per-device.
     * Satu admin boleh merasuki akun A di device 1 dan akun B di device 2
     * secara bersamaan — kunci isolasi adalah impersonation_token_id
     * (id baris oauth_access_tokens), BUKAN admin_id.
     */
    public function up(): void
    {
        Schema::create('core_impersonation_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('core_users')->onDelete('cascade');
            $table->foreignId('target_user_id')->constrained('core_users')->onDelete('cascade');

            // id baris oauth_access_tokens (Passport: char(80) primary key)
            $table->string('impersonation_token_id', 80)->unique();
            $table->string('admin_token_id', 80)->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();

            $table->timestamps();

            $table->index(['admin_id', 'ended_at']);
            $table->index(['target_user_id', 'ended_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('core_impersonation_sessions');
    }
};

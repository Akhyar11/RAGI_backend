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
        if (Schema::hasTable('core_sso_tokens')) {
            Schema::table('core_sso_tokens', function (Blueprint $table) {
                if (!Schema::hasColumn('core_sso_tokens', 'revoked_at')) {
                    $table->timestamp('revoked_at')->nullable()->after('refresh_expires_at');
                    $table->index('revoked_at', 'idx_sso_tokens_revoked');
                }
                if (!Schema::hasColumn('core_sso_tokens', 'rotated_to_id')) {
                    $table->unsignedBigInteger('rotated_to_id')->nullable()->after('revoked_at');
                    $table->foreign('rotated_to_id', 'fk_sso_tokens_rotated_to')
                        ->references('id')
                        ->on('core_sso_tokens')
                        ->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('core_sso_tokens')) {
            Schema::table('core_sso_tokens', function (Blueprint $table) {
                if (Schema::hasColumn('core_sso_tokens', 'rotated_to_id')) {
                    $table->dropForeign('fk_sso_tokens_rotated_to');
                    $table->dropColumn('rotated_to_id');
                }
                if (Schema::hasColumn('core_sso_tokens', 'revoked_at')) {
                    $table->dropIndex('idx_sso_tokens_revoked');
                    $table->dropColumn('revoked_at');
                }
            });
        }
    }
};

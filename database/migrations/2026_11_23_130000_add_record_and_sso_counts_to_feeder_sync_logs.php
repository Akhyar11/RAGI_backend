<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siakad_feeder_sync_logs', function (Blueprint $table): void {
            foreach ([
                'created_count',
                'updated_count',
                'sso_created_count',
                'sso_updated_count',
            ] as $column) {
                if (! Schema::hasColumn('siakad_feeder_sync_logs', $column)) {
                    $table->unsignedInteger($column)->default(0);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('siakad_feeder_sync_logs', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                'created_count',
                'updated_count',
                'sso_created_count',
                'sso_updated_count',
            ], static fn (string $column): bool => Schema::hasColumn('siakad_feeder_sync_logs', $column)));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('iam_api_integrations', 'source_type')) {
            Schema::table('iam_api_integrations', function (Blueprint $table): void {
                $table->string('source_type', 30)->default('custom_api')->after('expected_schema');
            });
        }

        if (!Schema::hasColumn('iam_api_integrations', 'supported_sources')) {
            Schema::table('iam_api_integrations', function (Blueprint $table): void {
                $table->json('supported_sources')->nullable()->after('source_type');
            });
        }

        if (!Schema::hasColumn('iam_api_integrations', 'source_schemas')) {
            Schema::table('iam_api_integrations', function (Blueprint $table): void {
                $table->json('source_schemas')->nullable()->after('supported_sources');
            });
        }

        if (!Schema::hasColumn('siakad_kurikulum', 'id_feeder')) {
            Schema::table('siakad_kurikulum', function (Blueprint $table): void {
                $table->string('id_feeder', 100)->nullable()->after('kode')->unique();
            });
        }

        if (Schema::hasTable('iam_api_integrations')) {
            DB::table('iam_api_integrations')
                ->where('key', 'siakad_mata_kuliah')
                ->update(['source_type' => 'pddikti']);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('siakad_kurikulum', 'id_feeder')) {
            Schema::table('siakad_kurikulum', function (Blueprint $table): void {
                $table->dropUnique('siakad_kurikulum_id_feeder_unique');
                $table->dropColumn('id_feeder');
            });
        }

        if (Schema::hasColumn('iam_api_integrations', 'source_type')) {
            Schema::table('iam_api_integrations', function (Blueprint $table): void {
                if (Schema::hasColumn('iam_api_integrations', 'source_schemas')) {
                    $table->dropColumn('source_schemas');
                }
                if (Schema::hasColumn('iam_api_integrations', 'supported_sources')) {
                    $table->dropColumn('supported_sources');
                }
                $table->dropColumn('source_type');
            });
        }
    }
};
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
        Schema::table('simpeg_pegawai', function (Blueprint $table) {
            if (!Schema::hasColumn('pegawai', 'sinta_id')) {
                $table->string('sinta_id', 50)->nullable()->after('telepon');
            }
            if (!Schema::hasColumn('pegawai', 'scopus_id')) {
                $table->string('scopus_id', 50)->nullable()->after('sinta_id');
            }
            if (!Schema::hasColumn('pegawai', 'google_scholar_id')) {
                $table->string('google_scholar_id', 100)->nullable()->after('scopus_id');
            }
            if (!Schema::hasColumn('pegawai', 'orcid_id')) {
                $table->string('orcid_id', 50)->nullable()->after('google_scholar_id');
            }
        });

        Schema::table('sippm_publikasi_ilmiah', function (Blueprint $table) {
            if (!Schema::hasColumn('publikasi_ilmiah', 'scopus_eid')) {
                $table->string('scopus_eid', 100)->nullable()->index()->after('doi');
            }
            if (!Schema::hasColumn('publikasi_ilmiah', 'sinta_article_id')) {
                $table->string('sinta_article_id', 100)->nullable()->index()->after('scopus_eid');
            }
            if (!Schema::hasColumn('publikasi_ilmiah', 'citation_count')) {
                $table->integer('citation_count')->default(0)->after('sinta_article_id');
            }
            if (!Schema::hasColumn('publikasi_ilmiah', 'publisher')) {
                $table->string('publisher', 255)->nullable()->after('citation_count');
            }
            if (!Schema::hasColumn('publikasi_ilmiah', 'synced_at')) {
                $table->timestamp('synced_at')->nullable()->after('publisher');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_pegawai', function (Blueprint $table) {
            $table->dropColumn(['sinta_id', 'scopus_id', 'google_scholar_id', 'orcid_id']);
        });

        Schema::table('sippm_publikasi_ilmiah', function (Blueprint $table) {
            $table->dropColumn(['scopus_eid', 'sinta_article_id', 'citation_count', 'publisher', 'synced_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Penampungan & Riwayat Log Sinkronisasi Neo Feeder
        Schema::create('siakad_feeder_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('entity_type', ['mahasiswa', 'dosen', 'mata_kuliah', 'kurikulum', 'kelas', 'krs', 'nilai']);
            $table->enum('sync_type', ['push', 'pull']);
            $table->integer('total_records')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->enum('status', ['pending', 'processing', 'success', 'failed', 'partial'])->default('pending');
            $table->json('details')->nullable()->comment('Menyimpan pesan sukses/error per record');
            $table->foreignId('synced_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // 2. Tabel Mapping ID Lokal ke ID DIKTI (Neo Feeder)
        Schema::create('siakad_feeder_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50)->comment('mahasiswa, dosen, mata_kuliah, kelas, dll');
            $table->unsignedBigInteger('local_id');
            $table->string('feeder_id', 100)->nullable();
            $table->enum('sync_status', ['synced', 'pending', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['entity_type', 'local_id'], 'feeder_entity_local_unique');
            $table->index(['entity_type', 'sync_status']);
        });

        // 3. Tambahkan kolom feeder_id di tabel mahasiswa, dosen, mata_kuliah, kelas jika belum ada
        Schema::table('siakad_mahasiswa', function (Blueprint $table) {
            if (!Schema::hasColumn('siakad_mahasiswa', 'id_feeder')) {
                $table->string('id_feeder', 100)->nullable()->after('status');
            }
        });

        Schema::table('siakad_dosen', function (Blueprint $table) {
            if (!Schema::hasColumn('siakad_dosen', 'id_feeder')) {
                $table->string('id_feeder', 100)->nullable()->after('is_active');
            }
        });

        Schema::table('siakad_mata_kuliah', function (Blueprint $table) {
            if (!Schema::hasColumn('siakad_mata_kuliah', 'id_feeder')) {
                $table->string('id_feeder', 100)->nullable()->after('is_active');
            }
        });

        Schema::table('siakad_kelas', function (Blueprint $table) {
            if (!Schema::hasColumn('siakad_kelas', 'id_feeder')) {
                $table->string('id_feeder', 100)->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siakad_feeder_mappings');
        Schema::dropIfExists('siakad_feeder_sync_logs');

        Schema::table('siakad_mahasiswa', function (Blueprint $table) {
            $table->dropColumn('id_feeder');
        });
        Schema::table('siakad_dosen', function (Blueprint $table) {
            $table->dropColumn('id_feeder');
        });
        Schema::table('siakad_mata_kuliah', function (Blueprint $table) {
            $table->dropColumn('id_feeder');
        });
        Schema::table('siakad_kelas', function (Blueprint $table) {
            $table->dropColumn('id_feeder');
        });
    }
};

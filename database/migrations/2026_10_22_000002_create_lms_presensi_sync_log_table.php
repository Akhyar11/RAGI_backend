<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integrasi LMS -> PresensiV2 (LMS-03).
 *
 * TIDAK membuat tabel `lms_presensi_sesi` / `lms_presensi_rekap` seperti di
 * donor: RAG sudah memakai `siakad_pertemuan` (sesi) dan
 * `siakad_absensi_mahasiswa` (rekap). Yang ditambahkan hanya:
 *  1. `lms_presensi_sync_log` — jejak/bookkeeping setiap percobaan sinkron.
 *  2. Kolom status sinkron pada `siakad_pertemuan` & `siakad_absensi_mahasiswa`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lms_presensi_sync_log')) {
            Schema::create('lms_presensi_sync_log', function (Blueprint $table) {
                $table->id();

                // Entitas lokal yang disinkronkan: pertemuan | absensi.
                $table->string('entitas', 20)->index();

                // Id lokal dari entitas tsb (id pertemuan / id absensi).
                $table->unsignedBigInteger('entitas_id')->index();

                // Konteks kelas & pertemuan agar log mudah difilter.
                $table->unsignedBigInteger('kelas_id')->nullable()->index();
                $table->unsignedInteger('pertemuan_ke')->nullable();

                // Aksi: buka_jurnal | hadir | hapus_hadir | push_kehadiran | reset | tarik.
                $table->string('aksi', 40);

                // Status: success | failed | partial | skipped.
                $table->string('status', 20)->index();

                // Transport: api | db | local | none.
                $table->string('via', 10)->default('none');

                $table->text('message')->nullable();
                $table->json('payload')->nullable();

                $table->foreignId('user_id')->nullable()->constrained('core_users')->nullOnDelete();
                $table->timestamps();

                $table->index(['entitas', 'entitas_id']);
                $table->index(['kelas_id', 'aksi']);
            });
        }

        if (Schema::hasTable('siakad_pertemuan') && ! Schema::hasColumn('siakad_pertemuan', 'id_jurnal_presensiv2')) {
            Schema::table('siakad_pertemuan', function (Blueprint $table) {
                // Id jurnal pada aplikasi PresensiV2 (bila sudah pernah tersinkron).
                $table->unsignedBigInteger('id_jurnal_presensiv2')->nullable()->after('token_expired_at')->index();

                // Status sinkron: local | pending | synced | failed.
                // Default `local` = data hanya tersimpan di LMS, belum pernah
                // disinkronkan ke PresensiV2 (bukan "menunggu sinkron" — agar
                // perintah retry tidak mencoba thousands record yang memang
                // tidak pernah diintegrasikan).
                $table->string('sync_status', 20)->default('local')->after('id_jurnal_presensiv2')->index();

                $table->text('sync_error')->nullable()->after('sync_status');
                $table->timestamp('synced_at')->nullable()->after('sync_error');
            });
        }

        if (Schema::hasTable('siakad_absensi_mahasiswa') && ! Schema::hasColumn('siakad_absensi_mahasiswa', 'sync_status')) {
            Schema::table('siakad_absensi_mahasiswa', function (Blueprint $table) {
                $table->string('sync_status', 20)->default('local')->after('catatan')->index();
                $table->text('sync_error')->nullable()->after('sync_status');
                $table->timestamp('synced_at')->nullable()->after('sync_error');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('siakad_absensi_mahasiswa') && Schema::hasColumn('siakad_absensi_mahasiswa', 'sync_status')) {
            Schema::table('siakad_absensi_mahasiswa', function (Blueprint $table) {
                $table->dropColumn(['sync_status', 'sync_error', 'synced_at']);
            });
        }

        if (Schema::hasTable('siakad_pertemuan') && Schema::hasColumn('siakad_pertemuan', 'id_jurnal_presensiv2')) {
            Schema::table('siakad_pertemuan', function (Blueprint $table) {
                $table->dropColumn(['id_jurnal_presensiv2', 'sync_status', 'sync_error', 'synced_at']);
            });
        }

        Schema::dropIfExists('lms_presensi_sync_log');
    }
};

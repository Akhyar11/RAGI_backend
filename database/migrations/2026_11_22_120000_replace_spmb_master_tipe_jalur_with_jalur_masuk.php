<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('spmb_pendaftaran_alur')) {
            Schema::drop('spmb_pendaftaran_alur');
        }

        if (Schema::hasTable('spmb_master_tipe_jalur_alur')) {
            Schema::drop('spmb_master_tipe_jalur_alur');
        }

        if (Schema::hasTable('spmb_kode_diskon_jenis_pendaftaran')) {
            Schema::drop('spmb_kode_diskon_jenis_pendaftaran');
        }

        if (Schema::hasTable('spmb_master_biaya') && Schema::hasColumn('spmb_master_biaya', 'master_tipe_jalur_id')) {
            if (Schema::hasTable('spmb_master_biaya_item')) {
                $biayaIds = DB::table('spmb_master_biaya')->pluck('id');
                DB::table('spmb_master_biaya_item')->whereIn('master_biaya_id', $biayaIds)->delete();
            }
            DB::table('spmb_master_biaya')->delete();

            Schema::table('spmb_master_biaya', function (Blueprint $table): void {
                $table->dropForeign(['master_tipe_jalur_id']);
                $table->dropUnique('spmb_master_biaya_tipe_prodi_unique');
                $table->dropColumn('master_tipe_jalur_id');
            });

            Schema::table('spmb_master_biaya', function (Blueprint $table): void {
                $table->foreignId('jalur_masuk_id')
                    ->after('program_studi_id')
                    ->constrained('spmb_jalur_masuk')
                    ->restrictOnDelete();
                $table->unique(['jalur_masuk_id', 'program_studi_id'], 'spmb_master_biaya_jalur_prodi_unique');
            });
        }

        if (Schema::hasTable('spmb_pendaftaran_calon_mhs') && Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'master_tipe_jalur_id')) {
            Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table): void {
                $table->dropForeign(['master_tipe_jalur_id']);
                $table->dropColumn('master_tipe_jalur_id');
            });
        }

        if (Schema::hasTable('spmb_jalur_masuk')) {
            foreach (['tipe', 'ada_ujian_tulis', 'ada_ujian_praktik'] as $column) {
                if (Schema::hasColumn('spmb_jalur_masuk', $column)) {
                    Schema::table('spmb_jalur_masuk', function (Blueprint $table) use ($column): void {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        Schema::dropIfExists('core_master_tipe_jalur');
    }

    public function down(): void
    {
        Schema::create('core_master_tipe_jalur', function (Blueprint $table): void {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama', 100);
            $table->timestamps();
        });

        if (Schema::hasTable('spmb_jalur_masuk')) {
            Schema::table('spmb_jalur_masuk', function (Blueprint $table): void {
                $table->enum('tipe', ['reguler', 'transfer', 'beasiswa', 'internasional', 'rpla'])->default('reguler');
                $table->boolean('ada_ujian_tulis')->default(false);
                $table->boolean('ada_ujian_praktik')->default(false);
            });
        }

        if (Schema::hasTable('spmb_pendaftaran_calon_mhs')) {
            Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table): void {
                $table->foreignId('master_tipe_jalur_id')->nullable()->constrained('core_master_tipe_jalur')->nullOnDelete();
            });
        }

        if (Schema::hasTable('spmb_master_biaya')) {
            Schema::table('spmb_master_biaya', function (Blueprint $table): void {
                $table->dropForeign(['jalur_masuk_id']);
                $table->dropUnique('spmb_master_biaya_jalur_prodi_unique');
                $table->dropColumn('jalur_masuk_id');
            });

            Schema::table('spmb_master_biaya', function (Blueprint $table): void {
                $table->foreignId('master_tipe_jalur_id')->nullable()->after('program_studi_id')->constrained('core_master_tipe_jalur')->nullOnDelete();
                $table->unique(['master_tipe_jalur_id', 'program_studi_id'], 'spmb_master_biaya_tipe_prodi_unique');
            });
        }

        Schema::create('spmb_master_tipe_jalur_alur', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('master_tipe_jalur_id')->constrained('core_master_tipe_jalur')->cascadeOnDelete();
            $table->string('nama_tahap');
            $table->integer('urutan')->default(1);
            $table->timestamps();
        });

        Schema::create('spmb_pendaftaran_alur', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('spmb_pendaftaran_calon_mhs')->cascadeOnDelete();
            $table->foreignId('master_tipe_jalur_alur_id')->constrained('spmb_master_tipe_jalur_alur')->cascadeOnDelete();
            $table->string('status', 30)->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('diperbarui_oleh')->nullable()->constrained('core_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('spmb_kode_diskon_jenis_pendaftaran', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kode_diskon_id')->constrained('spmb_kode_diskon')->cascadeOnDelete();
            $table->foreignId('master_tipe_jalur_id')->constrained('core_master_tipe_jalur')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['kode_diskon_id', 'master_tipe_jalur_id'], 'spmb_kode_diskon_tipe_jalur_unique');
        });
    }
};
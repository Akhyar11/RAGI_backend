<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Standarisasi penamaan tabel migrator dengan prefix modul sikeu_ & siakad_.
     */
    public function up(): void
    {
        if (Schema::hasTable('_mig_akun_mapping') && !Schema::hasTable('sikeu_mig_akun_mapping')) {
            Schema::rename('_mig_akun_mapping', 'sikeu_mig_akun_mapping');
        } elseif (!Schema::hasTable('sikeu_mig_akun_mapping')) {
            Schema::create('sikeu_mig_akun_mapping', function (Blueprint $table) {
                $table->id();
                $table->string('kd_perkiraan_lama')->comment('Kode akun dari sistem lama');
                $table->string('nama_akun_lama')->nullable();
                $table->foreignId('akun_id_baru')
                    ->nullable()
                    ->constrained('sikeu_akun_keuangan')
                    ->nullOnDelete();
                $table->string('kode_akun_baru')->nullable();
                $table->timestamps();
                $table->index('kd_perkiraan_lama');
            });
        }

        if (Schema::hasTable('_mig_unresolved') && !Schema::hasTable('sikeu_mig_unresolved')) {
            Schema::rename('_mig_unresolved', 'sikeu_mig_unresolved');
        } elseif (!Schema::hasTable('sikeu_mig_unresolved')) {
            Schema::create('sikeu_mig_unresolved', function (Blueprint $table) {
                $table->id();
                $table->string('source_table');
                $table->string('legacy_key');
                $table->json('raw_data');
                $table->string('failure_reason');
                $table->enum('status', ['pending_review', 'resolved_manual', 'skipped'])->default('pending_review');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['source_table', 'status']);
            });
        }

        if (Schema::hasTable('_mig_sync_state') && !Schema::hasTable('sikeu_mig_sync_state')) {
            Schema::rename('_mig_sync_state', 'sikeu_mig_sync_state');
        } elseif (!Schema::hasTable('sikeu_mig_sync_state')) {
            Schema::create('sikeu_mig_sync_state', function (Blueprint $table) {
                $table->string('module_id', 50);
                $table->string('source_name', 50);
                $table->string('entity_type', 50);
                $table->dateTime('last_record_date')->nullable();
                $table->timestamp('last_sync_time')->nullable();
                $table->integer('total_records')->default(0);
                $table->timestamps();
                $table->primary(['module_id', 'source_name', 'entity_type']);
            });
        }
    }

    /**
     * Reverse the migrations.
     * Mengembalikan nama tabel lama dengan aman jika tabel awal ada sebelum migrasi.
     */
    public function down(): void
    {
        if (Schema::hasTable('sikeu_mig_sync_state')) {
            if (!Schema::hasTable('_mig_sync_state')) {
                Schema::rename('sikeu_mig_sync_state', '_mig_sync_state');
            } else {
                Schema::dropIfExists('sikeu_mig_sync_state');
            }
        }

        if (Schema::hasTable('sikeu_mig_unresolved')) {
            if (!Schema::hasTable('_mig_unresolved')) {
                Schema::rename('sikeu_mig_unresolved', '_mig_unresolved');
            } else {
                Schema::dropIfExists('sikeu_mig_unresolved');
            }
        }

        if (Schema::hasTable('sikeu_mig_akun_mapping')) {
            if (!Schema::hasTable('_mig_akun_mapping')) {
                Schema::rename('sikeu_mig_akun_mapping', '_mig_akun_mapping');
            } else {
                Schema::dropIfExists('sikeu_mig_akun_mapping');
            }
        }
    }
};

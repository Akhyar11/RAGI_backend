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
        if (Schema::hasTable('jenis_biaya') && !Schema::hasColumn('jenis_biaya', 'nominal_standar')) {
            Schema::table('jenis_biaya', function (Blueprint $table) {
                $table->decimal('nominal_standar', 15, 2)->default(0)->after('tipe');
            });
        }

        if (Schema::hasTable('beasiswa')) {
            Schema::table('beasiswa', function (Blueprint $table) {
                if (!Schema::hasColumn('beasiswa', 'jenis_biaya_id')) {
                    $table->foreignId('jenis_biaya_id')->nullable()->after('tipe_potongan')->constrained('jenis_biaya')->onDelete('set null');
                }
                if (!Schema::hasColumn('beasiswa', 'berlaku_angkatan_mulai')) {
                    $table->integer('berlaku_angkatan_mulai')->nullable()->after('jenis_biaya_id');
                }
                if (!Schema::hasColumn('beasiswa', 'berlaku_angkatan_sampai')) {
                    $table->integer('berlaku_angkatan_sampai')->nullable()->after('berlaku_angkatan_mulai');
                }
            });
        }

        if (!Schema::hasTable('master_jalur_kelas')) {
            Schema::create('master_jalur_kelas', function (Blueprint $table) {
                $table->id();
                $table->string('kode')->unique();
                $table->string('nama_jalur');
                $table->text('deskripsi')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('jenis_biaya') && Schema::hasColumn('jenis_biaya', 'nominal_standar')) {
            Schema::table('jenis_biaya', function (Blueprint $table) {
                $table->dropColumn('nominal_standar');
            });
        }

        if (Schema::hasTable('beasiswa')) {
            Schema::table('beasiswa', function (Blueprint $table) {
                if (Schema::hasColumn('beasiswa', 'jenis_biaya_id')) {
                    $table->dropForeign(['jenis_biaya_id']);
                    $table->dropColumn(['jenis_biaya_id', 'berlaku_angkatan_mulai', 'berlaku_angkatan_sampai']);
                }
            });
        }

        Schema::dropIfExists('master_jalur_kelas');
    }
};

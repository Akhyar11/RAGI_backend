<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sikeu_master_gaji_pegawai')) {
            Schema::create('sikeu_master_gaji_pegawai', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pegawai_id')->unique()->constrained('simpeg_pegawai')->onDelete('cascade');
                $table->decimal('gaji_pokok', 12, 2)->default(5000000);
                $table->decimal('tunjangan_tetap', 12, 2)->default(1500000);
                $table->decimal('potongan_tetap', 12, 2)->default(200000);
                $table->decimal('tarif_transport_harian', 12, 2)->default(50000);
                $table->text('catatan')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('simpeg_gaji_pegawai')) {
            Schema::table('simpeg_gaji_pegawai', function (Blueprint $table) {
                if (!Schema::hasColumn('simpeg_gaji_pegawai', 'tunjangan_tetap')) {
                    $table->decimal('tunjangan_tetap', 12, 2)->default(0)->after('gaji_pokok');
                }
                if (!Schema::hasColumn('simpeg_gaji_pegawai', 'total_biaya_transport')) {
                    $table->decimal('total_biaya_transport', 12, 2)->default(0)->after('tunjangan_tetap');
                }
                if (!Schema::hasColumn('simpeg_gaji_pegawai', 'jumlah_hari_hadir_tepat_waktu')) {
                    $table->integer('jumlah_hari_hadir_tepat_waktu')->default(0)->after('total_biaya_transport');
                }
                if (!Schema::hasColumn('simpeg_gaji_pegawai', 'catatan')) {
                    $table->text('catatan')->nullable()->after('bank_nama');
                }
                if (!Schema::hasColumn('simpeg_gaji_pegawai', 'submitted_at')) {
                    $table->timestamp('submitted_at')->nullable()->after('catatan');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('simpeg_gaji_pegawai')) {
            Schema::table('simpeg_gaji_pegawai', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('simpeg_gaji_pegawai', 'tunjangan_tetap')) $columns[] = 'tunjangan_tetap';
                if (Schema::hasColumn('simpeg_gaji_pegawai', 'total_biaya_transport')) $columns[] = 'total_biaya_transport';
                if (Schema::hasColumn('simpeg_gaji_pegawai', 'jumlah_hari_hadir_tepat_waktu')) $columns[] = 'jumlah_hari_hadir_tepat_waktu';
                if (Schema::hasColumn('simpeg_gaji_pegawai', 'catatan')) $columns[] = 'catatan';
                if (Schema::hasColumn('simpeg_gaji_pegawai', 'submitted_at')) $columns[] = 'submitted_at';
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }

        Schema::dropIfExists('sikeu_master_gaji_pegawai');
    }
};

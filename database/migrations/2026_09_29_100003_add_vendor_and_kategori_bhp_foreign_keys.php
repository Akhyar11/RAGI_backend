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
        if (Schema::hasTable('sinapra_alat_kalibrasi')) {
            Schema::table('sinapra_alat_kalibrasi', function (Blueprint $table) {
                if (!Schema::hasColumn('sinapra_alat_kalibrasi', 'vendor_id')) {
                    $table->foreignId('vendor_id')->nullable()->after('aset_id')->constrained('sinapra_master_vendor')->onDelete('set null');
                }
            });
        }

        if (Schema::hasTable('sinapra_lab_bhp')) {
            Schema::table('sinapra_lab_bhp', function (Blueprint $table) {
                if (!Schema::hasColumn('sinapra_lab_bhp', 'kategori_bhp_id')) {
                    $table->foreignId('kategori_bhp_id')->nullable()->after('nama_bhp')->constrained('sinapra_master_kategori_bhp')->onDelete('set null');
                }
                if (!Schema::hasColumn('sinapra_lab_bhp', 'satuan_id')) {
                    $table->foreignId('satuan_id')->nullable()->after('stok_minimum')->constrained('sinapra_master_satuan')->onDelete('set null');
                }
            });
        }

        if (Schema::hasTable('sinapra_pengajuan_pengadaan')) {
            Schema::table('sinapra_pengajuan_pengadaan', function (Blueprint $table) {
                if (!Schema::hasColumn('sinapra_pengajuan_pengadaan', 'vendor_id')) {
                    $table->foreignId('vendor_id')->nullable()->after('diajukan_oleh')->constrained('sinapra_master_vendor')->onDelete('set null');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sinapra_alat_kalibrasi')) {
            Schema::table('sinapra_alat_kalibrasi', function (Blueprint $table) {
                if (Schema::hasColumn('sinapra_alat_kalibrasi', 'vendor_id')) {
                    $table->dropForeign(['vendor_id']);
                    $table->dropColumn('vendor_id');
                }
            });
        }

        if (Schema::hasTable('sinapra_lab_bhp')) {
            Schema::table('sinapra_lab_bhp', function (Blueprint $table) {
                if (Schema::hasColumn('sinapra_lab_bhp', 'kategori_bhp_id')) {
                    $table->dropForeign(['kategori_bhp_id']);
                    $table->dropColumn('kategori_bhp_id');
                }
                if (Schema::hasColumn('sinapra_lab_bhp', 'satuan_id')) {
                    $table->dropForeign(['satuan_id']);
                    $table->dropColumn('satuan_id');
                }
            });
        }

        if (Schema::hasTable('sinapra_pengajuan_pengadaan')) {
            Schema::table('sinapra_pengajuan_pengadaan', function (Blueprint $table) {
                if (Schema::hasColumn('sinapra_pengajuan_pengadaan', 'vendor_id')) {
                    $table->dropForeign(['vendor_id']);
                    $table->dropColumn('vendor_id');
                }
            });
        }
    }
};

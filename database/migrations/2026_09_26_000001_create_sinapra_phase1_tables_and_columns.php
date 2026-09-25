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
        // 1. Tabel penugasan laboran ke ruangan lab
        if (!Schema::hasTable('sinapra_laboran_ruangan')) {
            Schema::create('sinapra_laboran_ruangan', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ruangan_id')->constrained('sinapra_ruangan')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('core_users')->onDelete('cascade');
                $table->boolean('is_primary')->default(true);
                $table->timestamps();

                $table->unique(['ruangan_id', 'user_id'], 'uq_sinapra_laboran_ruangan');
                $table->index('user_id', 'idx_sinapra_laboran_user');
            });
        }

        // 2. Tambah flag is_borrowable dan is_lab_asset pada sinapra_aset
        Schema::table('sinapra_aset', function (Blueprint $table) {
            if (!Schema::hasColumn('sinapra_aset', 'is_borrowable')) {
                $table->boolean('is_borrowable')->default(true)->after('status');
            }
            if (!Schema::hasColumn('sinapra_aset', 'is_lab_asset')) {
                $table->boolean('is_lab_asset')->default(false)->after('is_borrowable');
            }
            $table->index(['is_borrowable', 'is_lab_asset'], 'idx_aset_borrowable_lab');
        });

        // 3. Kolom approval bertingkat pada sinapra_peminjaman_ruangan
        Schema::table('sinapra_peminjaman_ruangan', function (Blueprint $table) {
            if (!Schema::hasColumn('sinapra_peminjaman_ruangan', 'laboran_approved_by')) {
                $table->foreignId('laboran_approved_by')->nullable()->after('status')->constrained('core_users')->onDelete('set null');
            }
            if (!Schema::hasColumn('sinapra_peminjaman_ruangan', 'laboran_approved_at')) {
                $table->timestamp('laboran_approved_at')->nullable()->after('laboran_approved_by');
            }
            if (!Schema::hasColumn('sinapra_peminjaman_ruangan', 'catatan_laboran')) {
                $table->text('catatan_laboran')->nullable()->after('laboran_approved_at');
            }
            if (!Schema::hasColumn('sinapra_peminjaman_ruangan', 'admin_approved_at')) {
                $table->timestamp('admin_approved_at')->nullable()->after('disetujui_oleh');
            }
            $table->string('status', 50)->default('pending')->change();
        });

        // 4. Kolom approval bertingkat pada sinapra_peminjaman_aset
        Schema::table('sinapra_peminjaman_aset', function (Blueprint $table) {
            if (!Schema::hasColumn('sinapra_peminjaman_aset', 'laboran_approved_by')) {
                $table->foreignId('laboran_approved_by')->nullable()->after('status')->constrained('core_users')->onDelete('set null');
            }
            if (!Schema::hasColumn('sinapra_peminjaman_aset', 'laboran_approved_at')) {
                $table->timestamp('laboran_approved_at')->nullable()->after('laboran_approved_by');
            }
            if (!Schema::hasColumn('sinapra_peminjaman_aset', 'catatan_laboran')) {
                $table->text('catatan_laboran')->nullable()->after('laboran_approved_at');
            }
            if (!Schema::hasColumn('sinapra_peminjaman_aset', 'catatan_penolakan')) {
                $table->text('catatan_penolakan')->nullable()->after('catatan_laboran');
            }
            if (!Schema::hasColumn('sinapra_peminjaman_aset', 'admin_approved_at')) {
                $table->timestamp('admin_approved_at')->nullable()->after('disetujui_oleh');
            }
            $table->string('status', 50)->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sinapra_peminjaman_aset', function (Blueprint $table) {
            if (Schema::hasColumn('sinapra_peminjaman_aset', 'laboran_approved_by')) {
                $table->dropForeign(['laboran_approved_by']);
                $table->dropColumn(['laboran_approved_by', 'laboran_approved_at', 'catatan_laboran', 'catatan_penolakan', 'admin_approved_at']);
            }
        });

        Schema::table('sinapra_peminjaman_ruangan', function (Blueprint $table) {
            if (Schema::hasColumn('sinapra_peminjaman_ruangan', 'laboran_approved_by')) {
                $table->dropForeign(['laboran_approved_by']);
                $table->dropColumn(['laboran_approved_by', 'laboran_approved_at', 'catatan_laboran', 'admin_approved_at']);
            }
        });

        Schema::table('sinapra_aset', function (Blueprint $table) {
            $table->dropIndex('idx_aset_borrowable_lab');
            $table->dropColumn(['is_borrowable', 'is_lab_asset']);
        });

        Schema::dropIfExists('sinapra_laboran_ruangan');
    }
};

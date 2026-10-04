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
        Schema::table('sinapra_peminjaman_aset', function (Blueprint $table) {
            if (!Schema::hasColumn('sinapra_peminjaman_aset', 'nomor_identitas')) {
                $table->string('nomor_identitas', 50)->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('sinapra_peminjaman_aset', 'kontak_peminjam')) {
                $table->string('kontak_peminjam', 50)->nullable()->after('nomor_identitas');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sinapra_peminjaman_aset', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('sinapra_peminjaman_aset', 'nomor_identitas')) {
                $columns[] = 'nomor_identitas';
            }
            if (Schema::hasColumn('sinapra_peminjaman_aset', 'kontak_peminjam')) {
                $columns[] = 'kontak_peminjam';
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table) {
            // Data import legacy belum memiliki gelombang; ditetapkan kemudian oleh admin.
            $table->unsignedBigInteger('gelombang_id')->nullable()->change();
            $table->string('pekerjaan_ortu', 100)->nullable()->after('telp_ortu');
            $table->json('data_import')->nullable()->after('pekerjaan_ortu');
        });
    }

    public function down(): void
    {
        Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table) {
            $table->dropColumn(['pekerjaan_ortu', 'data_import']);
        });
    }
};

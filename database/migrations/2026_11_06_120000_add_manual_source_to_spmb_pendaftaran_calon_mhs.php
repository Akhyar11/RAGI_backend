<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table) {
            $table->string('sumber_pendaftaran', 20)->default('online')->after('status_pembayaran');
            $table->foreignId('diinput_oleh')->nullable()->after('sumber_pendaftaran')
                ->constrained('core_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diinput_oleh');
            $table->dropColumn('sumber_pendaftaran');
        });
    }
};

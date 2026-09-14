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
        Schema::table('siakad_dosen', function (Blueprint $table) {
            $table->string('nuptk', 30)->nullable()->after('nidn');
            $table->string('jenis_kelamin', 10)->nullable()->after('nama_lengkap');
            $table->string('tempat_lahir', 100)->nullable()->after('jenis_kelamin');
            $table->date('tanggal_lahir')->nullable()->after('tempat_lahir');
            $table->string('agama', 50)->nullable()->after('tanggal_lahir');
            $table->string('status_aktif', 50)->nullable()->default('Aktif')->after('is_active');
            $table->string('nik', 30)->nullable()->after('nip');
            $table->string('telepon', 30)->nullable()->after('jabatan_akademik');
            $table->string('handphone', 30)->nullable()->after('telepon');
            $table->string('email', 100)->nullable()->after('handphone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('siakad_dosen', function (Blueprint $table) {
            $table->dropColumn([
                'nuptk',
                'jenis_kelamin',
                'tempat_lahir',
                'tanggal_lahir',
                'agama',
                'status_aktif',
                'nik',
                'telepon',
                'handphone',
                'email',
            ]);
        });
    }
};

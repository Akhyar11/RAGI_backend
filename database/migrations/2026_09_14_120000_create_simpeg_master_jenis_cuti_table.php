<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simpeg_master_jenis_cuti', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->string('kode', 50)->nullable()->unique();
            $table->enum('tipe_durasi', ['ditetapkan', 'fleksibel'])->default('fleksibel');
            $table->integer('durasi_hari')->default(0);
            $table->string('satuan', 20)->default('hari');
            $table->boolean('lampiran_wajib')->default(false);
            $table->text('keterangan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('simpeg_pengajuan_cuti', function (Blueprint $table) {
            $table->foreignId('master_jenis_cuti_id')
                ->nullable()
                ->after('pegawai_id')
                ->constrained('simpeg_master_jenis_cuti')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('simpeg_pengajuan_cuti', function (Blueprint $table) {
            $table->dropForeign(['master_jenis_cuti_id']);
            $table->dropColumn('master_jenis_cuti_id');
        });

        Schema::dropIfExists('simpeg_master_jenis_cuti');
    }
};

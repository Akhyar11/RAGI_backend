<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create master data table for tipe jalur
        Schema::create('core_master_tipe_jalur', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama', 100);
            $table->timestamps();
        });

        // Insert initial data
        DB::table('core_master_tipe_jalur')->insert([
            ['kode' => 'reguler', 'nama' => 'Reguler'],
            ['kode' => 'transfer', 'nama' => 'Transfer / Pindahan'],
            ['kode' => 'beasiswa', 'nama' => 'Beasiswa'],
            ['kode' => 'internasional', 'nama' => 'Internasional'],
            ['kode' => 'rpla', 'nama' => 'RPL / Rekognisi Pembelajaran Lampau'],
        ]);

        // 2. Modify jalur_masuk to use foreign key instead of ENUM
        Schema::table('spmb_jalur_masuk', function (Blueprint $table) {
            $table->foreignId('master_tipe_jalur_id')->nullable()->after('deskripsi')
                ->constrained('core_master_tipe_jalur')->nullOnDelete();
        });

        // Map existing data (assuming 'tipe' is the enum column)
        DB::statement("UPDATE spmb_jalur_masuk SET master_tipe_jalur_id = (SELECT id FROM core_master_tipe_jalur WHERE core_master_tipe_jalur.kode = spmb_jalur_masuk.tipe LIMIT 1)");

        Schema::table('spmb_jalur_masuk', function (Blueprint $table) {
            // Drop old enum column
            $table->dropColumn('tipe');
        });

        // 3. Minimize ENUM on other tables by converting them to string
        Schema::table('spmb_gelombang_penerimaan', function (Blueprint $table) {
            $table->string('status', 30)->default('draft')->change();
        });

        Schema::table('spmb_pembayaran', function (Blueprint $table) {
            $table->string('status', 30)->default('pending')->change();
        });

        if (Schema::hasTable('jadwal_ujian_spmb')) {
            Schema::table('jadwal_ujian_spmb', function (Blueprint $table) {
                $table->string('tipe_ujian', 50)->change();
            });
        }

        Schema::table('spmb_pertanyaan_kuesioner', function (Blueprint $table) {
            $table->string('tipe', 30)->change();
        });

        Schema::table('spmb_hasil_seleksi', function (Blueprint $table) {
            $table->string('status', 50)->nullable()->change();
        });

        Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table) {
            $table->string('jenis_kelamin', 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Revert to ENUM is complex, we will just keep the new schema or handle safely.
        // For brevity in rollback, we drop the foreign key and table.
        Schema::table('spmb_jalur_masuk', function (Blueprint $table) {
            $table->string('tipe', 50)->nullable();
            $table->dropForeign(['master_tipe_jalur_id']);
            $table->dropColumn('master_tipe_jalur_id');
        });

        Schema::dropIfExists('core_master_tipe_jalur');
    }
};

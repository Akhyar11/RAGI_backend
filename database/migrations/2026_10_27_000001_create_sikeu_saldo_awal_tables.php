<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo awal (opening balance) akuntansi SIKEU.
 *
 * Dipakai saat cut-over/migrasi agar neraca memiliki saldo awal yang sah
 * tanpa harus mengubah saldo operasional unit kas satu per satu.
 * Alur: simpan draft -> posting (membentuk jurnal umum) -> batal posting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sikeu_saldo_awal', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->foreignId('periode_id')->nullable()
                ->constrained('sikeu_periode_akuntansi')->onDelete('set null');
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->text('keterangan')->nullable();
            $table->decimal('total_debet', 15, 2)->default(0);
            $table->decimal('total_kredit', 15, 2)->default(0);
            /**
             * Selisih debet-kredit yang otomatis dibebankan ke akun penyeimbang
             * ekuitas (301.03). Nilai positif = tambahan kredit ekuitas,
             * nilai negatif = tambahan debet ekuitas.
             */
            $table->decimal('nominal_penyeimbang', 15, 2)->default(0);
            $table->foreignId('akun_penyeimbang_id')->nullable()
                ->constrained('sikeu_akun_keuangan')->onDelete('set null');
            $table->foreignId('jurnal_id')->nullable()
                ->constrained('sikeu_jurnal_umum')->onDelete('set null');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('tanggal');
        });

        Schema::create('sikeu_saldo_awal_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saldo_awal_id')
                ->constrained('sikeu_saldo_awal')->onDelete('cascade');
            $table->foreignId('akun_id')
                ->constrained('sikeu_akun_keuangan')->onDelete('restrict');
            $table->decimal('debet', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['saldo_awal_id', 'akun_id'], 'sikeu_saldo_awal_detail_unik');
        });

        // Akun ekuitas penyeimbang saldo awal. 301.01 sudah terpakai untuk
        // "Ekuitas Dana Terikat Institusi", jadi gunakan kode baru 301.03.
        if (Schema::hasTable('sikeu_akun_keuangan')
            && DB::table('sikeu_akun_keuangan')->where('kode_akun', '301.03')->doesntExist()) {
            DB::table('sikeu_akun_keuangan')->insert([
                'kode_akun' => '301.03',
                'nama_akun' => 'Ekuitas Saldo Awal',
                'kelompok' => 'ekuitas',
                'saldo_normal' => 'kredit',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sikeu_saldo_awal_detail');
        Schema::dropIfExists('sikeu_saldo_awal');

        // Jangan hapus akun bila sudah dipakai jurnal agar data historis aman.
        if (Schema::hasTable('sikeu_akun_keuangan')) {
            $akunId = DB::table('sikeu_akun_keuangan')->where('kode_akun', '301.03')->value('id');
            if ($akunId && (!Schema::hasTable('sikeu_detail_jurnal_umum')
                || DB::table('sikeu_detail_jurnal_umum')->where('akun_id', $akunId)->doesntExist())) {
                DB::table('sikeu_akun_keuangan')->where('id', $akunId)->delete();
            }
        }
    }
};

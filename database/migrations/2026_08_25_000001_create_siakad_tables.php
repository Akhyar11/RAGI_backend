<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Fakultas
        Schema::create('siakad_fakultas', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama');
            $table->string('nama_singkat')->nullable();
            $table->foreignId('dekan_id')->nullable(); // Akan direferensikan ke pegawai_id nantinya
            $table->string('telepon')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Modifikasi tabel master_program_studi
        Schema::table('spmb_master_program_studi', function (Blueprint $table) {
            if (!Schema::hasColumn('master_program_studi', 'fakultas_id')) {
                $table->foreignId('fakultas_id')->nullable()->constrained('siakad_fakultas')->nullOnDelete();
            }
            if (!Schema::hasColumn('master_program_studi', 'kode_prodi_dikti')) {
                $table->string('kode_prodi_dikti', 50)->nullable()->after('kode_prodi');
            }
            if (!Schema::hasColumn('master_program_studi', 'akreditasi')) {
                $table->string('akreditasi', 10)->nullable();
                $table->date('akreditasi_berlaku_sampai')->nullable();
            }
        });

        // 2. Kurikulum
        Schema::create('siakad_kurikulum', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_studi_id')->constrained('spmb_master_program_studi')->cascadeOnDelete();
            $table->string('kode', 50)->unique();
            $table->string('nama');
            $table->integer('tahun_berlaku');
            $table->integer('total_sks_lulus')->default(144);
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // 3. Mata Kuliah
        Schema::create('siakad_mata_kuliah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kurikulum_id')->constrained('siakad_kurikulum')->cascadeOnDelete();
            $table->string('kode_mk', 50)->unique();
            $table->string('nama');
            $table->integer('sks_teori')->default(0);
            $table->integer('sks_praktik')->default(0);
            $table->integer('total_sks');
            $table->integer('semester_anjuran')->default(1);
            $table->enum('tipe', ['wajib', 'pilihan', 'wajib_prodi'])->default('wajib');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // 4. Prasyarat Mata Kuliah
        Schema::create('siakad_prasyarat_mk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mata_kuliah_id')->constrained('siakad_mata_kuliah')->cascadeOnDelete();
            $table->foreignId('prasyarat_id')->constrained('siakad_mata_kuliah')->cascadeOnDelete();
            $table->enum('tipe', ['lulus', 'pernah_ambil'])->default('lulus');
            $table->decimal('nilai_minimum', 5, 2)->nullable();
            $table->timestamps();
        });

        // 5. Konversi Mahasiswa Transfer
        Schema::create('siakad_konversi_transfer', function (Blueprint $table) {
            $table->id();
            $table->string('no_transaksi', 50)->unique();
            $table->string('kampus_asal');
            $table->string('prodi_asal');
            $table->foreignId('diproses_oleh')->nullable()->constrained('core_users')->nullOnDelete();
            $table->enum('status', ['draft', 'disetujui'])->default('draft');
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 6. Mahasiswa
        Schema::create('siakad_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('core_users')->nullOnDelete();
            $table->foreignId('program_studi_id')->constrained('spmb_master_program_studi');
            $table->foreignId('konversi_id')->nullable()->constrained('siakad_konversi_transfer')->nullOnDelete();
            $table->string('nim', 30)->unique();
            $table->string('nama_lengkap');
            $table->string('nik', 20)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable();
            $table->string('agama')->nullable();
            $table->text('alamat')->nullable();
            $table->string('telepon')->nullable();
            $table->integer('angkatan');
            $table->date('tanggal_masuk')->nullable();
            $table->enum('status', ['aktif', 'cuti', 'mangkir', 'dropout', 'lulus'])->default('aktif');
            // dosen_wali_id akan di alter setelah tabel dosen dibuat
            $table->foreignId('dosen_wali_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Add FK to konversi_transfer
        Schema::table('siakad_konversi_transfer', function (Blueprint $table) {
            $table->foreignId('mahasiswa_id')->nullable()->constrained('siakad_mahasiswa')->cascadeOnDelete();
        });

        // 7. Konversi Transfer Detail (Mata Kuliah)
        Schema::create('siakad_konversi_transfer_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('konversi_id')->constrained('siakad_konversi_transfer')->cascadeOnDelete();
            $table->foreignId('mata_kuliah_diakui_id')->constrained('siakad_mata_kuliah');
            $table->string('kode_mk_asal');
            $table->string('nama_mk_asal');
            $table->integer('sks_asal');
            $table->string('nilai_huruf_asal', 5);
            $table->timestamps();
        });

        // 8. Dosen
        Schema::create('siakad_dosen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('core_users')->nullOnDelete();
            $table->foreignId('pegawai_id')->nullable(); // FK ke simpeg_pegawai
            $table->string('nidn', 30)->nullable()->unique();
            $table->string('nip', 30)->nullable();
            $table->string('nama_lengkap');
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
            $table->foreignId('program_studi_id')->nullable()->constrained('spmb_master_program_studi');
            $table->string('jabatan_akademik')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // FK untuk dosen_wali
        Schema::table('siakad_mahasiswa', function (Blueprint $table) {
            $table->foreign('dosen_wali_id')->references('id')->on('siakad_dosen')->nullOnDelete();
        });

        // 9. Kelas Perkuliahan (Jadwal & Ruang)
        Schema::create('siakad_kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mata_kuliah_id')->constrained('siakad_mata_kuliah');
            $table->foreignId('tahun_akademik_id')->constrained('spmb_master_tahun_akademik');
            $table->foreignId('program_studi_id')->constrained('spmb_master_program_studi');
            $table->foreignId('ruangan_id')->nullable(); // FK ke sinapra
            $table->string('kode_kelas', 20);
            $table->string('nama_kelas');
            $table->integer('kapasitas')->default(40);
            $table->integer('kuota_krs')->default(40);
            $table->enum('hari', ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'])->nullable();
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->enum('status', ['draft', 'aktif', 'selesai'])->default('draft');
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['mata_kuliah_id', 'tahun_akademik_id', 'kode_kelas'], 'kelas_mk_ta_kode_unique');
        });

        // 10. Dosen Pengampu Kelas
        Schema::create('siakad_dosen_pengampu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('siakad_kelas')->cascadeOnDelete();
            $table->foreignId('dosen_id')->constrained('siakad_dosen')->cascadeOnDelete();
            $table->enum('peran', ['pengampu_utama', 'co_pengampu'])->default('pengampu_utama');
            $table->timestamps();
        });

        // 11. KRS (Kartu Rencana Studi)
        Schema::create('siakad_krs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained('siakad_mahasiswa')->cascadeOnDelete();
            $table->foreignId('tahun_akademik_id')->constrained('spmb_master_tahun_akademik');
            $table->integer('total_sks_diambil')->default(0);
            $table->enum('status', ['draft', 'diajukan', 'disetujui', 'dikunci', 'dibatalkan'])->default('draft');
            $table->foreignId('disetujui_oleh')->nullable()->constrained('siakad_dosen')->nullOnDelete();
            $table->timestamp('disetujui_at')->nullable();
            $table->boolean('locked_by_keuangan')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        // 12. KRS Detail
        Schema::create('siakad_krs_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('krs_id')->constrained('siakad_krs')->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('siakad_kelas');
            $table->enum('status', ['aktif', 'dibatalkan'])->default('aktif');
            $table->timestamps();
            
            $table->unique(['krs_id', 'kelas_id']);
        });

        // 13. Nilai Mahasiswa
        Schema::create('siakad_nilai_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('krs_detail_id')->constrained('siakad_krs_detail')->cascadeOnDelete();
            $table->decimal('nilai_harian', 5, 2)->default(0);
            $table->decimal('nilai_uts', 5, 2)->default(0);
            $table->decimal('nilai_uas', 5, 2)->default(0);
            $table->decimal('nilai_praktik', 5, 2)->default(0);
            $table->decimal('nilai_akhir', 5, 2)->default(0);
            $table->string('nilai_huruf', 2)->nullable();
            $table->decimal('bobot_mutu', 3, 2)->default(0);
            $table->boolean('is_final')->default(false);
            $table->foreignId('diinput_oleh')->nullable()->constrained('core_users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 14. KHS (Kartu Hasil Studi)
        Schema::create('siakad_khs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained('siakad_mahasiswa')->cascadeOnDelete();
            $table->foreignId('tahun_akademik_id')->constrained('spmb_master_tahun_akademik');
            $table->decimal('ips', 5, 2)->default(0);
            $table->integer('total_sks_semester')->default(0);
            $table->integer('sks_kumulatif')->default(0);
            $table->decimal('ipk', 5, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // 15. Status Akademik Log
        Schema::create('siakad_status_akademik_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained('siakad_mahasiswa')->cascadeOnDelete();
            $table->string('status_lama', 50);
            $table->string('status_baru', 50);
            $table->text('alasan')->nullable();
            $table->foreignId('diubah_oleh')->nullable()->constrained('core_users')->nullOnDelete();
            $table->timestamps();
        });

        // 16. Cuti Mahasiswa
        Schema::create('siakad_cuti_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained('siakad_mahasiswa')->cascadeOnDelete();
            $table->foreignId('tahun_akademik_id')->constrained('spmb_master_tahun_akademik');
            $table->text('alasan');
            $table->string('file_surat')->nullable();
            $table->enum('status', ['pending', 'disetujui', 'ditolak'])->default('pending');
            $table->foreignId('diproses_oleh')->nullable()->constrained('core_users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 17. Kelulusan
        Schema::create('siakad_kelulusan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained('siakad_mahasiswa')->cascadeOnDelete();
            $table->foreignId('tahun_akademik_id')->constrained('spmb_master_tahun_akademik');
            $table->date('tanggal_sidang')->nullable();
            $table->decimal('ipk_akhir', 5, 2);
            $table->integer('total_sks');
            $table->integer('masa_studi_semester');
            $table->enum('predikat', ['memuaskan', 'sangat_memuaskan', 'cum_laude', 'dengan_pujian'])->nullable();
            $table->string('nomor_ijazah', 100)->nullable()->unique();
            $table->date('tanggal_ijazah')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siakad_kelulusan');
        Schema::dropIfExists('siakad_cuti_mahasiswa');
        Schema::dropIfExists('siakad_status_akademik_log');
        Schema::dropIfExists('siakad_khs');
        Schema::dropIfExists('siakad_nilai_mahasiswa');
        Schema::dropIfExists('siakad_krs_detail');
        Schema::dropIfExists('siakad_krs');
        Schema::dropIfExists('siakad_dosen_pengampu');
        Schema::dropIfExists('siakad_kelas');
        Schema::table('siakad_mahasiswa', function (Blueprint $table) {
            $table->dropForeign(['dosen_wali_id']);
        });
        Schema::dropIfExists('siakad_dosen');
        Schema::dropIfExists('siakad_konversi_transfer_detail');
        Schema::dropIfExists('siakad_konversi_transfer');
        Schema::dropIfExists('siakad_mahasiswa');
        Schema::dropIfExists('siakad_prasyarat_mk');
        Schema::dropIfExists('siakad_mata_kuliah');
        Schema::dropIfExists('siakad_kurikulum');
        
        Schema::table('spmb_master_program_studi', function (Blueprint $table) {
            $table->dropForeign(['fakultas_id']);
            $table->dropColumn(['fakultas_id', 'kode_prodi_dikti', 'akreditasi', 'akreditasi_berlaku_sampai']);
        });
        
        Schema::dropIfExists('siakad_fakultas');
    }
};

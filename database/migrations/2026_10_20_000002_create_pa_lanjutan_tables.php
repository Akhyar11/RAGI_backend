<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 Bimbingan PA — tabel lanjutan:
 *  - siakad_pa_jadwal   : slot jadwal pertemuan/konsultasi PA yang dibuka dosen.
 *  - siakad_pa_booking  : pengajuan/janji konsultasi mahasiswa pada slot jadwal.
 *  - siakad_pa_pkl      : status PKL/PL/Magang mahasiswa bimbingan.
 *  - siakad_pa_insiden  : pelaporan kekerasan/pelecehan + rujukan konseling + eskalasi
 *                         (mendukung pelaporan anonim dan tindak lanjut Kaprodi/Kemahasiswaan).
 *
 * Sekaligus men-seed item master referensi SIAKAD (zero-hardcode untuk jenis/status).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Jadwal pertemuan / konsultasi PA
        if (!Schema::hasTable('siakad_pa_jadwal')) {
            Schema::create('siakad_pa_jadwal', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dosen_id')->constrained('siakad_dosen')->cascadeOnDelete();
                $table->foreignId('tahun_akademik_id')->nullable()->constrained('siakad_tahun_akademik')->nullOnDelete();
                $table->date('tanggal');
                $table->time('jam_mulai');
                $table->time('jam_selesai');
                $table->string('mode', 30)->default('tatap_muka'); // master ref: pa_jadwal_mode
                $table->string('lokasi', 150)->nullable();
                $table->unsignedInteger('kuota')->default(1);
                $table->text('catatan')->nullable();
                $table->string('status', 20)->default('dibuka'); // dibuka/ditutup
                $table->timestamps();

                $table->index(['dosen_id', 'tanggal']);
            });
        }

        // 2. Booking / janji konsultasi mahasiswa pada slot jadwal
        if (!Schema::hasTable('siakad_pa_booking')) {
            Schema::create('siakad_pa_booking', function (Blueprint $table) {
                $table->id();
                $table->foreignId('jadwal_id')->constrained('siakad_pa_jadwal')->cascadeOnDelete();
                $table->foreignId('mahasiswa_id')->constrained('siakad_mahasiswa')->cascadeOnDelete();
                $table->string('topik', 200)->nullable();
                $table->text('catatan_mahasiswa')->nullable();
                $table->string('status', 20)->default('diajukan'); // master ref: pa_booking_status
                $table->text('catatan_dosen')->nullable();
                $table->timestamp('diproses_pada')->nullable();
                $table->timestamps();

                $table->unique(['jadwal_id', 'mahasiswa_id']);
            });
        }

        // 3. Status PKL / PL / Magang mahasiswa bimbingan
        if (!Schema::hasTable('siakad_pa_pkl')) {
            Schema::create('siakad_pa_pkl', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mahasiswa_id')->constrained('siakad_mahasiswa')->cascadeOnDelete();
                $table->foreignId('tahun_akademik_id')->nullable()->constrained('siakad_tahun_akademik')->nullOnDelete();
                $table->foreignId('dosen_id')->nullable()->constrained('siakad_dosen')->nullOnDelete(); // PA yang memantau
                $table->string('jenis', 30)->default('pkl'); // master ref: pa_pkl_jenis (pkl/pl/magang/mbkm)
                $table->string('instansi', 200);
                $table->string('lokasi', 200)->nullable();
                $table->string('pembimbing_lapangan', 150)->nullable();
                $table->date('tanggal_mulai')->nullable();
                $table->date('tanggal_selesai')->nullable();
                $table->string('status', 20)->default('berjalan'); // master ref: pa_pkl_status
                $table->text('catatan')->nullable();
                $table->timestamps();

                $table->index(['mahasiswa_id', 'status']);
            });
        }

        // 4. Insiden: kekerasan/pelecehan + rujukan konseling + eskalasi
        if (!Schema::hasTable('siakad_pa_insiden')) {
            Schema::create('siakad_pa_insiden', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mahasiswa_id')->nullable()->constrained('siakad_mahasiswa')->nullOnDelete(); // boleh null bila terduga/tanpa identitas
                $table->foreignId('dosen_id')->nullable()->constrained('siakad_dosen')->nullOnDelete(); // PA pelapor (null bila anonim)
                $table->foreignId('pelapor_user_id')->nullable()->constrained('core_users')->nullOnDelete();
                $table->boolean('anonim')->default(false);
                $table->string('jenis', 40)->default('kekerasan'); // master ref: pa_insiden_jenis
                $table->string('tingkat', 20)->nullable(); // ringan/sedang/berat (master ref opsional)
                $table->text('deskripsi');
                $table->date('tanggal_kejadian')->nullable();
                $table->string('lokasi', 200)->nullable();
                $table->string('status', 30)->default('dilaporkan'); // master ref: pa_insiden_status
                $table->string('eskalasi_tujuan', 40)->nullable(); // master ref: pa_eskalasi_tujuan (kaprodi/kemahasiswaan/...)
                $table->timestamp('dieskalasi_pada')->nullable();
                $table->boolean('rujukan_konseling')->default(false);
                $table->string('konseling_ke', 150)->nullable(); // unit/konselor rujukan
                $table->text('tindak_lanjut')->nullable();
                $table->timestamps();

                $table->index(['status', 'jenis']);
                $table->index('eskalasi_tujuan');
            });
        }

        $this->seedReferensi();
    }

    private function seedReferensi(): void
    {
        $now = now();
        $tipe = 'siakad';

        $types = [
            ['kode' => 'pa_jadwal_mode', 'nama' => 'Mode Jadwal Bimbingan PA', 'deskripsi' => 'Moda pertemuan/konsultasi PA (tatap muka, daring, dll).', 'urutan' => 30],
            ['kode' => 'pa_jadwal_status', 'nama' => 'Status Slot Jadwal PA', 'deskripsi' => 'Status buka/tutup slot jadwal bimbingan PA.', 'urutan' => 39],
            ['kode' => 'pa_booking_status', 'nama' => 'Status Booking Konsultasi PA', 'deskripsi' => 'Status pengajuan janji konsultasi mahasiswa pada slot jadwal PA.', 'urutan' => 31],
            ['kode' => 'pa_pkl_jenis', 'nama' => 'Jenis PKL/PL/Magang', 'deskripsi' => 'Jenis kegiatan praktik lapangan mahasiswa.', 'urutan' => 32],
            ['kode' => 'pa_pkl_status', 'nama' => 'Status PKL/PL/Magang', 'deskripsi' => 'Tahapan pelaksanaan kegiatan praktik lapangan.', 'urutan' => 33],
            ['kode' => 'pa_insiden_jenis', 'nama' => 'Jenis Insiden PA', 'deskripsi' => 'Klasifikasi laporan insiden (kekerasan/pelecehan/dll).', 'urutan' => 34],
            ['kode' => 'pa_insiden_status', 'nama' => 'Status Penanganan Insiden', 'deskripsi' => 'Tahapan tindak lanjut laporan insiden PA.', 'urutan' => 35],
            ['kode' => 'pa_eskalasi_tujuan', 'nama' => 'Tujuan Eskalasi Insiden', 'deskripsi' => 'Unit tujuan eskalasi laporan insiden (Kaprodi, Kemahasiswaan, dll).', 'urutan' => 36],
            ['kode' => 'pa_catatan_kategori', 'nama' => 'Kategori Catatan Bimbingan PA', 'deskripsi' => 'Kategori catatan/aktivitas bimbingan PA.', 'urutan' => 37],
            ['kode' => 'pa_tindak_lanjut_status', 'nama' => 'Status Tindak Lanjut Catatan PA', 'deskripsi' => 'Status tindak lanjut catatan bimbingan PA.', 'urutan' => 38],
        ];

        if (Schema::hasTable('core_tipe_referensi')) {
            foreach ($types as $t) {
                DB::table('core_tipe_referensi')->updateOrInsert(
                    ['kode' => $t['kode']],
                    [
                        'nama' => $t['nama'],
                        'modul' => 'siakad',
                        'deskripsi' => $t['deskripsi'],
                        'urutan' => $t['urutan'],
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }

        // Item master referensi (modul=siakad), selaras kolom/enum database.
        $items = [
            'pa_jadwal_mode' => [
                ['kode' => 'tatap_muka', 'nama' => 'Tatap Muka'],
                ['kode' => 'daring', 'nama' => 'Daring (Online)'],
                ['kode' => 'hybrid', 'nama' => 'Hybrid'],
            ],
            'pa_jadwal_status' => [
                ['kode' => 'dibuka', 'nama' => 'Dibuka'],
                ['kode' => 'ditutup', 'nama' => 'Ditutup'],
            ],
            'pa_booking_status' => [
                ['kode' => 'diajukan', 'nama' => 'Diajukan'],
                ['kode' => 'disetujui', 'nama' => 'Disetujui'],
                ['kode' => 'ditolak', 'nama' => 'Ditolak'],
                ['kode' => 'selesai', 'nama' => 'Selesai'],
                ['kode' => 'batal', 'nama' => 'Batal'],
            ],
            'pa_pkl_jenis' => [
                ['kode' => 'pkl', 'nama' => 'Praktik Kerja Lapangan (PKL)'],
                ['kode' => 'pl', 'nama' => 'Praktik Lapangan (PL)'],
                ['kode' => 'magang', 'nama' => 'Magang'],
                ['kode' => 'mbkm', 'nama' => 'Magang MBKM'],
                ['kode' => 'kp', 'nama' => 'Kerja Praktik (KP)'],
            ],
            'pa_pkl_status' => [
                ['kode' => 'rencana', 'nama' => 'Rencana'],
                ['kode' => 'berjalan', 'nama' => 'Berjalan'],
                ['kode' => 'selesai', 'nama' => 'Selesai'],
                ['kode' => 'batal', 'nama' => 'Batal'],
            ],
            'pa_insiden_jenis' => [
                ['kode' => 'kekerasan', 'nama' => 'Kekerasan'],
                ['kode' => 'pelecehan_seksual', 'nama' => 'Pelecehan Seksual'],
                ['kode' => 'perundungan', 'nama' => 'Perundungan (Bullying)'],
                ['kode' => 'intimidasi', 'nama' => 'Intimidasi / Ancaman'],
                ['kode' => 'diskriminasi', 'nama' => 'Diskriminasi'],
                ['kode' => 'lainnya', 'nama' => 'Lainnya'],
            ],
            'pa_insiden_status' => [
                ['kode' => 'dilaporkan', 'nama' => 'Dilaporkan'],
                ['kode' => 'diverifikasi', 'nama' => 'Diverifikasi'],
                ['kode' => 'ditangani', 'nama' => 'Ditangani'],
                ['kode' => 'selesai', 'nama' => 'Selesai'],
                ['kode' => 'ditolak', 'nama' => 'Ditolak / Tidak Terbukti'],
            ],
            'pa_eskalasi_tujuan' => [
                ['kode' => 'kaprodi', 'nama' => 'Ketua Program Studi'],
                ['kode' => 'kemahasiswaan', 'nama' => 'Bagian Kemahasiswaan'],
                ['kode' => 'satgas_ppks', 'nama' => 'Satgas PPKS'],
                ['kode' => 'dekan', 'nama' => 'Dekan'],
                ['kode' => 'rektorat', 'nama' => 'Rektorat'],
            ],
            'pa_catatan_kategori' => [
                ['kode' => 'akademik', 'nama' => 'Akademik / Nilai'],
                ['kode' => 'krs', 'nama' => 'KRS'],
                ['kode' => 'khs', 'nama' => 'KHS'],
                ['kode' => 'keuangan', 'nama' => 'Keuangan'],
                ['kode' => 'pribadi', 'nama' => 'Pribadi'],
                ['kode' => 'lainnya', 'nama' => 'Lainnya'],
            ],
            'pa_tindak_lanjut_status' => [
                ['kode' => 'dipantau', 'nama' => 'Dipantau'],
                ['kode' => 'diproses', 'nama' => 'Diproses'],
                ['kode' => 'selesai', 'nama' => 'Selesai'],
            ],
        ];

        if (Schema::hasTable('spmb_master_referensi')) {
            foreach ($items as $t => $rows) {
                foreach (array_values($rows) as $i => $row) {
                    DB::table('spmb_master_referensi')->updateOrInsert(
                        ['modul' => $tipe, 'tipe' => $t, 'kode' => $row['kode']],
                        [
                            'nama' => $row['nama'],
                            'urutan' => $i + 1,
                            'is_active' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('siakad_pa_insiden');
        Schema::dropIfExists('siakad_pa_pkl');
        Schema::dropIfExists('siakad_pa_booking');
        Schema::dropIfExists('siakad_pa_jadwal');

        $tipes = [
            'pa_jadwal_mode', 'pa_jadwal_status', 'pa_booking_status', 'pa_pkl_jenis', 'pa_pkl_status',
            'pa_insiden_jenis', 'pa_insiden_status', 'pa_eskalasi_tujuan',
            'pa_catatan_kategori', 'pa_tindak_lanjut_status',
        ];

        if (Schema::hasTable('spmb_master_referensi')) {
            DB::table('spmb_master_referensi')
                ->where('modul', 'siakad')
                ->whereIn('tipe', $tipes)
                ->delete();
        }

        if (Schema::hasTable('core_tipe_referensi')) {
            DB::table('core_tipe_referensi')->whereIn('kode', $tipes)->delete();
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        $types = [
            [
                'kode'        => 'tipe_konten_lms',
                'nama'        => 'Tipe Konten LMS',
                'modul'       => 'siakad',
                'deskripsi'   => 'Format penyajian materi pembelajaran LMS (Teks, File, Link, Video).',
                'urutan'      => 24,
            ],
            [
                'kode'        => 'tipe_izin',
                'nama'        => 'Tipe Izin Absensi',
                'modul'       => 'siakad',
                'deskripsi'   => 'Klasifikasi permohonan dispensasi presensi mahasiswa (Sakit atau Izin).',
                'urutan'      => 25,
            ],
            [
                'kode'        => 'status_persetujuan_izin',
                'nama'        => 'Status Persetujuan Izin',
                'modul'       => 'siakad',
                'deskripsi'   => 'Status pemrosesan persetujuan izin absensi oleh dosen.',
                'urutan'      => 26,
            ],
        ];

        if (Schema::hasTable('core_tipe_referensi')) {
            foreach ($types as $t) {
                DB::table('core_tipe_referensi')->updateOrInsert(
                    ['kode' => $t['kode']],
                    array_merge($t, ['is_active' => true, 'created_at' => $now, 'updated_at' => $now])
                );
            }
        }

        $items = [
            'tipe_konten_lms' => [
                ['kode' => 'teks', 'nama' => 'Artikel Teks / Ringkasan'],
                ['kode' => 'file', 'nama' => 'Dokumen / Slide Berkas'],
                ['kode' => 'link_eksternal', 'nama' => 'Tautan Eksternal / Video Conf'],
                ['kode' => 'video_embed', 'nama' => 'Video Pembelajaran Tersemat'],
            ],
            'tipe_izin' => [
                ['kode' => 'sakit', 'nama' => 'Sakit (Surat Dokter)'],
                ['kode' => 'izin', 'nama' => 'Izin Dispensasi / Mendesak'],
            ],
            'status_persetujuan_izin' => [
                ['kode' => 'disetujui', 'nama' => 'Disetujui'],
                ['kode' => 'ditolak', 'nama' => 'Ditolak'],
            ],
        ];

        if (Schema::hasTable('spmb_master_referensi')) {
            foreach ($items as $tipe => $rows) {
                foreach (array_values($rows) as $i => $row) {
                    DB::table('spmb_master_referensi')->updateOrInsert(
                        ['modul' => 'siakad', 'tipe' => $tipe, 'kode' => $row['kode']],
                        [
                            'nama'       => $row['nama'],
                            'urutan'     => $i + 1,
                            'is_active'  => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('spmb_master_referensi')) {
            DB::table('spmb_master_referensi')
                ->where('modul', 'siakad')
                ->whereIn('tipe', ['tipe_konten_lms', 'tipe_izin', 'status_persetujuan_izin'])
                ->delete();
        }

        if (Schema::hasTable('core_tipe_referensi')) {
            DB::table('core_tipe_referensi')
                ->whereIn('kode', ['tipe_konten_lms', 'tipe_izin', 'status_persetujuan_izin'])
                ->delete();
        }
    }
};

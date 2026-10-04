<?php

use App\Models\Arsip\KlasifikasiSurat;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 9 Klasifikasi Resmi Politeknik Indonusa Surakarta (DI s/d DIX)
        $officialKlasifikasi = [
            [
                'kode' => 'DI',
                'nama' => 'SK',
                'kategori' => 'klasifikasi',
                'keterangan' => 'Surat Keputusan',
                'is_active' => true,
            ],
            [
                'kode' => 'DII',
                'nama' => 'ST/SPPD',
                'kategori' => 'klasifikasi',
                'keterangan' => 'Surat Tugas/Surat Perjalanan Dinas',
                'is_active' => true,
            ],
            [
                'kode' => 'DIII',
                'nama' => 'Permohonan',
                'kategori' => 'klasifikasi',
                'keterangan' => 'Surat Permohonan',
                'is_active' => true,
            ],
            [
                'kode' => 'DIV',
                'nama' => 'Pemberitahuan',
                'kategori' => 'klasifikasi',
                'keterangan' => 'Surat Pemberitahuan',
                'is_active' => true,
            ],
            [
                'kode' => 'DV',
                'nama' => 'Pengantar',
                'kategori' => 'klasifikasi',
                'keterangan' => 'Surat Pengantar',
                'is_active' => true,
            ],
            [
                'kode' => 'DVI',
                'nama' => 'MOU/Perjanjian',
                'kategori' => 'klasifikasi',
                'keterangan' => 'Surat MOU/Perjanjian',
                'is_active' => true,
            ],
            [
                'kode' => 'DVII',
                'nama' => 'Undangan',
                'kategori' => 'klasifikasi',
                'keterangan' => 'Undangan',
                'is_active' => true,
            ],
            [
                'kode' => 'DVIII',
                'nama' => 'Surat Keterangan',
                'kategori' => 'klasifikasi',
                'keterangan' => 'Surat Keterangan',
                'is_active' => true,
            ],
            [
                'kode' => 'DIX',
                'nama' => 'Berita Acara',
                'kategori' => 'klasifikasi',
                'keterangan' => 'Berita Acara',
                'is_active' => true,
            ],
        ];

        // 1. Bersihkan klasifikasi scaffolding terdahulu yang tidak sesuai
        KlasifikasiSurat::where('kategori', 'klasifikasi')
            ->whereNotIn('kode', array_column($officialKlasifikasi, 'kode'))
            ->forceDelete();

        // 2. Bersihkan kode DIII dan DIV yang sebelumnya salah dikelompokkan ke kategori jenjang
        KlasifikasiSurat::where('kategori', 'jenjang')
            ->forceDelete();

        // 3. Masukkan / perbarui 9 klasifikasi surat resmi
        foreach ($officialKlasifikasi as $item) {
            KlasifikasiSurat::updateOrCreate(
                ['kode' => $item['kode']],
                [
                    'nama' => $item['nama'],
                    'kategori' => $item['kategori'],
                    'keterangan' => $item['keterangan'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        KlasifikasiSurat::whereIn('kode', ['DI', 'DII', 'DIII', 'DIV', 'DV', 'DVI', 'DVII', 'DVIII', 'DIX'])->delete();
    }
};

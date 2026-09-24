<?php

namespace Database\Seeders;

use App\Models\Sinapra\MasterTipeRuangan;
use App\Models\Sinapra\MasterSatuan;
use App\Models\Ruangan;
use Illuminate\Database\Seeder;

class SinapraMasterSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Master Tipe Ruangan
        $tipeRuanganData = [
            ['kode' => 'kelas', 'nama' => 'Ruang Kelas Teori', 'deskripsi' => 'Ruangan perkuliahan tatap muka reguler', 'urutan' => 1],
            ['kode' => 'lab_komputer', 'nama' => 'Laboratorium Komputer', 'deskripsi' => 'Lab praktikum komputasi, software, dan jaringan', 'urutan' => 2],
            ['kode' => 'lab_sains', 'nama' => 'Laboratorium Sains & Teknik', 'deskripsi' => 'Lab praktikum fisika, kimia, mekatronika, dan presisi', 'urutan' => 3],
            ['kode' => 'kantor', 'nama' => 'Ruang Kantor / Dosen', 'deskripsi' => 'Ruang kerja staf administrasi, kaprodi, dan dosen', 'urutan' => 4],
            ['kode' => 'aula', 'nama' => 'Aula / Auditorium', 'deskripsi' => 'Ruangan serbaguna untuk seminar, wisuda, dan pertemuan besar', 'urutan' => 5],
            ['kode' => 'gudang', 'nama' => 'Gudang Sarpras', 'deskripsi' => 'Tempat penyimpanan inventaris logistik dan sparepart', 'urutan' => 6],
            ['kode' => 'workshop', 'nama' => 'Studio & Workshop', 'deskripsi' => 'Ruang studio multimedia, arsitektur, dan bengkel teknik', 'urutan' => 7],
            ['kode' => 'perpustakaan', 'nama' => 'Perpustakaan & Ruang Baca', 'deskripsi' => 'Area literasi, ruang referensi, dan belajar mandiri', 'urutan' => 8],
            ['kode' => 'lainnya', 'nama' => 'Fasilitas Kampus Lainnya', 'deskripsi' => 'Fasilitas umum pendukung operasional kampus', 'urutan' => 9],
        ];

        foreach ($tipeRuanganData as $data) {
            MasterTipeRuangan::firstOrCreate(
                ['kode' => $data['kode']],
                [
                    'nama' => $data['nama'],
                    'deskripsi' => $data['deskripsi'],
                    'is_active' => true,
                    'urutan' => $data['urutan'],
                ]
            );
        }

        // 2. Hubungkan data eksisting ruangan ke tipe_ruangan_id
        $ruangans = Ruangan::whereNull('tipe_ruangan_id')->get();
        foreach ($ruangans as $r) {
            $matchingKode = match (strtolower($r->tipe ?? '')) {
                'lab', 'laboratorium' => 'lab_komputer',
                'aula' => 'aula',
                'kantor' => 'kantor',
                'gudang' => 'gudang',
                'toilet' => 'lainnya',
                default => 'kelas',
            };
            $tipe = MasterTipeRuangan::where('kode', $matchingKode)->first();
            if ($tipe) {
                $r->update(['tipe_ruangan_id' => $tipe->id]);
            }
        }

        // 3. Master Satuan Barang
        $satuanData = [
            ['kode' => 'UNIT', 'nama' => 'Unit', 'keterangan' => 'Satuan untuk peralatan elektronik, mesin, furnitur', 'urutan' => 1],
            ['kode' => 'PCS', 'nama' => 'Pcs (Pieces)', 'keterangan' => 'Satuan hitung butir / komponen individual', 'urutan' => 2],
            ['kode' => 'BUAH', 'nama' => 'Buah', 'keterangan' => 'Satuan barang fisik umum', 'urutan' => 3],
            ['kode' => 'BOX', 'nama' => 'Box / Kotak', 'keterangan' => 'Satuan kemasan kotak', 'urutan' => 4],
            ['kode' => 'RIM', 'nama' => 'Rim', 'keterangan' => 'Satuan kertas (500 lembar)', 'urutan' => 5],
            ['kode' => 'BOTOL', 'nama' => 'Botol', 'keterangan' => 'Satuan zat cair atau reagen kimia dalam botol', 'urutan' => 6],
            ['kode' => 'ROLL', 'nama' => 'Roll / Gulung', 'keterangan' => 'Satuan kabel, pita, kain, atau selang', 'urutan' => 7],
            ['kode' => 'SET', 'nama' => 'Set', 'keterangan' => 'Satuan perlengkapan atau instrumen lengkap', 'urutan' => 8],
            ['kode' => 'PAKET', 'nama' => 'Paket', 'keterangan' => 'Satuan bundling paket barang atau lisensi', 'urutan' => 9],
            ['kode' => 'METER', 'nama' => 'Meter', 'keterangan' => 'Satuan panjang', 'urutan' => 10],
            ['kode' => 'LITER', 'nama' => 'Liter', 'keterangan' => 'Satuan volume zat cair', 'urutan' => 11],
        ];

        foreach ($satuanData as $data) {
            MasterSatuan::firstOrCreate(
                ['kode' => $data['kode']],
                [
                    'nama' => $data['nama'],
                    'keterangan' => $data['keterangan'],
                    'is_active' => true,
                    'urutan' => $data['urutan'],
                ]
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Sinapra\MasterKategoriBhp;
use App\Models\Sinapra\MasterSatuan;
use App\Models\Sinapra\MasterVendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SinapraVendorKategoriBhpSeeder extends Seeder
{
    public function run(): void
    {
        // 0. Seed jenis_rekanan master referensi
        if (DB::getSchemaBuilder()->hasTable('core_tipe_referensi')) {
            DB::table('core_tipe_referensi')->updateOrInsert(
                ['kode' => 'jenis_rekanan'],
                [
                    'nama' => 'Jenis Rekanan / Vendor',
                    'modul' => 'sinapra',
                    'deskripsi' => 'Klasifikasi jenis rekanan atau vendor di modul sarana & prasarana.',
                    'urutan' => 25,
                    'is_active' => true,
                    'updated_at' => now(),
                ]
            );
        }

        if (DB::getSchemaBuilder()->hasTable('spmb_master_referensi')) {
            $jenisRekananList = [
                ['kode' => 'penyedia_barang', 'nama' => 'Penyedia Barang', 'urutan' => 1],
                ['kode' => 'laboratorium_kalibrasi', 'nama' => 'Laboratorium Kalibrasi', 'urutan' => 2],
                ['kode' => 'jasa_maintenance', 'nama' => 'Jasa Maintenance', 'urutan' => 3],
                ['kode' => 'kontraktor', 'nama' => 'Kontraktor', 'urutan' => 4],
                ['kode' => 'umum', 'nama' => 'Umum', 'urutan' => 5],
            ];

            foreach ($jenisRekananList as $item) {
                DB::table('spmb_master_referensi')->updateOrInsert(
                    ['tipe' => 'jenis_rekanan', 'kode' => $item['kode']],
                    [
                        'modul' => 'sinapra',
                        'nama' => $item['nama'],
                        'urutan' => $item['urutan'],
                        'is_active' => true,
                        'updated_at' => now(),
                    ]
                );
            }
        }

        $vendors = [
            [
                'kode' => 'VND-LAB-01',
                'nama' => 'PT Precision Kalibrasi Indonesia',
                'jenis_rekanan' => 'laboratorium_kalibrasi',
                'alamat' => 'Kawasan Industri Pulogadung No. 18, Jakarta Timur',
                'telepon' => '021-4601234',
                'email' => 'kalibrasi@precision-indo.co.id',
                'pic_nama' => 'Hendra Setiawan',
                'pic_kontak' => '081234567801',
                'nomor_npwp' => '01.234.567.8-001.000',
                'is_active' => true,
                'urutan' => 1,
            ],
            [
                'kode' => 'VND-LAB-02',
                'nama' => 'Balai Pengujian Mutu & Sertifikasi Standar',
                'jenis_rekanan' => 'laboratorium_kalibrasi',
                'alamat' => 'Jl. Cisitu No. 21, Coblong, Kota Bandung',
                'telepon' => '022-2504567',
                'email' => 'layanan@balaimutu-kalibrasi.or.id',
                'pic_nama' => 'Dr. Ir. Rahmat Hidayat',
                'pic_kontak' => '081234567802',
                'nomor_npwp' => '01.345.678.9-002.000',
                'is_active' => true,
                'urutan' => 2,
            ],
            [
                'kode' => 'VND-IT-01',
                'nama' => 'PT Trikomindo Cipta Solusi IT',
                'jenis_rekanan' => 'penyedia_barang',
                'alamat' => 'Ruko Mangga Dua Mall Blok C No. 5, Jakarta Pusat',
                'telepon' => '021-6230987',
                'email' => 'sales@trikomindo.co.id',
                'pic_nama' => 'Budi Santoso',
                'pic_kontak' => '081234567803',
                'nomor_npwp' => '01.456.789.0-003.000',
                'is_active' => true,
                'urutan' => 3,
            ],
            [
                'kode' => 'VND-BHP-01',
                'nama' => 'PT Smart Reagent & Glassware Chem',
                'jenis_rekanan' => 'penyedia_barang',
                'alamat' => 'Jl. Boulevard Raya Blok PA 19 No. 12, Kelapa Gading',
                'telepon' => '021-4587654',
                'email' => 'order@smartreagent.com',
                'pic_nama' => 'Siti Nurhaliza, S.Si',
                'pic_kontak' => '081234567804',
                'nomor_npwp' => '01.567.890.1-004.000',
                'is_active' => true,
                'urutan' => 4,
            ],
            [
                'kode' => 'VND-SVC-01',
                'nama' => 'CV Teknik Prima AC & Mekanikal',
                'jenis_rekanan' => 'jasa_maintenance',
                'alamat' => 'Jl. Kaliurang KM 8 No. 45, Sleman, D.I. Yogyakarta',
                'telepon' => '0274-889900',
                'email' => 'cs@teknikprima.com',
                'pic_nama' => 'Agus Priyono',
                'pic_kontak' => '081234567805',
                'nomor_npwp' => '02.678.901.2-005.000',
                'is_active' => true,
                'urutan' => 5,
            ],
            [
                'kode' => 'VND-MEB-01',
                'nama' => 'CV Sarana Mebel Abadi Kampus',
                'jenis_rekanan' => 'penyedia_barang',
                'alamat' => 'Jl. Raya Jepara-Kudus KM 12, Jawa Tengah',
                'telepon' => '0291-591234',
                'email' => 'kontrak@mebelabadikampus.com',
                'pic_nama' => 'Miftahul Huda',
                'pic_kontak' => '081234567806',
                'nomor_npwp' => '02.789.012.3-006.000',
                'is_active' => true,
                'urutan' => 6,
            ],
            [
                'kode' => 'VND-KTR-01',
                'nama' => 'PT Konstruksi Jaya Bangun Persada',
                'jenis_rekanan' => 'kontraktor',
                'alamat' => 'Gedung Wisma Perkasa Lt. 5, Jl. MT Haryono Kav. 33',
                'telepon' => '021-7981234',
                'email' => 'tender@jayabangunkontraktor.co.id',
                'pic_nama' => 'Ir. Wahyu Prabowo',
                'pic_kontak' => '081234567807',
                'nomor_npwp' => '01.890.123.4-007.000',
                'is_active' => true,
                'urutan' => 7,
            ],
        ];

        foreach ($vendors as $v) {
            MasterVendor::updateOrCreate(['kode' => $v['kode']], $v);
        }

        $kategoriBhp = [
            [
                'kode' => 'ELEKTRONIK',
                'nama' => 'Komponen Elektronik & Robotika',
                'deskripsi' => 'Sensor, mikrokontroler Arduino/ESP32, resistor, IC, dan modul rangkaian lab IoT/Sistem Komputer',
                'is_active' => true,
                'urutan' => 1,
            ],
            [
                'kode' => 'REAGEN',
                'nama' => 'Reagen & Bahan Kimia Analitik',
                'deskripsi' => 'Larutan standar, asam/basa pekat, indikator titrasi, dan pelarut organik laboratorium kimia',
                'is_active' => true,
                'urutan' => 2,
            ],
            [
                'kode' => 'GLASSWARE',
                'nama' => 'Glassware & Peralatan Kaca Lab',
                'deskripsi' => 'Tabung reaksi, buret, erlenmeyer, pipet ukur, beaker glass, dan desikator praktikum',
                'is_active' => true,
                'urutan' => 3,
            ],
            [
                'kode' => 'BIOLOGI',
                'nama' => 'Preparat & Bahan Biologi Medis',
                'deskripsi' => 'Kaca preparat, pewarna gram, media kultur agar, dan bahan uji biokimia/mikrobiologi',
                'is_active' => true,
                'urutan' => 4,
            ],
            [
                'kode' => 'KOMPUTASI',
                'nama' => 'Aksesoris & Kabel Jaringan Lab',
                'deskripsi' => 'Kabel UTP Cat6, konektor RJ45, patch cord, thermal paste processor, dan kabel adapter display',
                'is_active' => true,
                'urutan' => 5,
            ],
            [
                'kode' => 'SAFETY',
                'nama' => 'Alat Pelindung Diri (APD) Lab',
                'deskripsi' => 'Sarung tangan nitril/lateks, masker medis, jas lab sekali pakai, dan kacamata safety goggle',
                'is_active' => true,
                'urutan' => 6,
            ],
            [
                'kode' => 'ATK_LAB',
                'nama' => 'ATK & Logbook Laboratorium',
                'deskripsi' => 'Kertas saring Whatman, spidol penanda kaca cryo, label barcode stiker, dan logbook asistensi lab',
                'is_active' => true,
                'urutan' => 7,
            ],
            [
                'kode' => 'UMUM',
                'nama' => 'Bahan Habis Pakai Umum',
                'deskripsi' => 'Bahan praktikum dan perlengkapan umum laboratorium kampus',
                'is_active' => true,
                'urutan' => 8,
            ],
        ];

        foreach ($kategoriBhp as $k) {
            MasterKategoriBhp::updateOrCreate(['kode' => $k['kode']], $k);
        }

        // Migrasi data relasi eksisting ke master
        $defaultVendorKalibrasi = MasterVendor::where('jenis_rekanan', 'laboratorium_kalibrasi')->first();
        if ($defaultVendorKalibrasi) {
            DB::table('sinapra_alat_kalibrasi')
                ->whereNull('vendor_id')
                ->update(['vendor_id' => $defaultVendorKalibrasi->id]);
        }

        $defaultKategoriBhp = MasterKategoriBhp::where('kode', 'UMUM')->first();
        $defaultSatuanPcs = MasterSatuan::where('kode', 'PCS')->first();
        if ($defaultKategoriBhp && $defaultSatuanPcs) {
            DB::table('sinapra_lab_bhp')
                ->whereNull('kategori_bhp_id')
                ->update([
                    'kategori_bhp_id' => $defaultKategoriBhp->id,
                    'satuan_id' => $defaultSatuanPcs->id,
                ]);
        }
    }
}

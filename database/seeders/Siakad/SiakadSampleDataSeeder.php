<?php

namespace Database\Seeders\Siakad;

use Illuminate\Database\Seeder;
use App\Models\Siakad\Fakultas;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\PrasyaratMk;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\Krs;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\NilaiMahasiswa;
use App\Models\Siakad\Khs;
use App\Models\Siakad\KonversiTransfer;
use App\Models\Siakad\KonversiTransferDetail;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\Spmb\MasterTahunAkademik;
use App\Models\Ruangan;
use App\Models\User;
use App\Models\Simpeg\Pegawai;

class SiakadSampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Tahun Akademik Multi-Periode
        $periods = [
            ['kode' => '20231', 'nama' => '2023/2024 Ganjil', 'tahun_mulai' => 2023, 'tahun_selesai' => 2024, 'is_active' => false],
            ['kode' => '20232', 'nama' => '2023/2024 Genap', 'tahun_mulai' => 2023, 'tahun_selesai' => 2024, 'is_active' => false],
            ['kode' => '20241', 'nama' => '2024/2025 Ganjil', 'tahun_mulai' => 2024, 'tahun_selesai' => 2025, 'is_active' => false],
            ['kode' => '20242', 'nama' => '2024/2025 Genap', 'tahun_mulai' => 2024, 'tahun_selesai' => 2025, 'is_active' => false],
            ['kode' => '20251', 'nama' => '2025/2026 Ganjil', 'tahun_mulai' => 2025, 'tahun_selesai' => 2026, 'is_active' => false],
            ['kode' => '20252', 'nama' => '2025/2026 Genap', 'tahun_mulai' => 2025, 'tahun_selesai' => 2026, 'is_active' => false],
            ['kode' => '20261', 'nama' => '2026/2027 Ganjil (Aktif)', 'tahun_mulai' => 2026, 'tahun_selesai' => 2027, 'is_active' => true],
        ];

        $taModels = [];
        foreach ($periods as $p) {
            $taModels[$p['kode']] = MasterTahunAkademik::updateOrCreate(
                ['kode' => $p['kode']],
                [
                    'nama' => $p['nama'],
                    'tahun_mulai' => $p['tahun_mulai'],
                    'tahun_selesai' => $p['tahun_selesai'],
                    'is_active' => $p['is_active'],
                ]
            );
        }

        // 2. Fakultas
        $fti = Fakultas::updateOrCreate(
            ['kode' => 'FTI'],
            [
                'nama' => 'Fakultas Teknologi Informasi & Sains Data',
                'nama_singkat' => 'FTI',
                'telepon' => '021-5551234',
                'email' => 'fti@kampus.ac.id',
                'is_active' => true,
            ]
        );

        $feb = Fakultas::updateOrCreate(
            ['kode' => 'FEB'],
            [
                'nama' => 'Fakultas Ekonomi & Bisnis Digital',
                'nama_singkat' => 'FEB',
                'telepon' => '021-5555678',
                'email' => 'feb@kampus.ac.id',
                'is_active' => true,
            ]
        );

        // Update Prodi to point to Fakultas
        MasterProgramStudi::where('kode_prodi', 'like', 'IF%')->orWhere('nama', 'like', '%Informatika%')->update(['fakultas_id' => $fti->id, 'kode_prodi_dikti' => '55201', 'akreditasi' => 'Unggul']);
        MasterProgramStudi::where('kode_prodi', 'like', 'SI%')->orWhere('nama', 'like', '%Sistem Informasi%')->update(['fakultas_id' => $fti->id, 'kode_prodi_dikti' => '57201', 'akreditasi' => 'Baik Sekali']);
        MasterProgramStudi::where('kode_prodi', 'like', 'MN%')->orWhere('nama', 'like', '%Manajemen%')->update(['fakultas_id' => $feb->id, 'kode_prodi_dikti' => '61201', 'akreditasi' => 'Unggul']);
        MasterProgramStudi::where('kode_prodi', 'like', 'AK%')->orWhere('nama', 'like', '%Akuntansi%')->update(['fakultas_id' => $feb->id, 'kode_prodi_dikti' => '62201', 'akreditasi' => 'Unggul']);

        $prodiIF = MasterProgramStudi::where('fakultas_id', $fti->id)->first() ?? MasterProgramStudi::first();

        // 3. Kurikulum
        $kurikulumIF = Kurikulum::updateOrCreate(
            ['kode' => 'KUR-2024-IF'],
            [
                'program_studi_id' => $prodiIF->id,
                'nama' => 'Kurikulum OBE Informatika 2024',
                'tahun_berlaku' => 2024,
                'total_sks_lulus' => 144,
                'deskripsi' => 'Kurikulum berbasis Outcome-Based Education dengan peminatan AI & Rekayasa Perangkat Lunak.',
                'is_active' => true,
            ]
        );

        // 4. Mata Kuliah Lengkap
        $mks = [
            ['kode_mk' => 'IF101', 'nama' => 'Algoritma & Pemrograman I', 'sks_t' => 2, 'sks_p' => 1, 'sem' => 1, 'tipe' => 'wajib'],
            ['kode_mk' => 'IF102', 'nama' => 'Matematika Diskrit', 'sks_t' => 3, 'sks_p' => 0, 'sem' => 1, 'tipe' => 'wajib'],
            ['kode_mk' => 'MKU101', 'nama' => 'Pendidikan Pancasila & Kewarganegaraan', 'sks_t' => 2, 'sks_p' => 0, 'sem' => 1, 'tipe' => 'wajib'],
            ['kode_mk' => 'IF201', 'nama' => 'Struktur Data & Algoritma II', 'sks_t' => 2, 'sks_p' => 1, 'sem' => 2, 'tipe' => 'wajib'],
            ['kode_mk' => 'IF202', 'nama' => 'Basis Data Relasional', 'sks_t' => 2, 'sks_p' => 1, 'sem' => 2, 'tipe' => 'wajib'],
            ['kode_mk' => 'IF203', 'nama' => 'Arsitektur Komputer & Organisasi', 'sks_t' => 3, 'sks_p' => 0, 'sem' => 2, 'tipe' => 'wajib'],
            ['kode_mk' => 'IF301', 'nama' => 'Pemrograman Web & Mobile', 'sks_t' => 2, 'sks_p' => 2, 'sem' => 3, 'tipe' => 'wajib'],
            ['kode_mk' => 'IF302', 'nama' => 'Rekayasa Perangkat Lunak', 'sks_t' => 3, 'sks_p' => 0, 'sem' => 3, 'tipe' => 'wajib'],
            ['kode_mk' => 'IF303', 'nama' => 'Sistem Operasi Lanjut', 'sks_t' => 2, 'sks_p' => 1, 'sem' => 3, 'tipe' => 'wajib'],
            ['kode_mk' => 'IF401', 'nama' => 'Kecerdasan Buatan & Machine Learning', 'sks_t' => 2, 'sks_p' => 1, 'sem' => 4, 'tipe' => 'wajib_prodi'],
            ['kode_mk' => 'IF402', 'nama' => 'Jaringan Komputer & Cyber Security', 'sks_t' => 2, 'sks_p' => 1, 'sem' => 4, 'tipe' => 'wajib'],
            ['kode_mk' => 'IF403', 'nama' => 'Interaksi Manusia & Komputer (UI/UX)', 'sks_t' => 3, 'sks_p' => 0, 'sem' => 4, 'tipe' => 'wajib'],
            ['kode_mk' => 'IF501', 'nama' => 'Cloud Computing & DevOps Architecture', 'sks_t' => 2, 'sks_p' => 1, 'sem' => 5, 'tipe' => 'pilihan'],
        ];

        $mkModels = [];
        foreach ($mks as $mk) {
            $total = $mk['sks_t'] + $mk['sks_p'];
            $mkModels[$mk['kode_mk']] = MataKuliah::updateOrCreate(
                ['kode_mk' => $mk['kode_mk']],
                [
                    'kurikulum_id' => $kurikulumIF->id,
                    'nama' => $mk['nama'],
                    'sks_teori' => $mk['sks_t'],
                    'sks_praktik' => $mk['sks_p'],
                    'total_sks' => $total,
                    'semester_anjuran' => $mk['sem'],
                    'tipe' => $mk['tipe'],
                    'is_active' => true,
                ]
            );
        }

        // 5. Dosen
        $userDosen = User::where('email', 'dosen@kampus.ac.id')->first();
        $dosen1 = Dosen::updateOrCreate(
            ['nidn' => '0412058001'],
            [
                'user_id' => $userDosen?->id,
                'nama_lengkap' => 'Dr. Ir. Ahmad Santoso, M.Kom',
                'nip' => '198005122005011002',
                'program_studi_id' => $prodiIF->id,
                'jabatan_akademik' => 'Lektor',
                'is_active' => true,
            ]
        );

        $dosen2 = Dosen::updateOrCreate(
            ['nidn' => '0415088502'],
            [
                'nama_lengkap' => 'Budi Raharjo, S.T., M.T.',
                'nip' => '198508152010121001',
                'program_studi_id' => $prodiIF->id,
                'jabatan_akademik' => 'Lektor',
                'is_active' => true,
            ]
        );

        // 6. Mahasiswa Reguler (Ahmad Fadillah)
        $userMhs = User::where('email', 'mahasiswa@kampus.ac.id')->first();
        $mhs1 = Mahasiswa::updateOrCreate(
            ['nim' => '2301001001'],
            [
                'user_id' => $userMhs?->id,
                'program_studi_id' => $prodiIF->id,
                'nama_lengkap' => 'Ahmad Fadillah',
                'nik' => '3201123456780001',
                'tanggal_lahir' => '2004-05-15',
                'tempat_lahir' => 'Jakarta',
                'jenis_kelamin' => 'L',
                'agama' => 'Islam',
                'alamat' => 'Jl. Kampus Merdeka No. 10',
                'telepon' => '081234567890',
                'angkatan' => 2023,
                'tanggal_masuk' => '2023-09-01',
                'status' => 'aktif',
                'dosen_wali_id' => $dosen1->id,
            ]
        );

        // 7. Ruangan SINAPRA
        $ruang1 = Ruangan::first() ?? Ruangan::create([
            'gedung_id' => 1,
            'kode' => 'R-AULA-01',
            'nama' => 'Aula Utama Nusantara',
            'lantai' => 1,
            'tipe' => 'aula',
            'kapasitas' => 500,
            'ada_ac' => true,
            'ada_proyektor' => true,
            'ada_wifi' => true,
            'status' => 'aktif',
        ]);

        // 8. Seeding Nilai Historis Per Semester (Semester 1 s.d 4)
        $historicalSemesters = [
            '20241' => [
                ['mk' => 'IF101', 'h' => 90, 'uts' => 88, 'uas' => 92, 'p' => 88, 'huruf' => 'A', 'mutu' => 4.00],
                ['mk' => 'IF102', 'h' => 85, 'uts' => 90, 'uas' => 86, 'p' => 85, 'huruf' => 'A', 'mutu' => 4.00],
                ['mk' => 'MKU101', 'h' => 82, 'uts' => 80, 'uas' => 85, 'p' => 80, 'huruf' => 'A-', 'mutu' => 3.75],
            ],
            '20242' => [
                ['mk' => 'IF201', 'h' => 88, 'uts' => 92, 'uas' => 90, 'p' => 88, 'huruf' => 'A', 'mutu' => 4.00],
                ['mk' => 'IF202', 'h' => 82, 'uts' => 80, 'uas' => 85, 'p' => 82, 'huruf' => 'A-', 'mutu' => 3.75],
                ['mk' => 'IF203', 'h' => 78, 'uts' => 75, 'uas' => 80, 'p' => 76, 'huruf' => 'B+', 'mutu' => 3.25],
            ],
            '20251' => [
                ['mk' => 'IF301', 'h' => 92, 'uts' => 90, 'uas' => 94, 'p' => 92, 'huruf' => 'A', 'mutu' => 4.00],
                ['mk' => 'IF302', 'h' => 88, 'uts' => 85, 'uas' => 90, 'p' => 88, 'huruf' => 'A', 'mutu' => 4.00],
                ['mk' => 'IF303', 'h' => 86, 'uts' => 88, 'uas' => 89, 'p' => 86, 'huruf' => 'A', 'mutu' => 4.00],
            ],
            '20252' => [
                ['mk' => 'IF401', 'h' => 90, 'uts' => 92, 'uas' => 95, 'p' => 90, 'huruf' => 'A', 'mutu' => 4.00],
                ['mk' => 'IF402', 'h' => 84, 'uts' => 82, 'uas' => 86, 'p' => 84, 'huruf' => 'A-', 'mutu' => 3.75],
                ['mk' => 'IF403', 'h' => 88, 'uts' => 90, 'uas' => 92, 'p' => 88, 'huruf' => 'A', 'mutu' => 4.00],
            ]
        ];

        foreach ($historicalSemesters as $semKode => $items) {
            $ta = $taModels[$semKode];
            $krsHist = Krs::firstOrCreate(
                ['mahasiswa_id' => $mhs1->id, 'tahun_akademik_id' => $ta->id],
                ['status' => 'disetujui', 'disetujui_oleh' => $dosen1->id, 'disetujui_at' => now()->subMonths(6), 'locked_by_keuangan' => false]
            );

            $semSks = 0;
            $semMutu = 0;

            foreach ($items as $it) {
                $mkObj = $mkModels[$it['mk']];
                $semSks += $mkObj->total_sks;
                $semMutu += ($it['mutu'] * $mkObj->total_sks);

                $kls = Kelas::firstOrCreate(
                    ['mata_kuliah_id' => $mkObj->id, 'tahun_akademik_id' => $ta->id, 'kode_kelas' => "{$it['mk']}-{$semKode}"],
                    [
                        'program_studi_id' => $prodiIF->id,
                        'ruangan_id' => $ruang1->id,
                        'nama_kelas' => "{$mkObj->nama} Kelas {$semKode}",
                        'kapasitas' => 40,
                        'kuota_krs' => 40,
                        'hari' => 'senin',
                        'jam_mulai' => '08:00:00',
                        'jam_selesai' => '10:30:00',
                        'status' => 'aktif',
                    ]
                );

                $det = KrsDetail::firstOrCreate(['krs_id' => $krsHist->id, 'kelas_id' => $kls->id]);
                $akhir = ($it['h'] * 0.20) + ($it['uts'] * 0.25) + ($it['uas'] * 0.35) + ($it['p'] * 0.20);
                
                NilaiMahasiswa::updateOrCreate(
                    ['krs_detail_id' => $det->id],
                    [
                        'nilai_harian' => $it['h'],
                        'nilai_uts' => $it['uts'],
                        'nilai_uas' => $it['uas'],
                        'nilai_praktik' => $it['p'],
                        'nilai_akhir' => $akhir,
                        'nilai_huruf' => $it['huruf'],
                        'bobot_mutu' => $it['mutu'],
                        'is_final' => true,
                        'diinput_oleh' => $dosen1->user_id ?? 1,
                    ]
                );
            }

            $krsHist->total_sks_diambil = $semSks;
            $krsHist->save();

            $ips = $semSks > 0 ? round($semMutu / $semSks, 2) : 3.85;
            Khs::updateOrCreate(
                ['mahasiswa_id' => $mhs1->id, 'tahun_akademik_id' => $ta->id],
                [
                    'ips' => $ips,
                    'ipk' => $ips,
                    'total_sks_semester' => $semSks,
                    'sks_kumulatif' => $semSks,
                ]
            );
        }

        // 9. Semester Aktif (20261)
        $taAktif = $taModels['20261'];
        $krsAktif = Krs::firstOrCreate(
            ['mahasiswa_id' => $mhs1->id, 'tahun_akademik_id' => $taAktif->id],
            ['status' => 'disetujui', 'disetujui_oleh' => $dosen1->id, 'disetujui_at' => now(), 'locked_by_keuangan' => false]
        );

        $kelasAktif1 = Kelas::firstOrCreate(
            ['mata_kuliah_id' => $mkModels['IF101']->id, 'tahun_akademik_id' => $taAktif->id, 'kode_kelas' => 'IF1A-ALGO'],
            [
                'program_studi_id' => $prodiIF->id,
                'ruangan_id' => $ruang1->id,
                'nama_kelas' => 'Algoritma Pemrograman Kelas A',
                'kapasitas' => 40,
                'kuota_krs' => 40,
                'hari' => 'senin',
                'jam_mulai' => '08:00:00',
                'jam_selesai' => '10:30:00',
                'status' => 'aktif',
            ]
        );

        $kelasAktif2 = Kelas::firstOrCreate(
            ['mata_kuliah_id' => $mkModels['IF202']->id, 'tahun_akademik_id' => $taAktif->id, 'kode_kelas' => 'IF3A-BASDAT'],
            [
                'program_studi_id' => $prodiIF->id,
                'ruangan_id' => $ruang1->id,
                'nama_kelas' => 'Basis Data Relasional Kelas A',
                'kapasitas' => 35,
                'kuota_krs' => 35,
                'hari' => 'selasa',
                'jam_mulai' => '10:45:00',
                'jam_selesai' => '13:15:00',
                'status' => 'aktif',
            ]
        );

        $detAktif1 = KrsDetail::firstOrCreate(['krs_id' => $krsAktif->id, 'kelas_id' => $kelasAktif1->id]);
        $detAktif2 = KrsDetail::firstOrCreate(['krs_id' => $krsAktif->id, 'kelas_id' => $kelasAktif2->id]);

        NilaiMahasiswa::updateOrCreate(
            ['krs_detail_id' => $detAktif1->id],
            [
                'nilai_harian' => 85,
                'nilai_uts' => 88,
                'nilai_uas' => 90,
                'nilai_praktik' => 86,
                'nilai_akhir' => 87.5,
                'nilai_huruf' => 'A',
                'bobot_mutu' => 4.00,
                'is_final' => true,
                'diinput_oleh' => $dosen1->user_id ?? 1,
            ]
        );

        NilaiMahasiswa::updateOrCreate(
            ['krs_detail_id' => $detAktif2->id],
            [
                'nilai_harian' => 80,
                'nilai_uts' => 82,
                'nilai_uas' => 85,
                'nilai_praktik' => 83,
                'nilai_akhir' => 82.5,
                'nilai_huruf' => 'A-',
                'bobot_mutu' => 3.75,
                'is_final' => true,
                'diinput_oleh' => $dosen2->user_id ?? 1,
            ]
        );

        $krsAktif->total_sks_diambil = 6;
        $krsAktif->save();
    }
}

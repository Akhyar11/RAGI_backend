<?php

namespace App\Services\Sinapra;

use App\Models\AlatKalibrasi;
use App\Models\Aset;
use App\Models\Gedung;
use App\Models\LabBhp;
use App\Models\MaintenanceLog;
use App\Models\PeminjamanAset;
use App\Models\PeminjamanRuangan;
use App\Models\PengajuanPengadaan;
use App\Models\Ruangan;
use Illuminate\Support\Facades\DB;

class SinapraDashboardService
{
    /**
     * Menghasilkan agregasi metrik, sebaran status aset, early warnings, dan aktivitas terkini.
     */
    public function getSummary(): array
    {
        // 1. Metrik Fasilitas
        $totalGedung = Gedung::count();
        $totalRuangan = Ruangan::count();
        $totalKapasitasRuangan = (int) Ruangan::sum('kapasitas');
        $ruanganTersedia = Ruangan::where('status', 'tersedia')->count();

        // 2. Metrik Inventaris Aset & Nilai Finansial (SIKEU)
        $totalAset = Aset::count();
        $totalHargaPerolehan = (float) Aset::sum('harga_perolehan');
        $totalNilaiBuku = (float) Aset::sum('nilai_buku');
        $totalAkumulasiPenyusutan = max(0, $totalHargaPerolehan - $totalNilaiBuku);
        $totalAsetAdaPic = Aset::whereNotNull('penanggung_jawab_pegawai_id')->count();

        // 3. Metrik Operasional & Peminjaman
        $peminjamanRuanganAktif = PeminjamanRuangan::whereIn('status', ['disetujui', 'aktif'])->count();
        $peminjamanAsetAktif = PeminjamanAset::whereIn('status', ['disetujui', 'dipinjam'])->count();
        $peminjamanPending = PeminjamanRuangan::where('status', 'diajukan')->count()
            + PeminjamanAset::where('status', 'diajukan')->count();

        // 4. Maintenance & Pengadaan
        $maintenanceAktif = MaintenanceLog::whereIn('status', ['dijadwalkan', 'proses'])->count();
        $pengadaanPending = PengajuanPengadaan::where('status', 'diajukan')->count();
        $pengadaanDisetujui = PengajuanPengadaan::where('status', 'disetujui')->count();

        // 5. Breakdown Status Aset
        $statusCounts = Aset::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // Breakdown Kondisi Aset
        $kondisiCounts = Aset::select('kondisi', DB::raw('count(*) as total'))
            ->groupBy('kondisi')
            ->pluck('total', 'kondisi')
            ->toArray();

        // 6. Early Warnings (BHP Kritis & Kalibrasi)
        $bhpKritis = LabBhp::with(['ruangan:id,nama,kode'])
            ->whereColumn('stok_saat_ini', '<=', 'stok_minimum')
            ->take(5)
            ->get(['id', 'ruangan_id', 'kode_bhp', 'nama_bhp', 'stok_saat_ini', 'stok_minimum', 'satuan']);

        $kalibrasiJatuhTempo = AlatKalibrasi::with(['aset:id,nama,kode_aset'])
            ->where(function ($q) {
                $q->whereNull('tanggal_kadaluarsa')
                  ->orWhere('tanggal_kadaluarsa', '<=', now()->addDays(30));
            })
            ->orderBy('tanggal_kadaluarsa', 'asc')
            ->take(5)
            ->get(['id', 'aset_id', 'nomor_sertifikat', 'tanggal_kadaluarsa', 'status_kelayakan']);

        // 7. Recent Peminjaman Ruangan & Aset
        $recentPeminjamanRuangan = PeminjamanRuangan::with(['ruangan:id,nama,kode', 'user:id,name'])
            ->latest('id')
            ->take(5)
            ->get(['id', 'ruangan_id', 'user_id', 'keperluan', 'tanggal', 'jam_mulai', 'jam_selesai', 'status']);

        $recentPeminjamanAset = PeminjamanAset::with(['aset:id,nama,kode_aset', 'user:id,name'])
            ->latest('id')
            ->take(5)
            ->get(['id', 'aset_id', 'user_id', 'keperluan', 'tanggal_pinjam', 'tanggal_kembali_rencana', 'status']);

        // 8. Recent Aset Terdaftar
        $recentAset = Aset::with(['penanggungJawab:id,nama_lengkap,nip'])
            ->latest('id')
            ->take(5)
            ->get(['id', 'kode_aset', 'nama', 'penanggung_jawab_pegawai_id', 'harga_perolehan', 'nilai_buku', 'kondisi', 'status']);

        // 9. Distribusi Aset & Ruangan per Program Studi
        $prodiStats = \App\Models\Siakad\ProgramStudi::select('id', 'kode_prodi', 'nama', 'jenjang')
            ->withCount(['asets as total_aset', 'ruangans as total_ruangan'])
            ->withSum('asets as total_nilai_aset', 'harga_perolehan')
            ->orderBy('nama', 'asc')
            ->get()
            ->map(function ($prodi) {
                return [
                    'id' => $prodi->id,
                    'kode_prodi' => $prodi->kode_prodi,
                    'nama' => $prodi->nama,
                    'jenjang' => $prodi->jenjang,
                    'total_aset' => (int) $prodi->total_aset,
                    'total_ruangan' => (int) $prodi->total_ruangan,
                    'total_nilai_aset' => (float) ($prodi->total_nilai_aset ?? 0),
                ];
            });

        $umumAsetCount = Aset::whereNull('program_studi_id')->count();
        $umumAsetNilai = (float) Aset::whereNull('program_studi_id')->sum('harga_perolehan');
        $umumRuanganCount = Ruangan::whereNull('program_studi_id')->count();

        return [
            'metrics' => [
                'total_gedung' => $totalGedung,
                'total_ruangan' => $totalRuangan,
                'ruangan_tersedia' => $ruanganTersedia,
                'total_kapasitas_ruangan' => $totalKapasitasRuangan,
                'total_aset' => $totalAset,
                'total_harga_perolehan' => $totalHargaPerolehan,
                'total_nilai_buku' => $totalNilaiBuku,
                'total_akumulasi_penyusutan' => $totalAkumulasiPenyusutan,
                'total_aset_ada_pic' => $totalAsetAdaPic,
                'peminjaman_ruangan_aktif' => $peminjamanRuanganAktif,
                'peminjaman_aset_aktif' => $peminjamanAsetAktif,
                'peminjaman_pending' => $peminjamanPending,
                'maintenance_aktif' => $maintenanceAktif,
                'pengadaan_pending' => $pengadaanPending,
                'pengadaan_disetujui' => $pengadaanDisetujui,
            ],
            'breakdown_aset' => [
                'status' => [
                    'tersedia' => $statusCounts['tersedia'] ?? 0,
                    'dipinjam' => $statusCounts['dipinjam'] ?? 0,
                    'maintenance' => $statusCounts['maintenance'] ?? 0,
                    'rusak' => $statusCounts['rusak'] ?? 0,
                    'dihapus' => $statusCounts['dihapus'] ?? 0,
                ],
                'kondisi' => [
                    'baik' => $kondisiCounts['baik'] ?? 0,
                    'rusak_ringan' => $kondisiCounts['rusak_ringan'] ?? 0,
                    'rusak_berat' => $kondisiCounts['rusak_berat'] ?? 0,
                ],
            ],
            'distribusi_prodi' => [
                'prodi_list' => $prodiStats,
                'fasilitas_umum' => [
                    'nama' => 'Umum Kampus / Rektorat',
                    'total_aset' => $umumAsetCount,
                    'total_ruangan' => $umumRuanganCount,
                    'total_nilai_aset' => $umumAsetNilai,
                ],
            ],
            'early_warnings' => [
                'bhp_kritis_count' => LabBhp::whereColumn('stok_saat_ini', '<=', 'stok_minimum')->count(),
                'bhp_kritis_list' => $bhpKritis,
                'kalibrasi_urgent_count' => AlatKalibrasi::where(function ($q) {
                    $q->whereNull('tanggal_kadaluarsa')
                      ->orWhere('tanggal_kadaluarsa', '<=', now()->addDays(30));
                })->count(),
                'kalibrasi_urgent_list' => $kalibrasiJatuhTempo,
            ],
            'recent_activities' => [
                'peminjaman_ruangan' => $recentPeminjamanRuangan,
                'peminjaman_aset' => $recentPeminjamanAset,
                'aset_terbaru' => $recentAset,
            ],
        ];
    }
}

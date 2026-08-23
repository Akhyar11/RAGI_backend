<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Spmb\PendaftaranCalonMhs;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanSpmbController extends Controller
{
    public function statistik(Request $request)
    {
        abort_unless($request->user()?->hasPermission('spmb.laporan.read'), 403);

        // 1. Pendaftar per Status
        $perStatus = PendaftaranCalonMhs::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        // 2. Lulus per Prodi (Tabel Relasi Join ke program_studi agar dapat nama)
        $perProdi = DB::table('hasil_seleksi')
            ->join('program_studi', 'hasil_seleksi.program_studi_diterima_id', '=', 'program_studi.id')
            ->where('hasil_seleksi.status', \App\Models\Spmb\HasilSeleksi::STATUS_LULUS)
            ->select('program_studi.nama as nama_prodi', 'program_studi_diterima_id', DB::raw('count(*) as total_lulus'))
            ->groupBy('program_studi_diterima_id', 'program_studi.nama')
            ->get();

        // 3. Pendaftar per Gelombang
        $perGelombang = PendaftaranCalonMhs::join('gelombang_penerimaan', 'pendaftaran_calon_mhs.gelombang_id', '=', 'gelombang_penerimaan.id')
            ->select('gelombang_penerimaan.nama as nama_gelombang', DB::raw('count(pendaftaran_calon_mhs.id) as total'))
            ->groupBy('gelombang_penerimaan.nama')
            ->get();

        // 4. Data Funnel Pendaftar (Funneling conversion)
        $totalPendaftar = PendaftaranCalonMhs::count();
        $totalBayar = PendaftaranCalonMhs::where('status_pembayaran', PendaftaranCalonMhs::STATUS_PEMBAYARAN_LUNAS)->count();
        $totalVerifikasi = PendaftaranCalonMhs::where('status', PendaftaranCalonMhs::STATUS_LULUS_ADMINISTRASI)->count();
        $totalLulus = DB::table('hasil_seleksi')->where('status', \App\Models\Spmb\HasilSeleksi::STATUS_LULUS)->count();
        $totalDaftarUlang = DB::table('hasil_seleksi')->where('status_daftar_ulang', 'lunas')->count();

        $funnelData = [
            ['label' => 'Total Pendaftar', 'value' => $totalPendaftar],
            ['label' => 'Sudah Membayar', 'value' => $totalBayar],
            ['label' => 'Lolos Administrasi', 'value' => $totalVerifikasi],
            ['label' => 'Lolos Seleksi', 'value' => $totalLulus],
            ['label' => 'Daftar Ulang Lunas', 'value' => $totalDaftarUlang],
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'per_status' => $perStatus,
                'lulus_per_prodi' => $perProdi,
                'per_gelombang' => $perGelombang,
                'funnel_data' => $funnelData
            ]
        ]);
    }

    public function exportCsv(Request $request)
    {
        abort_unless($request->user()?->hasPermission('spmb.laporan.export'), 403);

        $response = new StreamedResponse(function() {
            $handle = fopen('php://output', 'w');
            
            // Header CSV
            fputcsv($handle, [
                'No Pendaftaran', 'Nama Lengkap', 'NIK', 'Asal Sekolah', 
                'Status Pendaftaran', 'Status Pembayaran', 'Status Kelulusan', 'Status Daftar Ulang'
            ]);

            // Data
            PendaftaranCalonMhs::with(['hasilSeleksi'])
                ->chunk(500, function($pendaftars) use ($handle) {
                    foreach ($pendaftars as $p) {
                        fputcsv($handle, [
                            $p->no_pendaftaran,
                            $p->nama_lengkap,
                            $p->nik,
                            $p->asal_sekolah,
                            $p->status,
                            $p->status_pembayaran,
                            $p->hasilSeleksi->status ?? 'belum_ada',
                            $p->hasilSeleksi->status_daftar_ulang ?? 'belum'
                        ]);
                    }
                });

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="Laporan_Pendaftar_SPMB.csv"');

        return $response;
    }
}

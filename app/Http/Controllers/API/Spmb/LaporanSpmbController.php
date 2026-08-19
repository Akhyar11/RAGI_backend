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
        // Pendaftar per Status
        $perStatus = PendaftaranCalonMhs::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        // Lulus per Prodi
        $perProdi = DB::table('hasil_seleksi')
            ->where('status', 'lulus')
            ->select('program_studi_diterima_id', DB::raw('count(*) as total_lulus'))
            ->groupBy('program_studi_diterima_id')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'per_status' => $perStatus,
                'lulus_per_prodi' => $perProdi
            ]
        ]);
    }

    public function exportCsv(Request $request)
    {
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

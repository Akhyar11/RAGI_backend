<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\UnitKerja;
use App\Services\Simpeg\SimpegAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SimpegDashboardController extends Controller
{
    public function __construct(
        protected SimpegAnalyticsService $analyticsService
    ) {}

    /**
     * GET /api/simpeg/dashboard-stats
     * Real-time metrics for SIMPEG Admin dashboard.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('simpeg.dashboard.read') && !$user->hasPermission('simpeg.pegawai.read') && !$user->hasPermission('simpeg.pegawai.manage'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat statistik dashboard SIMPEG.',
            ], 403);
        }

        $totalPegawai = Pegawai::count();

        $totalDosen = Pegawai::where(function ($q) {
            $q->where('jenis_pegawai', 'like', '%dosen%')
              ->orWhereHas('roles', function ($r) {
                  $r->where('slug', 'dosen')->orWhere('name', 'like', '%dosen%');
              });
        })->count();

        $totalTendik = Pegawai::where(function ($q) {
            $q->where('jenis_pegawai', 'like', '%tendik%')
              ->orWhereHas('roles', function ($r) {
                  $r->where('slug', 'tendik')->orWhere('name', 'like', '%tendik%');
              });
        })->count();

        $totalUnitKerja = UnitKerja::count();

        $recentPegawai = Pegawai::with(['unitKerja', 'roles:id,name,slug'])
            ->latest('id')
            ->take(5)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_pegawai' => $totalPegawai,
                'total_dosen' => $totalDosen,
                'total_tendik' => $totalTendik,
                'total_unit_kerja' => $totalUnitKerja,
                'recent_pegawai' => $recentPegawai,
            ]
        ]);
    }

    /**
     * GET /api/simpeg/analytics/presensi
     * Analitik Kedisiplinan & Presensi (Tab 1 Dashboard):
     * 1. Tren Kehadiran & Keterlambatan Bulanan (12 Bulan)
     * 2. Tren Penerbitan Surat Peringatan (SP) (12 Bulan)
     * 3. Top 5 Unit Kerja Paling Disiplin vs Rawan Terlambat
     */
    public function presensiAnalytics(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('simpeg.dashboard.read') && !$user->hasPermission('simpeg.presensi.read') && !$user->hasPermission('simpeg.presensi.manage'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat analitik presensi SIMPEG.',
            ], 403);
        }

        $tahun = $request->integer('tahun', (int) date('Y'));
        $data = $this->analyticsService->getPresensiAnalytics($tahun);

        return response()->json([
            'status' => 'success',
            'message' => 'Data analitik presensi dan kedisiplinan berhasil diambil',
            'data' => $data,
        ]);
    }

    /**
     * GET /api/simpeg/analytics/demografi
     * Analitik Kualifikasi & Demografi SDM (Tab 2 Dashboard):
     * 4. Piramida Jabatan Fungsional Akademik (JAD) Dosen
     * 5. Rasio Dosen Bersertifikasi (Serdos)
     * 6. Penyebaran Kelompok Usia & Proyeksi Pensiun
     */
    public function demografiAnalytics(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('simpeg.dashboard.read') && !$user->hasPermission('simpeg.pegawai.read') && !$user->hasPermission('simpeg.pegawai.manage'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat analitik demografi SIMPEG.',
            ], 403);
        }

        $data = $this->analyticsService->getDemografiAnalytics();

        return response()->json([
            'status' => 'success',
            'message' => 'Data analitik demografi dan kualifikasi SDM berhasil diambil',
            'data' => $data,
        ]);
    }
}


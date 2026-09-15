<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\UnitKerja;
use Illuminate\Http\Request;

class SimpegDashboardController extends Controller
{
    /**
     * GET /api/simpeg/dashboard-stats
     * Real-time metrics for SIMPEG Admin dashboard.
     */
    public function stats(Request $request)
    {
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
}

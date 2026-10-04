<?php

namespace App\Http\Controllers\Arsip;

use App\Http\Controllers\Controller;
use App\Models\Arsip\KopSurat;
use App\Models\Arsip\NomorSurat;
use App\Models\Arsip\RequestNomorSurat;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArsipDashboardController extends Controller
{
    /**
     * Mengambil ringkasan metrik dashboard modul arsip.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('arsip.dashboard.read') && !$user->hasPermission('arsip.nomor_surat.read') && !$user->isAdmin())) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat dashboard arsip.',
            ], 403);
        }

        $currentYear = (int) Carbon::now()->year;

        $totalNomor = NomorSurat::count();
        $nomorTahunIni = NomorSurat::where('tahun', $currentYear)->count();
        $nomorTerpakai = NomorSurat::where('status', 'terpakai')->count();
        $nomorDireservasi = NomorSurat::where('status', 'direservasi')->count();

        $requestPending = RequestNomorSurat::where('status', 'menunggu_verifikasi')->count();
        $requestDisetujui = RequestNomorSurat::where('status', 'disetujui')->count();

        $totalKopSurat = KopSurat::count();
        $kopBaruAktif = KopSurat::where('versi', 'baru')->where('is_active', true)->exists();
        $kopLamaAktif = KopSurat::where('versi', 'lama')->where('is_active', true)->exists();

        $recentNomor = NomorSurat::with('pembuat:id,name,email')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        $recentRequests = RequestNomorSurat::with('user:id,name,email')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Data statistik dashboard arsip berhasil dimuat.',
            'data' => [
                'current_year' => $currentYear,
                'total_nomor_surat' => $totalNomor,
                'nomor_surat_tahun_ini' => $nomorTahunIni,
                'nomor_surat_terpakai' => $nomorTerpakai,
                'nomor_surat_direservasi' => $nomorDireservasi,
                'request_pending' => $requestPending,
                'request_disetujui' => $requestDisetujui,
                'total_kop_surat' => $totalKopSurat,
                'kop_status' => [
                    'baru_aktif' => $kopBaruAktif,
                    'lama_aktif' => $kopLamaAktif,
                ],
                'recent_nomor' => $recentNomor,
                'recent_requests' => $recentRequests,
            ],
        ]);
    }
}

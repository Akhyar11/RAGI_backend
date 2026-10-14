<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\SuratPeringatan;
use App\Services\Simpeg\SuratPeringatanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuratPeringatanController extends Controller
{
    /**
     * GET /api/simpeg/surat-peringatan/my
     * Mengambil daftar surat peringatan untuk pegawai yang sedang login.
     */
    public function mySuratPeringatan(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Cari record pegawai milik user
        $pegawai = Pegawai::where('user_id', $user->id)->first();
        if (!$pegawai && $user->email) {
            $pegawai = Pegawai::where('email', $user->email)->first();
        }

        if (!$pegawai) {
            return response()->json([
                'status' => 'success',
                'message' => 'Data pegawai tidak ditemukan untuk akun ini',
                'data' => [],
            ]);
        }

        $list = SuratPeringatan::where('pegawai_id', $pegawai->id)
            ->with(['presensiPeriode', 'gajiPegawai'])
            ->orderBy('tanggal_terbit', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar surat peringatan pegawai berhasil diambil',
            'data' => $list,
        ]);
    }

    /**
     * GET /api/simpeg/surat-peringatan
     * Daftar seluruh surat peringatan untuk Admin Kepegawaian (SIMPEG).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('simpeg.pegawai.manage') && !$user->hasPermission('simpeg.surat_peringatan.read') && !$user->hasPermission('simpeg.surat_peringatan.manage'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat data surat peringatan.',
            ], 403);
        }

        $query = SuratPeringatan::with(['pegawai.unitKerja', 'presensiPeriode', 'gajiPegawai']);

        if ($request->filled('periode')) {
            $query->where('periode_bulan_tahun', $request->input('periode'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('pegawai_id')) {
            $query->where('pegawai_id', $request->integer('pegawai_id'));
        }

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('nomor_sp', 'like', "%{$s}%")
                  ->orWhere('judul', 'like', "%{$s}%")
                  ->orWhereHas('pegawai', function ($pq) use ($s) {
                      $pq->where('nama_lengkap', 'like', "%{$s}%")
                        ->orWhere('nip', 'like', "%{$s}%");
                  });
            });
        }

        $sortField = $request->input('sort_by', 'tanggal_terbit');
        $allowedSorts = ['id', 'nomor_sp', 'tanggal_terbit', 'total_terlambat', 'periode_bulan_tahun', 'status'];
        if (!in_array($sortField, $allowedSorts, true)) {
            $sortField = 'tanggal_terbit';
        }

        $sortOrder = strtolower($request->input('sort_order', $request->input('sort_dir', 'desc')));
        $sortOrder = $sortOrder === 'asc' ? 'asc' : 'desc';

        $perPage = min(100, $request->integer('per_page', 15));
        $items = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar surat peringatan berhasil diambil',
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/simpeg/surat-peringatan/{id}
     * Rincian lengkap dokumen surat peringatan.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $sp = SuratPeringatan::with(['pegawai.unitKerja', 'presensiPeriode', 'gajiPegawai'])->find($id);
        if (!$sp) {
            return response()->json([
                'status' => 'error',
                'message' => 'Surat Peringatan tidak ditemukan.',
            ], 404);
        }

        // Cek otorisasi: pegawai ybs atau admin
        $isOwner = false;
        $myPegawai = Pegawai::where('user_id', $user->id)->first();
        if (!$myPegawai && $user->email) {
            $myPegawai = Pegawai::where('email', $user->email)->first();
        }
        if ($myPegawai && $myPegawai->id === $sp->pegawai_id) {
            $isOwner = true;
        }

        $canManage = $user->hasPermission('simpeg.pegawai.manage') || $user->hasPermission('simpeg.surat_peringatan.read') || $user->hasPermission('simpeg.surat_peringatan.manage');

        if (!$isOwner && !$canManage) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat rincian surat peringatan ini.',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Rincian surat peringatan berhasil diambil',
            'data' => $sp,
        ]);
    }

    /**
     * GET /api/simpeg/surat-peringatan/monitoring-tahunan
     * Matriks monitoring Surat Peringatan & Keterlambatan presensi 12 bulan.
     */
    public function annualMonitoring(Request $request, SuratPeringatanService $service): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('simpeg.pegawai.manage') && !$user->hasPermission('simpeg.surat_peringatan.read') && !$user->hasPermission('simpeg.surat_peringatan.manage'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat monitoring surat peringatan.',
            ], 403);
        }

        $tahun = $request->integer('tahun', (int) date('Y'));
        if ($tahun < 2000 || $tahun > 2100) {
            $tahun = (int) date('Y');
        }

        $filters = [
            'search' => $request->input('search'),
            'unit_kerja_id' => $request->filled('unit_kerja_id') ? $request->integer('unit_kerja_id') : null,
            'risk_level' => $request->input('risk_level'),
            'sort_by' => $request->input('sort_by', 'total_sp'),
            'sort_order' => $request->input('sort_order', $request->input('sort_dir', 'desc')),
            'per_page' => min(100, $request->integer('per_page', 15)),
            'page' => max(1, $request->integer('page', 1)),
        ];

        $result = $service->getAnnualMonitoring($tahun, $filters);

        return response()->json([
            'status' => 'success',
            'message' => "Matriks monitoring surat peringatan tahun {$tahun} berhasil diambil",
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }
}

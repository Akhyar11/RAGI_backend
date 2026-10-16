<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Services\Sikeu\SikeuCompatService;
use Illuminate\Http\Request;

/**
 * Kompatibilitas legacy SIKEU untuk gate SIAKAD/SPMB (server-to-server).
 *
 * Parity: cek_pembayaran.js, cek_tagihan_ujian.js, biaya_lain.js,
 * pendaftar.js (belumdu). Berada dalam grup auth:api + throttle.
 */
class SikeuCompatController extends Controller
{
    public function __construct(private SikeuCompatService $service) {}

    private function tolakTanpaAkses(Request $request): ?\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->isSuperAdmin() && !$user->isAdmin() && !$user->hasPermission('sikeu.tagihan.read'))) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki akses data tagihan.'], 403);
        }

        return null;
    }

    /**
     * GET /api/v1/sikeu/compat/ringkasan-pembayaran?mahasiswa_id=&nim=
     */
    public function ringkasan(Request $request)
    {
        if ($tolak = $this->tolakTanpaAkses($request)) {
            return $tolak;
        }

        $request->validate([
            'mahasiswa_id' => 'nullable|integer|exists:siakad_mahasiswa,id',
            'nim' => 'nullable|string|max:20',
        ]);

        if (!$request->filled('mahasiswa_id') && !$request->filled('nim')) {
            return response()->json(['status' => 'error', 'message' => 'Isi mahasiswa_id atau nim.'], 422);
        }

        try {
            $data = $this->service->ringkasan($request->integer('mahasiswa_id') ?: null, $request->query('nim'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 404);
        }

        return response()->json(['status' => 'success', 'message' => 'Ringkasan pembayaran berhasil dimuat', 'data' => $data]);
    }

    /**
     * GET /api/v1/sikeu/compat/tagihan-aktif?mahasiswa_id=&nim=
     */
    public function tagihanAktif(Request $request)
    {
        if ($tolak = $this->tolakTanpaAkses($request)) {
            return $tolak;
        }

        $request->validate([
            'mahasiswa_id' => 'nullable|integer|exists:siakad_mahasiswa,id',
            'nim' => 'nullable|string|max:20',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if (!$request->filled('mahasiswa_id') && !$request->filled('nim')) {
            return response()->json(['status' => 'error', 'message' => 'Isi mahasiswa_id atau nim.'], 422);
        }

        try {
            $perPage = min(100, $request->integer('per_page', 15));
            $data = $this->service->tagihanAktif(
                $request->integer('mahasiswa_id') ?: null,
                $request->query('nim'),
                $perPage,
                (int) $request->integer('page', 1)
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Tagihan aktif berhasil dimuat',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
        ]);
    }

    /**
     * GET /api/v1/sikeu/compat/eligibilitas-ujian?mahasiswa_id=&tipe_ujian=UTS&semester=
     */
    public function eligibilitasUjian(Request $request)
    {
        if ($tolak = $this->tolakTanpaAkses($request)) {
            return $tolak;
        }

        $request->validate([
            'mahasiswa_id' => 'required|integer|exists:siakad_mahasiswa,id',
            'tipe_ujian' => 'required|string|max:5',
            'semester' => 'nullable|integer|min:1|max:14',
        ]);

        try {
            $data = $this->service->eligibilitasUjian(
                (int) $request->query('mahasiswa_id'),
                (string) $request->query('tipe_ujian'),
                $request->query('semester') !== null ? (int) $request->query('semester') : null
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'success', 'message' => 'Eligibilitas ujian berhasil dicek', 'data' => $data]);
    }

    /**
     * GET /api/v1/sikeu/compat/biaya-lain?tipe=&search=
     */
    public function biayaLain(Request $request)
    {
        if ($tolak = $this->tolakTanpaAkses($request)) {
            return $tolak;
        }

        $request->validate([
            'tipe' => 'nullable|string|max:30',
            'search' => 'nullable|string|max:191',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = min(100, $request->integer('per_page', 15));
        $data = $this->service->biayaLain($request->query('tipe'), $request->query('search'), $perPage, (int) $request->integer('page', 1));

        return response()->json([
            'status' => 'success',
            'message' => 'Katalog biaya berhasil dimuat',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
        ]);
    }

    /**
     * GET /api/v1/sikeu/compat/pendaftar/belum-du?search=
     */
    public function belumDaftarUlang(Request $request)
    {
        if ($tolak = $this->tolakTanpaAkses($request)) {
            return $tolak;
        }

        $request->validate([
            'search' => 'nullable|string|max:191',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = min(100, $request->integer('per_page', 15));
        $data = $this->service->belumDaftarUlang($request->query('search'), $perPage, (int) $request->integer('page', 1));

        return response()->json([
            'status' => 'success',
            'message' => 'Pendaftar belum daftar ulang berhasil dimuat',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
        ]);
    }
}

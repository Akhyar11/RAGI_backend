<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Models\Simpeg\PresensiPegawai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PresensiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('simpeg.presensi.read') && !$request->user()->hasPermission('simpeg.presensi.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk melihat Presensi.'
            ], 403);
        }

        $query = PresensiPegawai::with('pegawai');

        if ($request->has('pegawai_id')) {
            $query->where('pegawai_id', $request->pegawai_id);
        }

        if ($request->has('tanggal')) {
            $query->where('tanggal', $request->tanggal);
        }

        $presensi = $query->latest('tanggal')->get();

        return response()->json([
            'status' => 'success',
            'data' => $presensi,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('simpeg.presensi.create') && !$request->user()->hasPermission('simpeg.presensi.read') && !$request->user()->hasPermission('simpeg.presensi.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk mencatat Presensi.'
            ], 403);
        }

        $validated = $request->validate([
            'pegawai_id' => 'required|exists:pegawai,id',
            'tanggal' => 'required|date',
            'jam_masuk' => 'nullable|string',
            'jam_keluar' => 'nullable|string',
            'status_kehadiran' => 'required|in:hadir,izin,sakit,alfa,dinas',
            'lat_long' => 'nullable|string',
            'foto_presensi' => 'nullable|string',
            'catatan' => 'nullable|string',
        ]);

        $presensi = PresensiPegawai::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Absensi presensi berhasil dicatat',
            'data' => $presensi,
        ], 201);
    }
}

<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Models\Simpeg\PenilaianKinerja;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PenilaianKinerjaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('simpeg.kinerja.read') && !$request->user()->hasPermission('simpeg.kinerja.evaluate')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk melihat Penilaian Kinerja.'
            ], 403);
        }

        $query = PenilaianKinerja::with(['pegawai', 'evaluator']);

        if ($request->has('pegawai_id')) {
            $query->where('pegawai_id', $request->pegawai_id);
        } elseif ($request->user()->user_type !== 'admin' && !$request->user()->hasPermission('simpeg.kinerja.evaluate')) {
            $pegId = $request->user()->pegawai?->id;
            if ($pegId) {
                $query->where('pegawai_id', $pegId);
            }
        }

        if ($request->has('tahun')) {
            $query->where('tahun', $request->tahun);
        }

        $kinerja = $query->latest('tahun')->get();

        return response()->json([
            'status' => 'success',
            'data' => $kinerja,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('simpeg.kinerja.create') && !$request->user()->hasPermission('simpeg.kinerja.evaluate')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk menginput Penilaian Kinerja SKP/BKD.'
            ], 403);
        }

        $validated = $request->validate([
            'pegawai_id' => 'required|exists:pegawai,id',
            'tahun' => 'required|integer',
            'semester' => 'required|in:ganjil,genap,tahunan',
            'nilai_skp' => 'required|numeric',
            'nilai_bkd' => 'nullable|numeric',
            'predikat' => 'required|in:sangat_baik,baik,cukup,kurang,sangat_kurang',
            'catatan_evaluator' => 'nullable|string',
        ]);

        $validated['evaluator_id'] = $request->user()?->id;

        $kinerja = PenilaianKinerja::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Penilaian kinerja SKP/BKD berhasil disimpan',
            'data' => $kinerja,
        ], 201);
    }
}

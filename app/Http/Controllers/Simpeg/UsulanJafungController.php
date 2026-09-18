<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Models\Simpeg\UsulanJafung;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsulanJafungController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('simpeg.usulan_jafung.read') && !$request->user()->hasPermission('simpeg.usulan_jafung.request') && !$request->user()->hasPermission('simpeg.usulan_jafung.verify')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses Ditolak: Peran Anda (Tendik/Mahasiswa) tidak memiliki hak akses Usulan Jafung.'
            ], 403);
        }

        $query = UsulanJafung::with(['pegawai', 'jafungAsal', 'jafungTujuan']);

        if ($request->has('pegawai_id')) {
            $query->where('pegawai_id', $request->pegawai_id);
        } elseif (!$request->user()->hasRole('superadmin') && !$request->user()->hasRole('admin') && !$request->user()->hasPermission('simpeg.usulan_jafung.verify')) {
            $pegId = $request->user()->pegawai?->id;
            if ($pegId) {
                $query->where('pegawai_id', $pegId);
            }
        }

        $limit = (int) $request->input('limit', 15);
        $paginated = $query->latest()->paginate($limit);

        return response()->json([
            'status' => 'success',
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'from' => $paginated->firstItem(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'to' => $paginated->lastItem(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('simpeg.usulan_jafung.create') && !$request->user()->hasPermission('simpeg.usulan_jafung.request')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses Ditolak: Anda tidak memiliki permission untuk mengajukan Usulan Jafung.'
            ], 403);
        }

        $validated = $request->validate([
            'pegawai_id' => 'required|exists:pegawai,id',
            'jafung_asal_id' => 'nullable|exists:jabatan_fungsional_akademik,id',
            'jafung_tujuan_id' => 'required|exists:jabatan_fungsional_akademik,id',
            'angka_kredit_usulan' => 'required|integer',
            'catatan_reviewer' => 'nullable|string',
        ]);

        $usulan = UsulanJafung::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Usulan kenaikan Jafung berhasil diajukan',
            'data' => $usulan,
        ], 201);
    }
}

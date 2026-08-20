<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Spmb\JadwalUjianSpmb;
use App\Models\Spmb\PesertaUjianSpmb;

use App\Services\MenuService;

class JadwalUjianController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!MenuService::hasAccess($user, '/spmb/ujian/jadwal')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. Menu ini belum diaktifkan untuk role Anda di database.'
            ], 403);
        }

        $query = JadwalUjianSpmb::with('gelombangPenerimaan');
        
        if ($request->has('gelombang_id')) {
            $query->where('gelombang_id', $request->gelombang_id);
        }

        $data = $query->get();
        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'gelombang_id' => 'required|exists:gelombang_penerimaan,id',
            'ruangan_id' => 'nullable|integer',
            'nama_sesi' => 'required|string',
            'tanggal' => 'required|date',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i',
            'kapasitas' => 'required|integer|min:1',
            'tipe_ujian' => 'required|in:tulis,praktik,wawancara',
        ]);

        $jadwal = JadwalUjianSpmb::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Jadwal ujian berhasil ditambahkan.',
            'data' => $jadwal
        ]);
    }

    public function show($id)
    {
        $jadwal = JadwalUjianSpmb::with('pesertaUjianSpmb.pendaftaranCalonMhs')->findOrFail($id);
        return response()->json([
            'status' => 'success',
            'data' => $jadwal
        ]);
    }

    public function assignPeserta(Request $request, $id)
    {
        $jadwal = JadwalUjianSpmb::findOrFail($id);
        
        $validated = $request->validate([
            'pendaftaran_ids' => 'required|array',
            'pendaftaran_ids.*' => 'exists:pendaftaran_calon_mhs,id'
        ]);

        $currentPesertaCount = PesertaUjianSpmb::where('jadwal_ujian_id', $id)->count();
        $newPesertaCount = count($validated['pendaftaran_ids']);

        if (($currentPesertaCount + $newPesertaCount) > $jadwal->kapasitas) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kapasitas ruangan tidak mencukupi.'
            ], 400);
        }

        foreach ($validated['pendaftaran_ids'] as $pendaftaranId) {
            PesertaUjianSpmb::updateOrCreate(
                ['pendaftaran_id' => $pendaftaranId, 'jadwal_ujian_id' => $id],
                ['kehadiran' => 'belum_hadir']
            );
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Peserta berhasil ditugaskan ke jadwal ujian.'
        ]);
    }
}

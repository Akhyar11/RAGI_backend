<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Spmb\SpmbKuotaProdi;

use App\Services\MenuService;

class SpmbKuotaProdiController extends Controller
{
    public function index(Request $request)
    {
        $query = SpmbKuotaProdi::query();
        
        if ($request->has('tahun_akademik_id')) {
            $query->where('tahun_akademik_id', $request->tahun_akademik_id);
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
            'tahun_akademik_id' => 'required|integer',
            'program_studi_id' => 'required|integer',
            'kuota_total' => 'required|integer|min:1',
        ]);

        $kuota = SpmbKuotaProdi::updateOrCreate(
            [
                'tahun_akademik_id' => $validated['tahun_akademik_id'],
                'program_studi_id' => $validated['program_studi_id'],
            ],
            [
                'kuota_total' => $validated['kuota_total']
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Kuota prodi berhasil disimpan.',
            'data' => $kuota
        ]);
    }

    public function show($id)
    {
        $kuota = SpmbKuotaProdi::findOrFail($id);
        return response()->json([
            'status' => 'success',
            'data' => $kuota
        ]);
    }

    public function update(Request $request, $id)
    {
        $kuota = SpmbKuotaProdi::findOrFail($id);
        
        $validated = $request->validate([
            'kuota_total' => 'required|integer|min:1',
        ]);

        $kuota->update(['kuota_total' => $validated['kuota_total']]);

        return response()->json([
            'status' => 'success',
            'message' => 'Kuota prodi berhasil diperbarui.',
            'data' => $kuota
        ]);
    }

    public function destroy($id)
    {
        $kuota = SpmbKuotaProdi::findOrFail($id);
        $kuota->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Kuota prodi berhasil dihapus.'
        ]);
    }
}

<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Models\Simpeg\UnitKerja;
use App\Services\Simpeg\UnitKerjaService;
use Illuminate\Http\Request;

class UnitKerjaController extends Controller
{
    protected $unitKerjaService;

    public function __construct(UnitKerjaService $unitKerjaService)
    {
        $this->unitKerjaService = $unitKerjaService;
    }

    public function index(Request $request)
    {
        if ($request->has('tree')) {
            $data = $this->unitKerjaService->getTree();
        } else {
            $data = $this->unitKerjaService->getAll();
        }

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        if (!$request->user()->hasPermission('simpeg.unit_kerja.create') && !$request->user()->hasPermission('simpeg.unit_kerja.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk menambah Unit Kerja.'
            ], 403);
        }

        $request->validate([
            'induk_id' => 'nullable|exists:unit_kerja,id',
            'kode' => 'required|string|unique:unit_kerja,kode',
            'nama' => 'required|string',
            'tipe' => 'required|in:rektorat,fakultas,prodi,lp3m,biro,unit',
            'is_active' => 'boolean',
        ]);

        $unitKerja = $this->unitKerjaService->create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Unit Kerja berhasil dibuat.',
            'data' => $unitKerja
        ], 201);
    }

    public function show($id)
    {
        $unitKerja = UnitKerja::with(['parent', 'children', 'jabatan', 'pegawai'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $unitKerja
        ]);
    }

    public function update(Request $request, $id)
    {
        if (!$request->user()->hasPermission('simpeg.unit_kerja.update') && !$request->user()->hasPermission('simpeg.unit_kerja.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk mengubah Unit Kerja.'
            ], 403);
        }

        $unitKerja = UnitKerja::findOrFail($id);

        $request->validate([
            'induk_id' => 'nullable|exists:unit_kerja,id',
            'kode' => 'sometimes|string|unique:unit_kerja,kode,' . $id,
            'nama' => 'sometimes|string',
            'tipe' => 'sometimes|in:rektorat,fakultas,prodi,lp3m,biro,unit',
            'is_active' => 'boolean',
        ]);

        $updated = $this->unitKerjaService->update($unitKerja, $request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Unit Kerja berhasil diperbarui.',
            'data' => $updated
        ]);
    }

    public function destroy(Request $request, $id)
    {
        if (!$request->user()->hasPermission('simpeg.unit_kerja.delete') && !$request->user()->hasPermission('simpeg.unit_kerja.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk menghapus Unit Kerja.'
            ], 403);
        }

        $unitKerja = UnitKerja::findOrFail($id);
        $this->unitKerjaService->delete($unitKerja);

        return response()->json([
            'status' => 'success',
            'message' => 'Unit Kerja berhasil dihapus.'
        ]);
    }
}

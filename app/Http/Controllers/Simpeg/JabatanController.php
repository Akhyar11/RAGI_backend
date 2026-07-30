<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Models\Simpeg\Jabatan;
use Illuminate\Http\Request;

class JabatanController extends Controller
{
    public function index(Request $request)
    {
        $query = Jabatan::with('unitKerja');

        if ($request->has('unit_kerja_id')) {
            $query->where('unit_kerja_id', $request->unit_kerja_id);
        }

        if ($request->has('tipe')) {
            $query->where('tipe', $request->tipe);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get()
        ]);
    }

    public function store(Request $request)
    {
        if (!$request->user()->hasPermission('simpeg.jabatan.create') && !$request->user()->hasPermission('simpeg.jabatan.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk menambah data Jabatan.'
            ], 403);
        }

        $request->validate([
            'unit_kerja_id' => 'nullable|exists:unit_kerja,id',
            'nama' => 'required|string',
            'tipe' => 'required|in:struktural,fungsional,teknis',
            'level_jabatan' => 'integer',
            'is_active' => 'boolean',
        ]);

        $jabatan = Jabatan::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Jabatan berhasil dibuat.',
            'data' => $jabatan
        ], 201);
    }

    public function show($id)
    {
        $jabatan = Jabatan::with(['unitKerja', 'riwayatJabatan.pegawai'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $jabatan
        ]);
    }

    public function update(Request $request, $id)
    {
        if (!$request->user()->hasPermission('simpeg.jabatan.update') && !$request->user()->hasPermission('simpeg.jabatan.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk mengedit data Jabatan.'
            ], 403);
        }

        $jabatan = Jabatan::findOrFail($id);

        $request->validate([
            'unit_kerja_id' => 'nullable|exists:unit_kerja,id',
            'nama' => 'sometimes|string',
            'tipe' => 'sometimes|in:struktural,fungsional,teknis',
            'level_jabatan' => 'integer',
            'is_active' => 'boolean',
        ]);

        $jabatan->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Jabatan berhasil diperbarui.',
            'data' => $jabatan
        ]);
    }

    public function destroy(Request $request, $id)
    {
        if (!$request->user()->hasPermission('simpeg.jabatan.delete') && !$request->user()->hasPermission('simpeg.jabatan.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk menghapus data Jabatan.'
            ], 403);
        }

        $jabatan = Jabatan::findOrFail($id);
        $jabatan->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Jabatan berhasil dihapus.'
        ]);
    }
}

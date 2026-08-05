<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\UnitKas;
use Illuminate\Http\Request;

class UnitKasController extends Controller
{
    /**
     * Get all Unit Kas
     */
    public function index()
    {
        $unitKas = UnitKas::all();
        return response()->json([
            'status' => 'success',
            'data' => $unitKas
        ]);
    }

    /**
     * Store a newly created Unit Kas
     */
    public function store(Request $request)
    {
        $request->validate([
            'nama_kas' => 'required|string',
            'deskripsi' => 'nullable|string',
            'status' => 'nullable|boolean',
            'is_kabag_kas' => 'nullable|boolean',
            'bank_name' => 'nullable|string',
            'bank_account_name' => 'nullable|string',
            'bank_account_number' => 'nullable|string',
        ]);

        // Note: Adding support for extra fields if needed, or just keeping it simple
        $unitKas = UnitKas::create([
            'nama_kas' => $request->nama_kas,
            'deskripsi' => $request->deskripsi,
            'status' => $request->status ?? true,
            'is_kabag_kas' => $request->is_kabag_kas ?? false,
            // You can add logic for storing bank details in a related table if necessary
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Unit Kas berhasil ditambahkan',
            'data' => $unitKas
        ], 201);
    }

    /**
     * Update Unit Kas
     */
    public function update(Request $request, $id)
    {
        $unitKas = UnitKas::find($id);
        if (!$unitKas) {
            return response()->json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        $request->validate([
            'nama_kas' => 'required|string',
            'deskripsi' => 'nullable|string',
            'status' => 'required|boolean',
        ]);

        $unitKas->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Unit Kas berhasil diupdate',
            'data' => $unitKas
        ]);
    }

    /**
     * Delete Unit Kas
     */
    public function destroy($id)
    {
        $unitKas = UnitKas::find($id);
        if (!$unitKas) {
            return response()->json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        $unitKas->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Unit Kas berhasil dihapus'
        ]);
    }
}

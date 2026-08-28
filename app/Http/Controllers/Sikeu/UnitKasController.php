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
        $validated = $request->validate([
            'nama_kas' => 'required|string',
            'tipe_kas' => 'nullable|string',
            'deskripsi' => 'nullable|string',
            'status' => 'nullable|boolean',
            'is_kabag_kas' => 'nullable|boolean',
            'bank_name' => 'nullable|string',
            'bank_account_name' => 'nullable|string',
            'bank_account_number' => 'nullable|string',
            'penanggung_jawab' => 'nullable|string',
            'saldo_awal' => 'nullable|numeric',
            'saldo_saat_ini' => 'nullable|numeric',
        ]);

        $unitKas = UnitKas::create([
            'nama_kas' => $validated['nama_kas'],
            'tipe_kas' => $validated['tipe_kas'] ?? 'operasional',
            'deskripsi' => $validated['deskripsi'] ?? null,
            'status' => $validated['status'] ?? true,
            'is_kabag_kas' => $validated['is_kabag_kas'] ?? false,
            'bank_name' => $validated['bank_name'] ?? null,
            'bank_account_name' => $validated['bank_account_name'] ?? null,
            'bank_account_number' => $validated['bank_account_number'] ?? null,
            'penanggung_jawab' => $validated['penanggung_jawab'] ?? null,
            'saldo_awal' => $validated['saldo_awal'] ?? 0,
            'saldo_saat_ini' => $validated['saldo_saat_ini'] ?? ($validated['saldo_awal'] ?? 0),
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

        $validated = $request->validate([
            'nama_kas' => 'required|string',
            'tipe_kas' => 'nullable|string',
            'deskripsi' => 'nullable|string',
            'status' => 'nullable|boolean',
            'bank_name' => 'nullable|string',
            'bank_account_name' => 'nullable|string',
            'bank_account_number' => 'nullable|string',
            'penanggung_jawab' => 'nullable|string',
            'saldo_saat_ini' => 'nullable|numeric',
        ]);

        $unitKas->update($validated);

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

<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Models\Simpeg\JabatanFungsionalAkademik;
use Illuminate\Http\Request;

class JabatanFungsionalController extends Controller
{
    public function index()
    {
        return response()->json([
            'status' => 'success',
            'data' => JabatanFungsionalAkademik::all()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|unique:jabatan_fungsional_akademik,nama',
            'angka_kredit_min' => 'nullable|integer',
            'angka_kredit_max' => 'nullable|integer',
            'golongan' => 'required|in:asisten_ahli,lektor,lektor_kepala,guru_besar',
        ]);

        $jafung = JabatanFungsionalAkademik::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Jabatan Fungsional berhasil dibuat.',
            'data' => $jafung
        ], 201);
    }
}

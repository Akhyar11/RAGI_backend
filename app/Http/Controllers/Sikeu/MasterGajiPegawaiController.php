<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\MasterGajiPegawai;
use App\Models\Simpeg\Pegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MasterGajiPegawaiController extends Controller
{
    /**
     * GET /api/sikeu/master/gaji-pegawai
     * List all employees with their salary configuration
     */
    public function index(Request $request)
    {
        $pegawaiList = Pegawai::orderBy('nama_lengkap', 'asc')->get();

        $data = $pegawaiList->map(function ($p) {
            $master = MasterGajiPegawai::where('pegawai_id', $p->id)->first();
            return [
                'pegawai_id' => $p->id,
                'nama_lengkap' => $p->nama_lengkap,
                'nip' => $p->nip ?? '-',
                'jenis_pegawai' => $p->jenis_pegawai,
                'gaji_pokok' => $master ? (float)$master->gaji_pokok : 4500000,
                'tunjangan_tetap' => $master ? (float)$master->tunjangan_tetap : 1200000,
                'potongan_tetap' => $master ? (float)$master->potongan_tetap : 150000,
                'tarif_transport_harian' => $master ? (float)$master->tarif_transport_harian : 50000,
                'catatan' => $master?->catatan,
                'updated_at' => $master?->updated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * POST /api/sikeu/master/gaji-pegawai
     * Save or update salary configuration for an employee
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'gaji_pokok' => 'required|numeric|min:0',
            'tunjangan_tetap' => 'nullable|numeric|min:0',
            'potongan_tetap' => 'nullable|numeric|min:0',
            'tarif_transport_harian' => 'nullable|numeric|min:0',
            'catatan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi komponen gaji gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $master = MasterGajiPegawai::updateOrCreate(
            ['pegawai_id' => $request->pegawai_id],
            [
                'gaji_pokok' => $request->gaji_pokok,
                'tunjangan_tetap' => $request->tunjangan_tetap ?? 0,
                'potongan_tetap' => $request->potongan_tetap ?? 0,
                'tarif_transport_harian' => $request->tarif_transport_harian ?? 50000,
                'catatan' => $request->catatan,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Master komponen gaji pegawai berhasil disimpan.',
            'data' => $master,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\RiwayatJabatan;
use App\Models\Simpeg\RiwayatPendidikanPegawai;
use Illuminate\Http\Request;

class RiwayatController extends Controller
{
    // === RIWAYAT JABATAN ===
    public function getRiwayatJabatan($pegawaiId)
    {
        $pegawai = Pegawai::findOrFail($pegawaiId);
        $riwayat = $pegawai->riwayatJabatan()->with(['jabatan', 'jabatanFungsional'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $riwayat
        ]);
    }

    public function storeRiwayatJabatan(Request $request, $pegawaiId)
    {
        $pegawai = Pegawai::findOrFail($pegawaiId);

        $request->validate([
            'jabatan_id' => 'nullable|exists:jabatan,id',
            'jabatan_fungsional_id' => 'nullable|exists:jabatan_fungsional_akademik,id',
            'mulai_jabatan' => 'required|date',
            'selesai_jabatan' => 'nullable|date',
            'sk_nomor' => 'nullable|string',
            'sk_tanggal' => 'nullable|date',
            'file_sk' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $riwayat = $pegawai->riwayatJabatan()->create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Riwayat Jabatan berhasil ditambahkan.',
            'data' => $riwayat
        ], 201);
    }

    // === RIWAYAT PENDIDIKAN ===
    public function getRiwayatPendidikan($pegawaiId)
    {
        $pegawai = Pegawai::findOrFail($pegawaiId);
        $riwayat = $pegawai->riwayatPendidikan()->get();

        return response()->json([
            'status' => 'success',
            'data' => $riwayat
        ]);
    }

    public function storeRiwayatPendidikan(Request $request, $pegawaiId)
    {
        $pegawai = Pegawai::findOrFail($pegawaiId);

        $request->validate([
            'jenjang' => 'required|in:sma,d3,d4,s1,s2,s3',
            'nama_institusi' => 'required|string',
            'program_studi' => 'nullable|string',
            'bidang_ilmu' => 'nullable|string',
            'tahun_masuk' => 'nullable|integer',
            'tahun_lulus' => 'nullable|integer',
            'nomor_ijazah' => 'nullable|string',
            'file_ijazah' => 'nullable|string',
            'is_pendidikan_terakhir' => 'boolean',
        ]);

        $riwayat = $pegawai->riwayatPendidikan()->create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Riwayat Pendidikan berhasil ditambahkan.',
            'data' => $riwayat
        ], 201);
    }
}

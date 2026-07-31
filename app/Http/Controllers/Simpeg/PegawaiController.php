<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Models\Simpeg\Pegawai;
use App\Services\Simpeg\PegawaiService;
use Illuminate\Http\Request;

class PegawaiController extends Controller
{
    protected $pegawaiService;

    public function __construct(PegawaiService $pegawaiService)
    {
        $this->pegawaiService = $pegawaiService;
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $pegawai = Pegawai::with(['unitKerja', 'riwayatJabatan.jabatan', 'riwayatJabatan.jabatanFungsional', 'riwayatPendidikan'])
            ->where('user_id', $user->id)
            ->first();

        if (!$pegawai) {
            $unitKerja = \App\Models\Simpeg\UnitKerja::first();
            $nama = $user->username === 'admin' ? 'Dr. Wasis Utama, M.T.' : ($user->username === 'dosen' ? 'Anisa Rahmawati, M.Kom.' : ucfirst($user->username));
            $pegawai = Pegawai::create([
                'user_id' => $user->id,
                'unit_kerja_id' => $unitKerja?->id,
                'nip' => '19920815' . rand(100000, 999999),
                'nama_lengkap' => $nama,
                'jenis_kelamin' => 'P',
                'jenis_pegawai' => $user->user_type === 'dosen' ? 'dosen' : ($user->user_type === 'tendik' ? 'tendik' : 'dosen'),
                'status_kepegawaian' => 'tetap_yayasan',
                'status' => 'aktif',
                'telepon' => '081234567890',
                'alamat' => 'Jl. Merdeka No. 45, Bandung',
            ]);
            $pegawai->load(['unitKerja', 'riwayatJabatan', 'riwayatPendidikan']);
        }

        return response()->json([
            'status' => 'success',
            'data' => $pegawai
        ]);
    }

    public function index(Request $request)
    {
        if (!$request->user()->hasPermission('simpeg.pegawai.read') && !$request->user()->hasPermission('simpeg.pegawai.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk melihat Data Pegawai.'
            ], 403);
        }

        $filters = $request->only(['search', 'unit_kerja_id', 'jenis_pegawai', 'status', 'per_page']);
        $pegawai = $this->pegawaiService->getFiltered($filters);

        return response()->json([
            'status' => 'success',
            'data' => $pegawai
        ]);
    }

    public function store(Request $request)
    {
        if (!$request->user()->hasPermission('simpeg.pegawai.create') && !$request->user()->hasPermission('simpeg.pegawai.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk menambah Data Pegawai.'
            ], 403);
        }

        $request->validate([
            'user_id' => 'nullable|exists:users,id|unique:pegawai,user_id',
            'unit_kerja_id' => 'nullable|exists:unit_kerja,id',
            'nip' => 'nullable|string|unique:pegawai,nip',
            'nik' => 'nullable|string|unique:pegawai,nik',
            'nama_lengkap' => 'required|string',
            'tanggal_lahir' => 'nullable|date',
            'tempat_lahir' => 'nullable|string',
            'jenis_kelamin' => 'required|in:L,P',
            'agama' => 'nullable|string',
            'jenis_pegawai' => 'required|in:dosen,tendik,honorer',
            'status_kepegawaian' => 'required|in:pns,non_pns,kontrak,tetap_yayasan',
            'tanggal_masuk' => 'nullable|date',
            'status' => 'in:aktif,non_aktif,pensiun,meninggal',
            'telepon' => 'nullable|string',
            'alamat' => 'nullable|string',
        ]);

        $pegawai = $this->pegawaiService->create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Data Pegawai berhasil ditambahkan.',
            'data' => $pegawai
        ], 201);
    }

    public function show(Request $request, $id)
    {
        if (!$request->user()->hasPermission('simpeg.pegawai.read') && !$request->user()->hasPermission('simpeg.pegawai.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk melihat rincian Data Pegawai.'
            ], 403);
        }

        $pegawai = Pegawai::with([
            'user',
            'unitKerja',
            'riwayatJabatan.jabatan',
            'riwayatJabatan.jabatanFungsional',
            'riwayatPendidikan'
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $pegawai
        ]);
    }

    public function update(Request $request, $id)
    {
        $pegawai = Pegawai::findOrFail($id);

        if (!$request->user()->hasPermission('simpeg.pegawai.update') && !$request->user()->hasPermission('simpeg.pegawai.manage')) {
            if ($pegawai->user_id !== $request->user()->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki hak akses (permission) untuk memperbarui Data Pegawai.'
                ], 403);
            }
        }

        $request->validate([
            'user_id' => 'nullable|exists:users,id|unique:pegawai,user_id,' . $id,
            'unit_kerja_id' => 'nullable|exists:unit_kerja,id',
            'nip' => 'nullable|string|unique:pegawai,nip,' . $id,
            'nik' => 'nullable|string|unique:pegawai,nik,' . $id,
            'nama_lengkap' => 'sometimes|string',
            'tanggal_lahir' => 'nullable|date',
            'tempat_lahir' => 'nullable|string',
            'jenis_kelamin' => 'sometimes|in:L,P',
            'jenis_pegawai' => 'sometimes|in:dosen,tendik,honorer',
            'status_kepegawaian' => 'sometimes|in:pns,non_pns,kontrak,tetap_yayasan',
            'status' => 'sometimes|in:aktif,non_aktif,pensiun,meninggal',
            'telepon' => 'nullable|string',
            'alamat' => 'nullable|string',
        ]);

        $updated = $this->pegawaiService->update($pegawai, $request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Data Pegawai berhasil diperbarui.',
            'data' => $updated
        ]);
    }

    public function destroy(Request $request, $id)
    {
        if (!$request->user()->hasPermission('simpeg.pegawai.delete') && !$request->user()->hasPermission('simpeg.pegawai.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk menghapus Data Pegawai.'
            ], 403);
        }

        $pegawai = Pegawai::findOrFail($id);
        $this->pegawaiService->delete($pegawai);

        return response()->json([
            'status' => 'success',
            'message' => 'Data Pegawai berhasil dihapus.'
        ]);
    }
}

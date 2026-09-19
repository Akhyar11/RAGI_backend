<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\DetailTagihan;
use App\Models\Sikeu\MasterBiaya;
use App\Models\Sikeu\SettingTarif;
use App\Models\Spmb\MasterProgramStudi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PembayaranMahasiswaTarifController extends Controller
{
    /**
     * GET /api/v1/sikeu/pembayaran-mahasiswa/tarif
     * List pengaturan tarif komponen biaya berdasarkan prodi dan angkatan.
     */
    public function index(Request $request)
    {
        $perPage = min(100, $request->integer('per_page', 15));
        $query = SettingTarif::with(['masterBiaya', 'programStudi']);

        // Filter Komponen Biaya (Katalog Biaya)
        if ($request->filled('master_biaya_id')) {
            $query->where('master_biaya_id', $request->master_biaya_id);
        }

        // Filter Tahun Angkatan
        if ($request->filled('tahun_angkatan')) {
            $query->where('tahun_angkatan', $request->tahun_angkatan);
        }

        // Filter Program Studi
        if ($request->has('program_studi_id') && $request->program_studi_id !== '' && $request->program_studi_id !== 'all') {
            if ($request->program_studi_id === 'global' || $request->program_studi_id === 'null') {
                $query->whereNull('program_studi_id');
            } else {
                $query->where('program_studi_id', $request->program_studi_id);
            }
        }

        // Filter Status Aktif
        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Filter Pencarian (Nama/Kode Biaya atau Keterangan)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('keterangan', 'like', "%{$search}%")
                  ->orWhereHas('masterBiaya', function ($mb) use ($search) {
                      $mb->where('nama', 'like', "%{$search}%")
                         ->orWhere('kode', 'like', "%{$search}%");
                  })
                  ->orWhereHas('programStudi', function ($ps) use ($search) {
                      $ps->where('nama', 'like', "%{$search}%")
                         ->orWhere('kode_prodi', 'like', "%{$search}%");
                  });
            });
        }

        // Pengurutan (Sorting)
        $allowedSortColumns = ['id', 'created_at', 'tahun_angkatan', 'nominal'];
        $sortBy = in_array($request->sort_by, $allowedSortColumns) ? $request->sort_by : 'id';
        $sortOrder = strtolower($request->sort_order) === 'asc' ? 'asc' : 'desc';

        if ($request->sort_by === 'komponen') {
            $query->join('sikeu_master_biaya', 'sikeu_setting_tarif.master_biaya_id', '=', 'sikeu_master_biaya.id')
                  ->orderBy('sikeu_master_biaya.nama', $sortOrder)
                  ->select('sikeu_setting_tarif.*');
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar tarif komponen biaya berhasil dimuat',
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
            'filters' => [
                'master_biaya_id' => $request->master_biaya_id,
                'tahun_angkatan' => $request->tahun_angkatan,
                'program_studi_id' => $request->program_studi_id,
                'is_active' => $request->is_active,
                'search' => $request->search,
            ],
        ]);
    }

    /**
     * POST /api/v1/sikeu/pembayaran-mahasiswa/tarif
     * Menambahkan tarif komponen biaya baru berdasarkan prodi & angkatan.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'master_biaya_id' => 'required|integer|exists:sikeu_master_biaya,id',
            'tahun_angkatan' => 'required|integer|min:2000|max:2050',
            'program_studi_id' => 'nullable|integer|exists:spmb_master_program_studi,id',
            'nominal' => 'required|numeric|min:0',
            'keterangan' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi tarif gagal diproses.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $prodiId = $request->filled('program_studi_id') ? (int)$request->program_studi_id : null;
        $tahunAngkatan = (int)$request->tahun_angkatan;
        $masterBiayaId = (int)$request->master_biaya_id;

        // Cek apakah kombinasi komponen biaya + angkatan + prodi sudah ada
        $existsQuery = SettingTarif::where('master_biaya_id', $masterBiayaId)
            ->where('tahun_angkatan', $tahunAngkatan)
            ->where('jalur_kelas', 'Reguler');

        if ($prodiId === null) {
            $existsQuery->whereNull('program_studi_id');
        } else {
            $existsQuery->where('program_studi_id', $prodiId);
        }

        if ($existsQuery->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tarif untuk komponen biaya ini pada tahun angkatan dan program studi yang dipilih sudah terdaftar.',
            ], 422);
        }

        $item = SettingTarif::create([
            'master_biaya_id' => $masterBiayaId,
            'tahun_angkatan' => $tahunAngkatan,
            'program_studi_id' => $prodiId,
            'semester' => null, // Berlaku umum / tahunan sesuai komponen
            'jalur_kelas' => 'Reguler', // Default background agar kompatibel
            'nominal' => $request->nominal,
            'is_active' => $request->boolean('is_active', true),
            'keterangan' => $request->keterangan,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Tarif komponen biaya berhasil ditambahkan.',
            'data' => $item->load(['masterBiaya', 'programStudi']),
        ], 201);
    }

    /**
     * GET /api/v1/sikeu/pembayaran-mahasiswa/tarif/{id}
     * Menampilkan detail tarif komponen biaya.
     */
    public function show($id)
    {
        $item = SettingTarif::with(['masterBiaya', 'programStudi'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail tarif berhasil dimuat',
            'data' => $item,
        ]);
    }

    /**
     * PUT /api/v1/sikeu/pembayaran-mahasiswa/tarif/{id}
     * Memperbarui tarif komponen biaya.
     */
    public function update(Request $request, $id)
    {
        $item = SettingTarif::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'master_biaya_id' => 'sometimes|required|integer|exists:sikeu_master_biaya,id',
            'tahun_angkatan' => 'sometimes|required|integer|min:2000|max:2050',
            'program_studi_id' => 'nullable|integer|exists:spmb_master_program_studi,id',
            'nominal' => 'sometimes|required|numeric|min:0',
            'keterangan' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi perubahan tarif gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $newBiayaId = $request->filled('master_biaya_id') ? (int)$request->master_biaya_id : $item->master_biaya_id;
        $newAngkatan = $request->filled('tahun_angkatan') ? (int)$request->tahun_angkatan : $item->tahun_angkatan;
        $newProdiId = $request->has('program_studi_id')
            ? ($request->filled('program_studi_id') ? (int)$request->program_studi_id : null)
            : $item->program_studi_id;

        // Cek duplikasi jika kombinasi berubah
        if ($newBiayaId !== $item->master_biaya_id || $newAngkatan !== $item->tahun_angkatan || $newProdiId !== $item->program_studi_id) {
            $duplicateQuery = SettingTarif::where('id', '!=', $item->id)
                ->where('master_biaya_id', $newBiayaId)
                ->where('tahun_angkatan', $newAngkatan)
                ->where('jalur_kelas', 'Reguler');

            if ($newProdiId === null) {
                $duplicateQuery->whereNull('program_studi_id');
            } else {
                $duplicateQuery->where('program_studi_id', $newProdiId);
            }

            if ($duplicateQuery->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Kombinasi tarif untuk komponen biaya, angkatan, dan program studi ini sudah ada pada data lain.',
                ], 422);
            }
        }

        $updateData = [];
        if ($request->filled('master_biaya_id')) $updateData['master_biaya_id'] = $newBiayaId;
        if ($request->filled('tahun_angkatan')) $updateData['tahun_angkatan'] = $newAngkatan;
        if ($request->has('program_studi_id')) $updateData['program_studi_id'] = $newProdiId;
        if ($request->has('nominal')) $updateData['nominal'] = $request->nominal;
        if ($request->has('is_active')) $updateData['is_active'] = $request->boolean('is_active');
        if ($request->has('keterangan')) $updateData['keterangan'] = $request->keterangan;

        $item->update($updateData);

        return response()->json([
            'status' => 'success',
            'message' => 'Tarif komponen biaya berhasil diperbarui.',
            'data' => $item->load(['masterBiaya', 'programStudi']),
        ]);
    }

    /**
     * DELETE /api/v1/sikeu/pembayaran-mahasiswa/tarif/{id}
     * Menghapus tarif komponen biaya dengan proteksi integritas.
     */
    public function destroy($id)
    {
        $item = SettingTarif::findOrFail($id);

        // Proteksi: Cek apakah komponen biaya ini sudah pernah digunakan pada detail tagihan mahasiswa
        $isUsed = DetailTagihan::where('master_biaya_id', $item->master_biaya_id)
            ->whereHas('tagihan', function ($q) use ($item) {
                if ($item->program_studi_id) {
                    $q->whereHas('mahasiswa', function ($m) use ($item) {
                        $m->where('program_studi_id', $item->program_studi_id);
                    });
                }
            })
            ->exists();

        if ($isUsed) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tarif tidak dapat dihapus karena komponen biaya ini sudah pernah diterbitkan pada tagihan mahasiswa. Anda dapat menonaktifkan status tarif tersebut sebagai alternatif.',
            ], 422);
        }

        $item->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Tarif komponen biaya berhasil dihapus.',
        ]);
    }

    /**
     * GET /api/v1/sikeu/pembayaran-mahasiswa/katalog-biaya
     * Mengambil katalog komponen biaya aktif untuk opsi dropdown.
     */
    public function katalogBiaya()
    {
        $biaya = MasterBiaya::where('is_active', true)
            ->orderBy('kode', 'asc')
            ->get(['id', 'kode', 'nama', 'tipe', 'nominal_standar', 'is_recurring']);

        return response()->json([
            'status' => 'success',
            'data' => $biaya,
        ]);
    }

    /**
     * GET /api/v1/sikeu/pembayaran-mahasiswa/prodi-list
     * Mengambil daftar program studi aktif.
     */
    public function prodiList()
    {
        $prodi = MasterProgramStudi::where('is_active', true)
            ->orderBy('nama', 'asc')
            ->get(['id', 'kode_prodi', 'nama', 'jenjang']);

        return response()->json([
            'status' => 'success',
            'data' => $prodi,
        ]);
    }

    /**
     * GET /api/v1/sikeu/pembayaran-mahasiswa/summary
     * Mengambil ringkasan statistik tarif untuk dashboard / card info.
     */
    public function summary()
    {
        $totalTarif = SettingTarif::count();
        $totalAktif = SettingTarif::where('is_active', true)->count();
        $totalKomponenDikonfigurasi = SettingTarif::distinct('master_biaya_id')->count('master_biaya_id');
        $totalKatalog = MasterBiaya::where('is_active', true)->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_tarif' => $totalTarif,
                'total_aktif' => $totalAktif,
                'total_komponen_dikonfigurasi' => $totalKomponenDikonfigurasi,
                'total_katalog_biaya' => $totalKatalog,
            ],
        ]);
    }
}

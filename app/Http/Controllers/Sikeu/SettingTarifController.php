<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\SettingTarif;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SettingTarifController extends Controller
{
    /**
     * GET /api/v1/sikeu/master/setting-tarif
     * List setting tarif with filters & pagination.
     */
    public function index(Request $request)
    {
        $perPage = min(100, $request->integer('per_page', 15));
        $query = SettingTarif::with(['masterBiaya', 'programStudi']);

        // Filter Tahun Angkatan
        if ($request->filled('tahun_angkatan')) {
            $query->where('tahun_angkatan', $request->tahun_angkatan);
        }

        // Filter Program Studi (dukung include_global / null fallback)
        if ($request->filled('program_studi_id')) {
            $prodiId = $request->program_studi_id;
            if ($request->boolean('include_global', false)) {
                $query->where(function ($q) use ($prodiId) {
                    $q->where('program_studi_id', $prodiId)
                      ->orWhereNull('program_studi_id');
                });
            } else {
                $query->where('program_studi_id', $prodiId);
            }
        }

        // Filter Semester (dukung include_all_semester / null fallback)
        if ($request->filled('semester')) {
            $sem = $request->semester;
            if ($request->boolean('include_global', false)) {
                $query->where(function ($q) use ($sem) {
                    $q->where('semester', $sem)
                      ->orWhereNull('semester');
                });
            } else {
                $query->where('semester', $sem);
            }
        }

        // Filter Jalur Kelas
        if ($request->filled('jalur_kelas')) {
            $query->where('jalur_kelas', $request->jalur_kelas);
        }

        // Filter aktif
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Filter Search (by keterangan atau nama master biaya)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('keterangan', 'like', "%{$search}%")
                  ->orWhereHas('masterBiaya', function ($mb) use ($search) {
                      $mb->where('nama', 'like', "%{$search}%")
                         ->orWhere('kode', 'like', "%{$search}%");
                  });
            });
        }

        // Sorting
        $allowedSortColumns = ['id', 'created_at', 'tahun_angkatan', 'semester', 'nominal', 'jalur_kelas'];
        $sortBy = in_array($request->sort_by, $allowedSortColumns) ? $request->sort_by : 'id';
        $sortOrder = strtolower($request->sort_order) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data setting tarif berhasil dimuat',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
            'filters' => [
                'tahun_angkatan' => $request->tahun_angkatan,
                'program_studi_id' => $request->program_studi_id,
                'semester' => $request->semester,
                'jalur_kelas' => $request->jalur_kelas,
                'search' => $request->search,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * POST /api/v1/sikeu/master/setting-tarif
     * Create new setting tarif.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'master_biaya_id' => 'required|exists:sikeu_master_biaya,id',
            'tahun_angkatan' => 'required|integer|min:2020|max:2040',
            'program_studi_id' => 'nullable|integer',
            'semester' => 'nullable|integer|min:1|max:14',
            'jalur_kelas' => 'required|string|max:50',
            'nominal' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'keterangan' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi setting tarif gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check unique combination
        $exists = SettingTarif::where('master_biaya_id', $request->master_biaya_id)
            ->where('tahun_angkatan', $request->tahun_angkatan)
            ->where('program_studi_id', $request->program_studi_id)
            ->where('semester', $request->semester)
            ->where('jalur_kelas', $request->jalur_kelas)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kombinasi tarif (Jenis Biaya + Angkatan + Prodi + Semester + Jalur Kelas) sudah ada.',
            ], 422);
        }

        $setting = SettingTarif::create([
            'master_biaya_id' => $request->master_biaya_id,
            'tahun_angkatan' => $request->tahun_angkatan,
            'program_studi_id' => $request->program_studi_id,
            'semester' => $request->semester,
            'jalur_kelas' => $request->jalur_kelas,
            'nominal' => $request->nominal,
            'is_active' => $request->boolean('is_active', true),
            'keterangan' => $request->keterangan,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Setting tarif berhasil ditambahkan.',
            'data' => $setting->load(['masterBiaya', 'programStudi']),
        ], 201);
    }

    /**
     * PUT /api/v1/sikeu/master/setting-tarif/{id}
     * Update existing setting tarif.
     */
    public function update(Request $request, $id)
    {
        $setting = SettingTarif::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'master_biaya_id' => 'sometimes|exists:sikeu_master_biaya,id',
            'tahun_angkatan' => 'sometimes|integer|min:2020|max:2040',
            'program_studi_id' => 'nullable|integer',
            'semester' => 'nullable|integer|min:1|max:14',
            'jalur_kelas' => 'sometimes|string|max:50',
            'nominal' => 'sometimes|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'keterangan' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi setting tarif gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $setting->update($request->only([
            'master_biaya_id', 'tahun_angkatan', 'program_studi_id',
            'semester', 'jalur_kelas', 'nominal', 'is_active', 'keterangan',
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Setting tarif berhasil diperbarui.',
            'data' => $setting->load(['masterBiaya', 'programStudi']),
        ]);
    }

    /**
     * DELETE /api/v1/sikeu/master/setting-tarif/{id}
     * Delete a setting tarif.
     */
    public function destroy($id)
    {
        $setting = SettingTarif::findOrFail($id);
        $setting->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Setting tarif berhasil dihapus.',
        ]);
    }

    /**
     * GET /api/v1/sikeu/master/program-studi
     * Get reference list of active Study Programs from database for tariff setting dropdown.
     */
    public function getProgramStudiList(Request $request)
    {
        $prodis = \App\Models\Siakad\ProgramStudi::orderBy('nama', 'asc')->get([
            'id', 'kode_prodi', 'nama', 'jenjang', 'is_active'
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $prodis
        ]);
    }
}

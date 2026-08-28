<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\TarifSpmb;
use App\Models\Sikeu\MasterBiaya;
use App\Models\Sikeu\MasterBiayaModule;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\DispensasiTagihan;
use App\Services\Sikeu\SpmbSikeuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SikeuMasterController extends Controller
{




    // ==========================================
    // 3. MASTER JENIS BIAYA PENDIDIKAN
    // ==========================================

    public function indexMasterBiaya(Request $request)
    {
        $query = MasterBiaya::with('moduleDelegations');

        if ($request->filled('module_id')) {
            $mod = \App\Models\Module::find($request->module_id);
            if ($mod) {
                $moduleCode = $mod->code;
                $query->whereHas('moduleDelegations', function ($q) use ($moduleCode) {
                    $q->where('module_code', $moduleCode);
                });
            }
        } else {
            $moduleCode = $request->input('module_code', $request->input('module'));
            if ($moduleCode) {
                $query->whereHas('moduleDelegations', function ($q) use ($moduleCode) {
                    $q->where('module_code', $moduleCode);
                });
            }
        }

        $biaya = $query->orderBy('id', 'asc')->get();
        return response()->json(['status' => 'success', 'data' => $biaya]);
    }

    public function storeMasterBiaya(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kode' => 'required|string|unique:sikeu_master_biaya,kode',
            'nama' => 'required|string',
            'tipe' => 'required|string',
            'nominal_standar' => 'nullable|numeric|min:0',
            'deskripsi' => 'nullable|string',
            'module_codes' => 'nullable|array',
            'module_codes.*' => 'string',
            'module_ids' => 'nullable|array',
            'module_ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $biaya = MasterBiaya::create([
            'kode' => strtoupper($request->kode),
            'nama' => $request->nama,
            'tipe' => $request->tipe,
            'nominal_standar' => $request->nominal_standar ?? 0,
            'deskripsi' => $request->deskripsi,
            'is_recurring' => true,
            'is_active' => true,
        ]);

        $moduleCodes = [];
        if ($request->has('module_ids') && is_array($request->module_ids)) {
            $moduleCodes = \App\Models\Module::whereIn('id', $request->module_ids)->pluck('code')->toArray();
        } elseif ($request->has('module_codes') && is_array($request->module_codes)) {
            $moduleCodes = $request->module_codes;
        } else {
            $moduleCodes = ['sikeu'];
        }

        if (!empty($moduleCodes) && is_array($moduleCodes)) {
            foreach ($moduleCodes as $code) {
                MasterBiayaModule::create([
                    'master_biaya_id' => $biaya->id,
                    'module_code' => strtolower($code),
                ]);
            }
        }

        return response()->json(['status' => 'success', 'message' => 'Master biaya berhasil ditambahkan.', 'data' => $biaya->load('moduleDelegations')], 201);
    }

    public function updateMasterBiaya(Request $request, $id)
    {
        $biaya = MasterBiaya::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nama' => 'sometimes|string',
            'tipe' => 'sometimes|string',
            'nominal_standar' => 'sometimes|numeric|min:0',
            'deskripsi' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'module_codes' => 'nullable|array',
            'module_codes.*' => 'string',
            'module_ids' => 'nullable|array',
            'module_ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $biaya->update($request->only(['nama', 'tipe', 'nominal_standar', 'deskripsi', 'is_active', 'is_recurring']));

        $moduleCodes = null;
        if ($request->has('module_ids') && is_array($request->module_ids)) {
            $moduleCodes = \App\Models\Module::whereIn('id', $request->module_ids)->pluck('code')->toArray();
        } elseif ($request->has('module_codes') && is_array($request->module_codes)) {
            $moduleCodes = $request->module_codes;
        }

        if ($moduleCodes !== null && is_array($moduleCodes)) {
            MasterBiayaModule::where('master_biaya_id', $biaya->id)->delete();
            foreach ($moduleCodes as $code) {
                MasterBiayaModule::create([
                    'master_biaya_id' => $biaya->id,
                    'module_code' => strtolower($code),
                ]);
            }
        }

        return response()->json(['status' => 'success', 'message' => 'Master biaya berhasil diperbarui.', 'data' => $biaya->load('moduleDelegations')]);
    }
    public function destroyMasterBiaya($id)
    {
        $biaya = MasterBiaya::findOrFail($id);
        
        // Prevent deletion if used in tags/billing
        $hasUsage = \App\Models\Sikeu\TagihanMahasiswaDetail::where('master_biaya_id', $id)->exists();
        if ($hasUsage) {
            return response()->json(['status' => 'error', 'message' => 'Master biaya tidak dapat dihapus karena sudah digunakan dalam tagihan.'], 400);
        }

        MasterBiayaModule::where('master_biaya_id', $id)->delete();
        $biaya->delete();

        return response()->json(['status' => 'success', 'message' => 'Master biaya berhasil dihapus.']);
    }


    // ==========================================
    // 5. PENETAPAN TIPE TAGIHAN MAHASISWA & INTEGRASI SPMB/SIAKAD
    // ==========================================



    // ==========================================
    // 6. SEARCH MAHASISWA SEARCH & TAGIHAN LOOKUP
    // ==========================================

    public function searchMahasiswa(Request $request)
    {
        $search = $request->query('q', '');

        $queryBuilder = TagihanMahasiswa::with(['details.jenisBiaya', 'dispensasis']);

        if (!empty($search)) {
            $queryBuilder->where(function ($q) use ($search) {
                $q->where('nomor_tagihan', 'like', "%{$search}%")
                  ->orWhere('mahasiswa_id', 'like', "%{$search}%");
            });
        }

        $tagihans = $queryBuilder->limit(10)->get();

        $results = $tagihans->map(function ($t) {
            $hasUnpaidDispensation = DispensasiTagihan::where('mahasiswa_id', $t->mahasiswa_id)
                ->where('status', 'approved')
                ->whereHas('tagihan', function($q) {
                    $q->whereIn('status', ['belum_bayar', 'sebagian', 'dispensasi']);
                })
                ->exists();

            return [
                'tagihan_id' => $t->id,
                'nomor_tagihan' => $t->nomor_tagihan,
                'mahasiswa_id' => $t->mahasiswa_id,
                'nama_mahasiswa' => 'Mahasiswa #' . $t->mahasiswa_id . ' (NIM: 2024' . str_pad($t->mahasiswa_id, 4, '0', STR_PAD_LEFT) . ')',
                'nim' => '2024' . str_pad($t->mahasiswa_id, 4, '0', STR_PAD_LEFT),
                'prodi' => 'Teknik Informatika',
                'tahun_angkatan' => 2024,
                'jalur_kelas' => 'Reguler',
                'total_tagihan' => (float)$t->total_tagihan,
                'total_bayar' => (float)$t->total_bayar,
                'sisa_tagihan' => (float)($t->total_tagihan - $t->total_bayar),
                'status' => $t->status,
                'has_unpaid_previous_dispensation' => $hasUnpaidDispensation,
                'details' => $t->details->map(function ($d) {
                    return [
                        'detail_id' => $d->id,
                        'master_biaya' => $d->masterBiaya->nama ?? 'Biaya Pendidikan',
                        'nominal' => (float)$d->nominal,
                        'potongan' => (float)$d->potongan,
                        'nominal_bersih' => (float)$d->nominal_bersih,
                    ];
                }),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $results
        ]);
    }

    // ==========================================
    // 8. MASTER TARIF SPMB & INTEGRASI GET TARIF
    // ==========================================

    /**
     * GET /api/v1/sikeu/master/tarif-spmb
     * List master tarif SPMB berdasarkan jalur & gelombang
     */
    public function indexTarifSpmb(Request $request)
    {
        $query = TarifSpmb::with('masterBiaya');

        if ($request->filled('jalur_id')) {
            $query->where('jalur_id', $request->jalur_id);
        }

        if ($request->filled('gelombang_id')) {
            $query->where('gelombang_id', $request->gelombang_id);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $tarif = $query->orderBy('jalur_id', 'asc')
            ->orderBy('gelombang_id', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $tarif
        ]);
    }

    /**
     * POST /api/v1/sikeu/master/tarif-spmb
     * Store new Tarif SPMB
     */
    public function storeTarifSpmb(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'master_biaya_id' => 'nullable|exists:sikeu_master_biaya,id',
            'jalur_id' => 'required|string|max:50',
            'gelombang_id' => 'required|string|max:50',
            'nominal' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Default master_biaya_id to first available MasterBiaya if null
        $masterBiayaId = $request->master_biaya_id;
        if (!$masterBiayaId) {
            $masterBiayaDefault = MasterBiaya::where('tipe', 'spmb_adm')->first() ?? MasterBiaya::first();
            $masterBiayaId = $masterBiayaDefault?->id;
        }

        $tarif = TarifSpmb::create([
            'master_biaya_id' => $masterBiayaId,
            'jalur_id' => $request->jalur_id,
            'gelombang_id' => $request->gelombang_id,
            'nominal' => $request->nominal,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Tarif SPMB berhasil ditambahkan.',
            'data' => $tarif->load('masterBiaya')
        ], 201);
    }

    /**
     * PUT /api/v1/sikeu/master/tarif-spmb/{id}
     * Update Tarif SPMB
     */
    public function updateTarifSpmb(Request $request, $id)
    {
        $tarif = TarifSpmb::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'master_biaya_id' => 'nullable|exists:sikeu_master_biaya,id',
            'jalur_id' => 'sometimes|required|string|max:50',
            'gelombang_id' => 'sometimes|required|string|max:50',
            'nominal' => 'sometimes|required|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $tarif->update($request->only([
            'master_biaya_id',
            'jalur_id',
            'gelombang_id',
            'nominal',
            'is_active',
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Tarif SPMB berhasil diperbarui.',
            'data' => $tarif->load('masterBiaya')
        ]);
    }

    /**
     * DELETE /api/v1/sikeu/master/tarif-spmb/{id}
     * Delete Tarif SPMB
     */
    public function destroyTarifSpmb($id)
    {
        $tarif = TarifSpmb::findOrFail($id);
        $tarif->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Tarif SPMB berhasil dihapus.'
        ]);
    }

    /**
     * GET /api/v1/sikeu/spmb/tarif
     * Service Endpoint untuk SPMB mengambil nominal pendaftaran secara real-time
     */
    public function getTarifSpmb(Request $request, SpmbSikeuService $service)
    {
        $validator = Validator::make($request->all(), [
            'jalur_id' => 'required',
            'gelombang_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter jalur_id dan gelombang_id wajib diisi',
                'errors' => $validator->errors()
            ], 422);
        }

        $nominal = $service->getTarifPendaftaranSpmb($request->jalur_id, $request->gelombang_id);

        return response()->json([
            'status' => 'success',
            'data' => [
                'jalur_id' => $request->jalur_id,
                'gelombang_id' => $request->gelombang_id,
                'nominal' => $nominal,
            ]
        ]);
    }

    // ==========================================
    // 9. STUDENT BILLING TYPES & CATEGORIES
    // ==========================================

    public function getStudentBillingCategories(Request $request)
    {
        $categories = [
            ['id' => 1, 'nama' => 'UKT Reguler', 'kode' => 'UKT_REG'],
            ['id' => 2, 'nama' => 'UKT Eksekutif / Karyawan', 'kode' => 'UKT_EKS'],
            ['id' => 3, 'nama' => 'UKT Internasional', 'kode' => 'UKT_INT'],
        ];

        return response()->json([
            'status' => 'success',
            'data' => $categories
        ]);
    }

    public function indexStudentBillingTypes(Request $request)
    {
        $perPage = min(100, $request->integer('per_page', 15));
        $query = \App\Models\Sikeu\MahasiswaTipeTagihan::query();

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where('nama_mahasiswa', 'like', "%{$search}%")
                  ->orWhere('nim', 'like', "%{$search}%");
        }

        $data = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
            ]
        ]);
    }

    public function assignStudentBillingType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mahasiswa_id' => 'required|integer',
            'tahun_angkatan' => 'required|integer',
            'jalur_kelas' => 'required|string',
            'kelompok_ukt' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $item = \App\Models\Sikeu\MahasiswaTipeTagihan::updateOrCreate(
            ['mahasiswa_id' => $request->mahasiswa_id],
            [
                'nim' => $request->nim ?? ('NIM-' . $request->mahasiswa_id),
                'nama_mahasiswa' => $request->nama_mahasiswa ?? ('Mahasiswa #' . $request->mahasiswa_id),
                'tahun_angkatan' => $request->tahun_angkatan,
                'jalur_kelas' => $request->jalur_kelas,
                'kelompok_ukt' => $request->kelompok_ukt,
                'catatan_perubahan' => $request->catatan_perubahan ?? 'Penetapan tipe tagihan',
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Tipe tagihan mahasiswa berhasil ditetapkan',
            'data' => $item
        ]);
    }

    public function updateStudentBillingType(Request $request, $id)
    {
        $item = \App\Models\Sikeu\MahasiswaTipeTagihan::findOrFail($id);
        $item->update($request->only([
            'jalur_kelas',
            'kelompok_ukt',
            'catatan_perubahan'
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Tipe tagihan mahasiswa berhasil diperbarui',
            'data' => $item
        ]);
    }
}

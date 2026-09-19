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
        $hasUsage = \App\Models\Sikeu\DetailTagihan::where('master_biaya_id', $id)->exists();
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
    // 6. SEARCH MAHASISWA & PENGATURAN UKT
    // ==========================================

    public function getUktSetting()
    {
        $setting = \App\Models\SystemSetting::where('key', 'sikeu_enable_golongan_ukt')->first();
        $enabled = $setting ? filter_var($setting->value, FILTER_VALIDATE_BOOLEAN) : true;

        return response()->json([
            'status' => 'success',
            'data' => [
                'enabled' => $enabled,
                'description' => 'Status aktifasi skema Golongan UKT (I - VIII) vs Flat Tarif Matriks Semester',
            ]
        ]);
    }

    public function updateUktSetting(Request $request)
    {
        $enabled = $request->boolean('enabled', true);
        \App\Models\SystemSetting::updateOrCreate(
            ['key' => 'sikeu_enable_golongan_ukt'],
            [
                'value' => $enabled ? 'true' : 'false',
                'description' => 'Status aktifasi skema Golongan UKT (I - VIII) vs Flat Tarif Matriks Semester'
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Pengaturan skema Golongan UKT berhasil diperbarui.',
            'data' => [
                'enabled' => $enabled
            ]
        ]);
    }

    public function searchMahasiswa(Request $request)
    {
        $search = trim($request->query('q', ''));

        try {
            // Search across Siakad Mahasiswa
            $siakadStudents = collect();
            try {
                $siakadQuery = \App\Models\Siakad\Mahasiswa::with('programStudi');
                if (!empty($search)) {
                    $siakadQuery->where(function ($sub) use ($search) {
                        $sub->where('nim', 'like', "%{$search}%")
                            ->orWhere('nama_lengkap', 'like', "%{$search}%")
                            ->orWhere('nik', 'like', "%{$search}%");
                        if (is_numeric($search)) {
                            $sub->orWhere('id', (int)$search);
                        }
                    });
                }
                $siakadStudents = $siakadQuery->orderBy('id', 'desc')->limit(20)->get();
            } catch (\Throwable $e) {
                // Ignore if table not yet migrated
            }

            // Search across MahasiswaTipeTagihan
            $tipeTagihanStudents = collect();
            try {
                $tipeQuery = MahasiswaTipeTagihan::query();
                if (!empty($search)) {
                    $tipeQuery->where(function ($sub) use ($search) {
                        $sub->where('nim', 'like', "%{$search}%")
                            ->orWhere('nama_mahasiswa', 'like', "%{$search}%");
                        if (is_numeric($search)) {
                            $sub->orWhere('mahasiswa_id', (int)$search)
                                ->orWhere('id', (int)$search);
                        }
                    });
                }
                $tipeTagihanStudents = $tipeQuery->orderBy('id', 'desc')->limit(20)->get();
            } catch (\Throwable $e) {
                // Ignore
            }

            // Also search by tagihan nomor / mahasiswa_id in Tagihan
            $tagihanMhsIds = [];
            try {
                if (!empty($search)) {
                    $tagihanMhsIds = TagihanMahasiswa::where('nomor_tagihan', 'like', "%{$search}%")
                        ->limit(10)
                        ->pluck('mahasiswa_id')
                        ->toArray();
                }
            } catch (\Throwable $e) {
                // Ignore
            }

            $studentIds = collect()
                ->merge($siakadStudents->pluck('id'))
                ->merge($tipeTagihanStudents->pluck('mahasiswa_id'))
                ->merge($tagihanMhsIds)
                ->filter()
                ->unique()
                ->take(30);

            // If empty and search is empty, return empty list (jangan fabrikasi data)
            if ($studentIds->isEmpty() && empty($search)) {
                $studentIds = collect();
            }

            $results = $studentIds->map(function ($mhsId) {
                $siakad = null;
                try {
                    $siakad = \App\Models\Siakad\Mahasiswa::with('programStudi')->find($mhsId);
                } catch (\Throwable $e) {}

                $tipe = null;
                try {
                    $tipe = MahasiswaTipeTagihan::where('mahasiswa_id', $mhsId)->first();
                } catch (\Throwable $e) {}

                $nim = $siakad?->nim ?? $tipe?->nim ?? ('-');
                $nama = $siakad?->nama_lengkap ?? $tipe?->nama_mahasiswa ?? ('Mahasiswa #' . $mhsId);
                $prodi = $siakad?->programStudi?->nama ?? $siakad?->programStudi?->nama_prodi ?? '-';
                $angkatan = $siakad?->angkatan ?? $tipe?->tahun_angkatan ?? null;
                $jalur = $tipe?->jalur_kelas ?? '-';
                $kelompokUkt = $tipe?->kelompok_ukt ?? 3;

                // Unpaid bills
                $bills = collect();
                try {
                    $bills = TagihanMahasiswa::with(['details.masterBiaya', 'potonganTagihan', 'dendaTagihan'])
                        ->where('mahasiswa_id', $mhsId)
                        ->whereIn('status', ['belum_bayar', 'sebagian', 'dispensasi'])
                        ->get();
                } catch (\Throwable $e) {}

                $totalUnpaid = $bills->sum(function ($b) {
                    $bersih = (float)($b->total_tagihan + $b->total_denda - $b->total_potongan);
                    return max(0, $bersih - (float)$b->total_bayar);
                });

                $hasUnpaidDispensation = false;
                try {
                    $hasUnpaidDispensation = DispensasiTagihan::where('mahasiswa_id', $mhsId)
                        ->where('status', 'approved')
                        ->whereHas('tagihan', function($q) {
                            $q->whereIn('status', ['belum_bayar', 'sebagian', 'dispensasi']);
                        })
                        ->exists();
                } catch (\Throwable $e) {}

                return [
                    'id' => (int)$mhsId,
                    'mahasiswa_id' => (int)$mhsId,
                    'nim' => $nim,
                    'nama_mahasiswa' => $nama,
                    'prodi' => $prodi,
                    'tahun_angkatan' => (int)$angkatan,
                    'jalur_kelas' => $jalur,
                    'kelompok_ukt' => (int)$kelompokUkt,
                    'unpaid_bills_count' => $bills->count(),
                    'total_unpaid_amount' => $totalUnpaid,
                    'has_unpaid_previous_dispensation' => $hasUnpaidDispensation,
                    'tagihans' => $bills->map(function ($b) {
                        $bersih = (float)($b->total_tagihan + $b->total_denda - $b->total_potongan);
                        $jt = null;
                        if ($b->jatuh_tempo) {
                            $jt = (is_object($b->jatuh_tempo) && method_exists($b->jatuh_tempo, 'format'))
                                ? $b->jatuh_tempo->format('Y-m-d')
                                : (string)$b->jatuh_tempo;
                        }

                        return [
                            'id' => $b->id,
                            'tagihan_id' => $b->id,
                            'nomor_tagihan' => $b->nomor_tagihan,
                            'total_tagihan' => (float)$b->total_tagihan,
                            'total_bayar' => (float)$b->total_bayar,
                            'total_potongan' => (float)$b->total_potongan,
                            'sisa' => max(0, $bersih - (float)$b->total_bayar),
                            'status' => $b->status,
                            'jatuh_tempo' => $jt,
                        ];
                    }),
                ];
            })->values();

            return response()->json([
                'status' => 'success',
                'data' => $results
            ]);
        } catch (\Throwable $e) {
            \Log::error('Error in searchMahasiswa: ' . $e->getMessage());
            return response()->json([
                'status' => 'success',
                'data' => []
            ]);
        }
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
        // Auto-sinkronisasi transparan: pastikan setiap mahasiswa di SIAKAD memiliki entitas tipe tagihan di SIKEU
        try {
            $unsyncedMhs = \App\Models\Siakad\Mahasiswa::whereNotIn('id', function ($q) {
                $q->select('mahasiswa_id')->from('sikeu_mahasiswa_tipe_tagihan')->whereNotNull('mahasiswa_id');
            })->get();

            foreach ($unsyncedMhs as $m) {
                \App\Models\Sikeu\MahasiswaTipeTagihan::create([
                    'mahasiswa_id' => $m->id,
                    'nim' => $m->nim ?? ('NIM-' . $m->id),
                    'nama_mahasiswa' => $m->nama_lengkap ?? ('Mahasiswa #' . $m->id),
                    'tahun_angkatan' => $m->angkatan ?? (int)date('Y'),
                    'jalur_kelas' => !empty($m->jalur_masuk) ? $m->jalur_masuk : 'Reguler',
                    'kelompok_ukt' => $m->kelompok_ukt ?? 3,
                    'status_pendaftaran' => $m->status ?? 'AKTIF',
                    'catatan_perubahan' => 'Sinkronisasi otomatis sistem terintegrasi',
                ]);
            }
        } catch (\Throwable $e) {
            // Lanjutkan jika ada pengecualian minor
        }

        $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
        $query = \App\Models\Sikeu\MahasiswaTipeTagihan::with(['mahasiswa.programStudi', 'beasiswa.beasiswa']);

        // 1. Filter Pencarian Nama / NIM
        if ($request->filled('q') || $request->filled('search')) {
            $term = $request->input('q') ?: $request->input('search');
            $query->where(function ($sq) use ($term) {
                $sq->where('nama_mahasiswa', 'like', "%{$term}%")
                   ->orWhere('nim', 'like', "%{$term}%");
            });
        }

        // 2. Filter Tahun Angkatan
        if ($request->filled('angkatan') || $request->filled('tahun_angkatan')) {
            $angkatan = $request->input('angkatan') ?: $request->input('tahun_angkatan');
            $query->where('tahun_angkatan', (int)$angkatan);
        }

        // 3. Filter Program Studi
        if ($request->filled('program_studi_id') || $request->filled('prodi_id')) {
            $prodiId = (int)($request->input('program_studi_id') ?: $request->input('prodi_id'));
            $query->whereHas('mahasiswa', function ($mq) use ($prodiId) {
                $mq->where('program_studi_id', $prodiId);
            });
        }

        // 4. Filter Jalur Kelas
        if ($request->filled('jalur_kelas')) {
            $query->where('jalur_kelas', $request->input('jalur_kelas'));
        }

        // 5. Filter Golongan UKT
        if ($request->filled('kelompok_ukt')) {
            $query->where('kelompok_ukt', (int)$request->input('kelompok_ukt'));
        }

        // 6. Sorting
        $sortBy = $request->input('sort_by', $request->input('orderBy', 'id'));
        $sortDir = strtolower($request->input('sort_dir', $request->input('orderDir', 'desc'))) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['id', 'nim', 'nama_mahasiswa', 'tahun_angkatan', 'jalur_kelas', 'kelompok_ukt'];
        if (!in_array($sortBy, $allowedSort)) {
            $sortBy = 'id';
        }
        $query->orderBy($sortBy, $sortDir);

        $data = $query->paginate($perPage);

        $items = $data->getCollection()->map(function ($item) {
            $prodiNama = $item->mahasiswa?->programStudi?->nama ?? null;
            $prodiJenjang = $item->mahasiswa?->programStudi?->jenjang ?? null;
            $prodiText = $prodiNama ? ($prodiJenjang ? "{$prodiJenjang} {$prodiNama}" : $prodiNama) : null;

            return [
                'id' => $item->id,
                'mahasiswa_id' => $item->mahasiswa_id,
                'nim' => $item->nim,
                'nama_mahasiswa' => $item->nama_mahasiswa,
                'tahun_angkatan' => $item->tahun_angkatan,
                'jalur_kelas' => $item->jalur_kelas,
                'kelompok_ukt' => $item->kelompok_ukt,
                'status_pendaftaran' => $item->status_pendaftaran,
                'catatan_perubahan' => $item->catatan_perubahan,
                'program_studi_id' => $item->mahasiswa?->program_studi_id,
                'prodi' => $prodiText,
                'beasiswa' => $item->beasiswa ? [
                    'id' => $item->beasiswa->id,
                    'nama' => $item->beasiswa->beasiswa?->nama ?? 'Beasiswa',
                    'kode' => $item->beasiswa->beasiswa?->kode,
                ] : null,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $items,
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
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

        $kelompokUkt = (int)$request->kelompok_ukt;
        $jalurKelas = $request->jalur_kelas;

        $item = \App\Models\Sikeu\MahasiswaTipeTagihan::updateOrCreate(
            ['mahasiswa_id' => $request->mahasiswa_id],
            [
                'nim' => $request->nim ?? ('NIM-' . $request->mahasiswa_id),
                'nama_mahasiswa' => $request->nama_mahasiswa ?? ('Mahasiswa #' . $request->mahasiswa_id),
                'tahun_angkatan' => $request->tahun_angkatan,
                'jalur_kelas' => $jalurKelas,
                'kelompok_ukt' => $kelompokUkt,
                'catatan_perubahan' => $request->catatan_perubahan ?? 'Penetapan tipe tagihan',
            ]
        );

        // Otomatis sinkronkan ke SIAKAD & SPMB
        $this->syncStudentTypeToSiakadAndSpmb($item, $kelompokUkt, $jalurKelas);

        return response()->json([
            'status' => 'success',
            'message' => 'Tipe tagihan mahasiswa berhasil ditetapkan dan disinkronkan ke SIAKAD & SPMB',
            'data' => $item
        ]);
    }

    public function updateStudentBillingType(Request $request, $id)
    {
        $item = \App\Models\Sikeu\MahasiswaTipeTagihan::findOrFail($id);

        $kelompokUkt = $request->filled('kelompok_ukt') ? (int)$request->kelompok_ukt : $item->kelompok_ukt;
        $jalurKelas = $request->filled('jalur_kelas') ? $request->jalur_kelas : $item->jalur_kelas;

        $item->update([
            'jalur_kelas' => $jalurKelas,
            'kelompok_ukt' => $kelompokUkt,
            'catatan_perubahan' => $request->input('catatan_perubahan', $item->catatan_perubahan),
        ]);

        // Otomatis sinkronkan ke SIAKAD & SPMB
        $this->syncStudentTypeToSiakadAndSpmb($item, $kelompokUkt, $jalurKelas);

        return response()->json([
            'status' => 'success',
            'message' => 'Tipe tagihan mahasiswa berhasil diperbarui dan disinkronkan ke SIAKAD & SPMB',
            'data' => $item
        ]);
    }

    /**
     * Helper internal: sinkronisasi otomatis tipe golongan & jalur ke SIAKAD dan SPMB
     */
    protected function syncStudentTypeToSiakadAndSpmb(\App\Models\Sikeu\MahasiswaTipeTagihan $item, int $kelompokUkt, string $jalurKelas): void
    {
        // 1. Sinkronisasi ke SIAKAD (siakad_mahasiswa)
        $mhs = null;
        if (!empty($item->mahasiswa_id)) {
            $mhs = \App\Models\Siakad\Mahasiswa::find($item->mahasiswa_id);
        }
        if (!$mhs && !empty($item->nim)) {
            $mhs = \App\Models\Siakad\Mahasiswa::where('nim', $item->nim)->first();
        }

        if ($mhs) {
            $updateSiakad = [];
            if (\Illuminate\Support\Facades\Schema::hasColumn('siakad_mahasiswa', 'kelompok_ukt')) {
                $updateSiakad['kelompok_ukt'] = $kelompokUkt;
            }
            if (!empty($jalurKelas) && \Illuminate\Support\Facades\Schema::hasColumn('siakad_mahasiswa', 'jalur_masuk')) {
                $updateSiakad['jalur_masuk'] = $jalurKelas;
            }
            if (!empty($updateSiakad)) {
                $mhs->update($updateSiakad);
            }
        }

        // 2. Sinkronisasi ke SPMB (spmb_pendaftaran_calon_mhs)
        $pendaftaran = null;
        if (!empty($item->nim)) {
            $pendaftaran = \App\Models\Spmb\PendaftaranCalonMhs::where('nim', $item->nim)->first();
        }
        if (!$pendaftaran && $mhs && !empty($mhs->user_id)) {
            $pendaftaran = \App\Models\Spmb\PendaftaranCalonMhs::where('user_id', $mhs->user_id)->first();
        }
        if (!$pendaftaran && $mhs && !empty($mhs->nik)) {
            $pendaftaran = \App\Models\Spmb\PendaftaranCalonMhs::where('nik', $mhs->nik)->first();
        }

        if ($pendaftaran) {
            $updateSpmb = [];
            if (\Illuminate\Support\Facades\Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'kelompok_ukt')) {
                $updateSpmb['kelompok_ukt'] = $kelompokUkt;
            }
            if (!empty($jalurKelas)) {
                $tipeJalur = \App\Models\MasterTipeJalur::where('nama', $jalurKelas)
                    ->orWhere('kode', strtolower($jalurKelas))
                    ->first();
                if ($tipeJalur) {
                    $updateSpmb['master_tipe_jalur_id'] = $tipeJalur->id;
                }
            }
            if (!empty($updateSpmb)) {
                $pendaftaran->update($updateSpmb);
            }
        }
    }

    /**
     * POST /api/v1/sikeu/master/sync-students
     * Sinkronisasi masal seluruh mahasiswa dari master SIAKAD/SPMB ke MahasiswaTipeTagihan SIKEU
     */
    public function syncStudentsFromSiakad(Request $request)
    {
        $tahunAngkatan = $request->input('tahun_angkatan');
        $query = \App\Models\Siakad\Mahasiswa::query();

        if (!empty($tahunAngkatan)) {
            $query->where('angkatan', (int)$tahunAngkatan);
        }

        $mahasiswas = $query->get();
        $syncedCount = 0;

        foreach ($mahasiswas as $mhs) {
            \App\Models\Sikeu\MahasiswaTipeTagihan::updateOrCreate(
                ['mahasiswa_id' => $mhs->id],
                [
                    'nim' => $mhs->nim ?? ('NIM-' . $mhs->id),
                    'nama_mahasiswa' => $mhs->nama_lengkap ?? ('Mahasiswa #' . $mhs->id),
                    'tahun_angkatan' => $mhs->angkatan ?? (int)date('Y'),
                    'jalur_kelas' => !empty($mhs->jalur_masuk) ? $mhs->jalur_masuk : 'Reguler',
                    'kelompok_ukt' => $mhs->kelompok_ukt ?? 3,
                    'status_pendaftaran' => $mhs->status ?? 'AKTIF',
                    'catatan_perubahan' => 'Sinkronisasi otomatis master SIAKAD/SPMB',
                ]
            );
            $syncedCount++;
        }

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil menyinkronkan {$syncedCount} data mahasiswa dari SIAKAD/SPMB ke modul Keuangan (SIKEU).",
            'data' => [
                'synced_count' => $syncedCount,
                'target_angkatan' => $tahunAngkatan ?? 'Semua Angkatan',
            ]
        ]);
    }
}

<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\DetailTagihan;
use App\Models\Sikeu\MasterBiaya;
use App\Models\Sikeu\Pembayaran;
use App\Models\Sikeu\SettingTarif;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\VirtualAccount;
use App\Models\Siakad\Mahasiswa;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\Spmb\MasterTahunAkademik;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Services\Sikeu\VaNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
     * Menambahkan tarif komponen biaya baru berdasarkan prodi & angkatan dengan hierarki ketat.
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

        // Rule 1: Jika sudah ada tarif aktif untuk "Semua Program Studi" (Global) pada komponen & angkatan ini,
        // maka tarif global sudah mencakup seluruh kampus -> dilarang menginputkan tarif baru lagi.
        $globalActiveExists = SettingTarif::where('master_biaya_id', $masterBiayaId)
            ->where('tahun_angkatan', $tahunAngkatan)
            ->whereNull('program_studi_id')
            ->where('is_active', true)
            ->exists();

        if ($globalActiveExists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Komponen biaya ini sudah disetting aktif untuk Semua Program Studi pada angkatan ini. Tidak perlu menginputkan tarif lagi.',
            ], 422);
        }

        // Rule 2: Jika user memilih "Semua Program Studi" (Global),
        // pastikan belum ada tarif spesifik per prodi yang aktif untuk komponen & angkatan ini.
        if ($prodiId === null) {
            $prodiTarifActiveExists = SettingTarif::where('master_biaya_id', $masterBiayaId)
                ->where('tahun_angkatan', $tahunAngkatan)
                ->whereNotNull('program_studi_id')
                ->where('is_active', true)
                ->exists();

            if ($prodiTarifActiveExists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sudah terdapat tarif aktif spesifik per program studi untuk komponen biaya ini pada angkatan ini. Harap nonaktifkan tarif prodi terlebih dahulu jika ingin menerapkan satu tarif untuk Semua Program Studi.',
                ], 422);
            }
        }

        // Rule 3: Cek duplikasi kombinasi tepat (master_biaya_id + tahun_angkatan + program_studi_id)
        $existsQuery = SettingTarif::where('master_biaya_id', $masterBiayaId)
            ->where('tahun_angkatan', $tahunAngkatan);

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
     * Memperbarui tarif komponen biaya dengan proteksi hierarki.
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
        $newIsActive = $request->has('is_active') ? $request->boolean('is_active') : $item->is_active;

        // Cek duplikasi kombinasi jika kombinasi berubah
        if ($newBiayaId !== $item->master_biaya_id || $newAngkatan !== $item->tahun_angkatan || $newProdiId !== $item->program_studi_id) {
            $duplicateQuery = SettingTarif::where('id', '!=', $item->id)
                ->where('master_biaya_id', $newBiayaId)
                ->where('tahun_angkatan', $newAngkatan);

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

        // Cek hierarki ketika tarif aktif
        if ($newIsActive) {
            if ($newProdiId !== null) {
                $hasGlobalActive = SettingTarif::where('id', '!=', $item->id)
                    ->where('master_biaya_id', $newBiayaId)
                    ->where('tahun_angkatan', $newAngkatan)
                    ->whereNull('program_studi_id')
                    ->where('is_active', true)
                    ->exists();

                if ($hasGlobalActive) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Komponen biaya ini sudah disetting aktif untuk Semua Program Studi pada angkatan ini. Tidak perlu mengaktifkan tarif spesifik prodi.',
                    ], 422);
                }
            } else {
                $hasProdiActive = SettingTarif::where('id', '!=', $item->id)
                    ->where('master_biaya_id', $newBiayaId)
                    ->where('tahun_angkatan', $newAngkatan)
                    ->whereNotNull('program_studi_id')
                    ->where('is_active', true)
                    ->exists();

                if ($hasProdiActive) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Sudah terdapat tarif aktif spesifik per program studi untuk komponen biaya ini pada angkatan ini. Harap nonaktifkan tarif prodi terlebih dahulu jika ingin menggunakan tarif Semua Program Studi.',
                    ], 422);
                }
            }
        }

        $updateData = [];
        if ($request->filled('master_biaya_id')) $updateData['master_biaya_id'] = $newBiayaId;
        if ($request->filled('tahun_angkatan')) $updateData['tahun_angkatan'] = $newAngkatan;
        if ($request->has('program_studi_id')) $updateData['program_studi_id'] = $newProdiId;
        if ($request->has('nominal')) $updateData['nominal'] = $request->nominal;
        if ($request->has('is_active')) $updateData['is_active'] = $newIsActive;
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
     * GET /api/v1/sikeu/pembayaran-mahasiswa/tarif-mahasiswa
     * Mengambil komponen-komponen tarif dinamis yang berlaku untuk mahasiswa tertentu.
     * Mengadopsi hierarki: spesifik prodi jika ada, fallback ke Semua Program Studi (Global).
     */
    public function tarifMahasiswa(Request $request)
    {
        $mahasiswaId = $request->input('mahasiswa_id');
        $calonId = $request->input('calon_mahasiswa_id');
        $isCalon = $request->input('tipe_referensi') === 'calon_mahasiswa' || $request->filled('calon_mahasiswa_id') || $request->boolean('is_calon_mahasiswa');

        $studentData = null;
        $tahunAngkatan = $request->filled('tahun_angkatan') ? (int)$request->tahun_angkatan : null;
        $prodiId = $request->filled('program_studi_id') ? (int)$request->program_studi_id : null;

        if ($isCalon && $calonId) {
            $calon = PendaftaranCalonMhs::with(['programStudi', 'tahunAkademik'])->find($calonId);
            if ($calon) {
                $tahunAngkatan = $tahunAngkatan ?: ($calon->tahunAkademik?->tahun_mulai ?: date('Y'));
                $prodiId = $prodiId ?: $calon->program_studi_id;
                $studentData = [
                    'id' => $calon->id,
                    'calon_mahasiswa_id' => $calon->id,
                    'nim' => $calon->nim ?: ($calon->no_pendaftaran ?: '-'),
                    'nama' => $calon->nama_lengkap,
                    'is_calon_mahasiswa' => true,
                    'prodi' => $calon->programStudi?->nama ?? '-',
                    'tahun_angkatan' => $tahunAngkatan,
                ];
            }
        } elseif ($mahasiswaId) {
            $siakad = Mahasiswa::with('programStudi')->find($mahasiswaId);
            if ($siakad) {
                $tahunAngkatan = $tahunAngkatan ?: ($siakad->angkatan ?: date('Y'));
                $prodiId = $prodiId ?: $siakad->program_studi_id;
                $studentData = [
                    'id' => $siakad->id,
                    'nim' => $siakad->nim,
                    'nama' => $siakad->nama_lengkap,
                    'is_calon_mahasiswa' => false,
                    'prodi' => $siakad->programStudi?->nama ?? '-',
                    'tahun_angkatan' => $tahunAngkatan,
                ];
            }
        }

        if (!$tahunAngkatan) {
            $tahunAngkatan = (int)date('Y');
        }

        // Ambil seluruh setting tarif yang aktif untuk angkatan tersebut
        $tarifs = SettingTarif::with('masterBiaya')
            ->where('tahun_angkatan', $tahunAngkatan)
            ->where('is_active', true)
            ->get();

        // Kelompokkan tarif per master_biaya_id dan tentukan tarif yang berlaku
        $grouped = $tarifs->groupBy('master_biaya_id');
        $applicableTarifs = [];

        foreach ($grouped as $masterBiayaId => $items) {
            // 1. Cek apakah ada tarif spesifik prodi mahasiswa
            $prodiTarif = $prodiId ? $items->firstWhere('program_studi_id', $prodiId) : null;
            // 2. Cek apakah ada tarif global kampus (Semua Program Studi)
            $globalTarif = $items->first(function ($t) {
                return $t->program_studi_id === null;
            });

            $selectedTarif = $prodiTarif ?: $globalTarif;

            if ($selectedTarif && $selectedTarif->masterBiaya) {
                $applicableTarifs[] = [
                    'setting_tarif_id' => $selectedTarif->id,
                    'master_biaya_id' => $selectedTarif->master_biaya_id,
                    'kode' => $selectedTarif->masterBiaya->kode,
                    'nama' => $selectedTarif->masterBiaya->nama,
                    'tipe' => $selectedTarif->masterBiaya->tipe,
                    'nominal' => (float)$selectedTarif->nominal,
                    'is_recurring' => (bool)$selectedTarif->masterBiaya->is_recurring,
                    'cakupan' => $selectedTarif->program_studi_id !== null ? 'spesifik_prodi' : 'global_kampus',
                    'keterangan' => $selectedTarif->keterangan,
                ];
            }
        }

        // Urutkan berdasarkan kode komponen
        usort($applicableTarifs, function ($a, $b) {
            return strcmp($a['kode'], $b['kode']);
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'mahasiswa' => $studentData,
                'tahun_angkatan' => $tahunAngkatan,
                'program_studi_id' => $prodiId,
                'komponen_tarif' => $applicableTarifs,
            ],
        ]);
    }

    /**
     * GET /api/v1/sikeu/pembayaran-mahasiswa/tagihan
     * List tagihan pembayaran mahasiswa dengan pagination dan filter.
     */
    public function indexTagihan(Request $request)
    {
        $perPage = min(100, $request->integer('per_page', 15));
        $query = TagihanMahasiswa::with(['mahasiswa.programStudi', 'calonMahasiswa.programStudi', 'detailTagihan.masterBiaya', 'virtualAccount', 'pembayarans']);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_tagihan', 'like', "%{$search}%")
                  ->orWhereHas('mahasiswa', function ($mq) use ($search) {
                      $mq->where('nama_lengkap', 'like', "%{$search}%")
                         ->orWhere('nim', 'like', "%{$search}%");
                  })
                  ->orWhereHas('calonMahasiswa', function ($cq) use ($search) {
                      $cq->where('nama_lengkap', 'like', "%{$search}%")
                         ->orWhere('no_pendaftaran', 'like', "%{$search}%");
                  });
            });
        }

        $query->orderBy('id', 'desc');
        $paginated = $query->paginate($perPage);

        $items = collect($paginated->items())->map(function ($item) {
            $isCalon = (bool)$item->calon_mahasiswa_id;
            $mhs = $isCalon ? $item->calonMahasiswa : $item->mahasiswa;
            $va = $item->virtualAccount ?? $item->virtualAccounts->first();

            return [
                'id' => $item->id,
                'nomor_tagihan' => $item->nomor_tagihan,
                'mahasiswa_id' => $item->mahasiswa_id,
                'calon_mahasiswa_id' => $item->calon_mahasiswa_id,
                'is_calon_mahasiswa' => $isCalon,
                'nim' => $isCalon ? ($mhs?->nim ?: ($mhs?->no_pendaftaran ?: '-')) : ($mhs?->nim ?? '-'),
                'nama_mahasiswa' => $mhs?->nama_lengkap ?? ($isCalon ? 'Calon Mhs #' . $item->calon_mahasiswa_id : 'Mahasiswa #' . $item->mahasiswa_id),
                'prodi' => $mhs?->programStudi?->nama ?? '-',
                'total_tagihan' => (float)$item->total_tagihan,
                'total_potongan' => (float)$item->total_potongan,
                'total_bayar' => (float)$item->total_bayar,
                'sisa' => max(0, (float)$item->total_tagihan - (float)$item->total_potongan - (float)$item->total_bayar),
                'status' => $item->status,
                'jatuh_tempo' => $item->jatuh_tempo ? $item->jatuh_tempo->format('Y-m-d') : null,
                'va_number' => $va?->va_number,
                'created_at' => $item->created_at ? $item->created_at->format('Y-m-d H:i') : null,
                'rincian_komponen' => $item->detailTagihan->map(function ($d) {
                    return [
                        'master_biaya_id' => $d->master_biaya_id,
                        'nama_biaya' => $d->masterBiaya?->nama ?? 'Komponen Biaya',
                        'nominal' => (float)$d->nominal,
                    ];
                }),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar tagihan mahasiswa berhasil dimuat',
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
        ]);
    }

    /**
     * POST /api/v1/sikeu/pembayaran-mahasiswa/tagihan
     * Membuat tagihan mahasiswa dengan komponen dinamis dari setting tarif.
     * Mendukung mode terbitkan invoice/VA atau bayar langsung di loket kasir.
     */
    public function storeTagihan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mahasiswa_id' => 'nullable|integer|required_without:calon_mahasiswa_id',
            'calon_mahasiswa_id' => 'nullable|integer|required_without:mahasiswa_id',
            'tipe_referensi' => 'nullable|string|max:30',
            'semester' => 'nullable|integer|min:1|max:14',
            'jatuh_tempo' => 'required|date',
            'catatan' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.master_biaya_id' => 'required|integer|exists:sikeu_master_biaya,id',
            'items.*.nominal' => 'required|numeric|min:0',
            'items.*.keterangan' => 'nullable|string|max:255',
            'mode_pembayaran' => 'required|in:terbitkan_tagihan,bayar_loket_tunai,bayar_loket_transfer',
            'jumlah_bayar' => 'nullable|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi tagihan mahasiswa gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $isCalon = $request->input('tipe_referensi') === 'calon_mahasiswa' || $request->filled('calon_mahasiswa_id') || $request->boolean('is_calon_mahasiswa');
            $mhsId = $request->input('mahasiswa_id');
            $calonId = $request->input('calon_mahasiswa_id');

            if ($isCalon && !$calonId) {
                $calonId = $mhsId;
                $mhsId = null;
            }

            // Identifikasi info mahasiswa
            $identifier = 'MHS';
            $namaMhs = 'Mahasiswa';
            if ($isCalon) {
                $calon = PendaftaranCalonMhs::find($calonId);
                $identifier = $calon?->nim ?: ($calon?->no_pendaftaran ?: (string)$calonId);
                $namaMhs = $calon?->nama_lengkap ?? ('Calon Mhs #' . $calonId);
            } else {
                $siakad = Mahasiswa::find($mhsId);
                $identifier = $siakad?->nim ?? (string)$mhsId;
                $namaMhs = $siakad?->nama_lengkap ?? ('Mahasiswa #' . $mhsId);
            }

            // Dapatkan tahun akademik aktif
            $activeTa = MasterTahunAkademik::where('is_active', true)->first();
            $taId = $activeTa?->id ?? 1;

            $totalNominal = collect($request->items)->sum('nominal');
            $modePembayaran = $request->mode_pembayaran;
            $isDirectCashier = in_array($modePembayaran, ['bayar_loket_tunai', 'bayar_loket_transfer']);

            $nomorTagihan = 'INV-MHS-' . date('Ymd') . '-' . strtoupper(Str::random(4));

            // 1. Buat TagihanMahasiswa
            $tagihan = TagihanMahasiswa::create([
                'mahasiswa_id' => $isCalon ? null : $mhsId,
                'calon_mahasiswa_id' => $isCalon ? $calonId : null,
                'tipe_referensi' => $isCalon ? 'calon_mahasiswa' : 'mahasiswa',
                'tahun_akademik_id' => $taId,
                'nomor_tagihan' => $nomorTagihan,
                'total_tagihan' => $totalNominal,
                'total_potongan' => 0,
                'total_denda' => 0,
                'total_bayar' => $isDirectCashier ? $totalNominal : 0,
                'status' => $isDirectCashier ? 'lunas' : 'belum_bayar',
                'requires_approval' => false,
                'source_system' => 'sikeu_pembayaran_mahasiswa',
                'jatuh_tempo' => $request->jatuh_tempo,
                'catatan_approval' => $request->catatan ?: ('Tagihan Semester ' . ($request->semester ?? 1)),
            ]);

            // 2. Buat DetailTagihan per item
            foreach ($request->items as $item) {
                DetailTagihan::create([
                    'tagihan_id' => $tagihan->id,
                    'master_biaya_id' => $item['master_biaya_id'],
                    'nominal' => $item['nominal'],
                    'potongan' => 0,
                    'nominal_bersih' => $item['nominal'],
                    'keterangan' => $item['keterangan'] ?? null,
                ]);
            }

            // 3. Terbitkan Virtual Account standar (88012 standard)
            $vaNumber = VaNumberService::generate($identifier);
            $va = VirtualAccount::create([
                'tagihan_id' => $tagihan->id,
                'va_number' => $vaNumber,
                'bank_kode' => 'BANK_KAMPUS',
                'bank_nama' => 'Bank Mitra Kampus Terintegrasi',
                'nominal' => $totalNominal,
                'expired_at' => now()->addDays(30),
                'status' => $isDirectCashier ? 'dibayar' : 'aktif',
            ]);

            // 4. Jika mode bayar loket kasir, langsung catat pembayaran
            $pembayaran = null;
            if ($isDirectCashier) {
                $channel = $modePembayaran === 'bayar_loket_tunai' ? 'LOKET_TUNAI' : 'LOKET_TRANSFER';
                $kodeBayar = 'KASIR-' . date('YmdHis') . '-' . rand(100, 999);

                $pembayaran = Pembayaran::create([
                    'tagihan_id' => $tagihan->id,
                    'virtual_account_id' => $va->id,
                    'kode_transaksi' => $kodeBayar,
                    'jumlah_bayar' => $totalNominal,
                    'waktu_bayar' => now(),
                    'channel_bayar' => $channel,
                    'bank_pengirim' => $modePembayaran === 'bayar_loket_tunai' ? 'KAS KAMPUS' : 'TRANSFER BANK',
                    'catatan' => 'Pembayaran lunas via Loket Kasir Kampus',
                    'status' => 'success',
                    'diverifikasi_oleh' => auth()->id(),
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => $isDirectCashier
                    ? 'Tagihan dan transaksi pembayaran kasir loket berhasil diproses.'
                    : 'Tagihan mahasiswa dan nomor Virtual Account berhasil diterbitkan.',
                'data' => [
                    'tagihan' => $tagihan->load(['detailTagihan.masterBiaya', 'virtualAccount']),
                    'nomor_tagihan' => $tagihan->nomor_tagihan,
                    'va_number' => $va->va_number,
                    'total_tagihan' => $totalNominal,
                    'status' => $tagihan->status,
                    'nama_mahasiswa' => $namaMhs,
                    'pembayaran' => $pembayaran,
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses tagihan mahasiswa: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/sikeu/pembayaran-mahasiswa/katalog-biaya
     * Mengambil katalog komponen biaya aktif untuk opsi dropdown.
     * Hanya mengambil komponen biaya dengan skema tarif dinamis yang dikonfigurasikan di /sikeu/master.
     */
    public function katalogBiaya()
    {
        $biaya = MasterBiaya::where('is_active', true)
            ->where('skema_tarif', 'dinamis')
            ->orderBy('kode', 'asc')
            ->get(['id', 'kode', 'nama', 'tipe', 'skema_tarif', 'nominal_standar', 'is_recurring']);

        return response()->json([
            'status' => 'success',
            'data' => $biaya,
        ]);
    }

    /**
     * GET /api/v1/sikeu/pembayaran-mahasiswa/mass-tagihan/preview
     * Simulasi dan pratinjau penerbitan tagihan massal per angkatan & program studi.
     */
    public function previewMassTagihan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tahun_angkatan' => 'required|integer|min:2000|max:2050',
            'program_studi_id' => 'nullable|integer|exists:spmb_master_program_studi,id',
            'master_biaya_ids' => 'nullable|array',
            'master_biaya_ids.*' => 'integer|exists:sikeu_master_biaya,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi pratinjau tagihan massal gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tahunAngkatan = (int)$request->tahun_angkatan;
        $prodiId = $request->filled('program_studi_id') ? (int)$request->program_studi_id : null;

        $studentsQuery = Mahasiswa::with('programStudi')
            ->where('angkatan', $tahunAngkatan);

        if ($prodiId) {
            $studentsQuery->where('program_studi_id', $prodiId);
        }

        $students = $studentsQuery->get();

        // Komponen biaya yang akan ditagihkan (hanya skema dinamis)
        $biayaQuery = MasterBiaya::where('is_active', true)->where('skema_tarif', 'dinamis');
        if ($request->filled('master_biaya_ids') && is_array($request->master_biaya_ids)) {
            $biayaQuery->whereIn('id', $request->master_biaya_ids);
        }
        $komponenBiaya = $biayaQuery->get(['id', 'kode', 'nama', 'tipe', 'nominal_standar']);

        // Ambil pengaturan tarif untuk angkatan ini
        $tarifs = SettingTarif::where('is_active', true)
            ->where('tahun_angkatan', $tahunAngkatan)
            ->whereIn('master_biaya_id', $komponenBiaya->pluck('id'))
            ->get();

        $totalEstimasiNominal = 0;
        $sampleMahasiswa = [];

        foreach ($students as $index => $mhs) {
            $mhsNominal = 0;
            $rincian = [];

            foreach ($komponenBiaya as $kb) {
                // Hierarki tarif: spesifik prodi -> global prodi null -> nominal standar
                $tarif = $tarifs->first(fn($t) => $t->master_biaya_id == $kb->id && $t->program_studi_id == $mhs->program_studi_id)
                      ?? $tarifs->first(fn($t) => $t->master_biaya_id == $kb->id && $t->program_studi_id === null);

                $nom = $tarif ? (float)$tarif->nominal : (float)$kb->nominal_standar;
                if ($nom > 0) {
                    $mhsNominal += $nom;
                    $rincian[] = [
                        'master_biaya_id' => $kb->id,
                        'nama' => $kb->nama,
                        'nominal' => $nom,
                    ];
                }
            }

            $totalEstimasiNominal += $mhsNominal;

            if ($index < 10) {
                $sampleMahasiswa[] = [
                    'id' => $mhs->id,
                    'nim' => $mhs->nim ?: '-',
                    'nama_lengkap' => $mhs->nama_lengkap,
                    'prodi' => $mhs->programStudi?->nama ?? '-',
                    'total_nominal' => $mhsNominal,
                    'rincian' => $rincian,
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pratinjau tagihan massal berhasil dikalkulasi.',
            'data' => [
                'tahun_angkatan' => $tahunAngkatan,
                'program_studi_id' => $prodiId,
                'total_mahasiswa' => $students->count(),
                'total_estimasi_nominal' => $totalEstimasiNominal,
                'komponen_biaya' => $komponenBiaya,
                'sample_mahasiswa' => $sampleMahasiswa,
            ],
        ]);
    }

    /**
     * POST /api/v1/sikeu/pembayaran-mahasiswa/mass-tagihan
     * Menerbitkan tagihan massal untuk seluruh mahasiswa dalam satu angkatan dan/atau prodi.
     */
    public function storeMassTagihan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tahun_angkatan' => 'required|integer|min:2000|max:2050',
            'program_studi_id' => 'nullable|integer|exists:spmb_master_program_studi,id',
            'semester' => 'required|integer|min:1|max:14',
            'jatuh_tempo' => 'required|date',
            'catatan' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.master_biaya_id' => 'required|integer|exists:sikeu_master_biaya,id',
            'items.*.nominal' => 'nullable|numeric|min:0',
            'items.*.keterangan' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi tagihan massal gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tahunAngkatan = (int)$request->tahun_angkatan;
        $prodiId = $request->filled('program_studi_id') ? (int)$request->program_studi_id : null;

        $studentsQuery = Mahasiswa::where('angkatan', $tahunAngkatan);
        if ($prodiId) {
            $studentsQuery->where('program_studi_id', $prodiId);
        }

        $students = $studentsQuery->get();

        if ($students->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => "Tidak ditemukan mahasiswa aktif pada angkatan {$tahunAngkatan}" . ($prodiId ? " untuk program studi yang dipilih." : "."),
            ], 422);
        }

        $activeTa = MasterTahunAkademik::where('is_active', true)->first();
        $taId = $activeTa?->id ?? 1;

        $biayaIds = collect($request->items)->pluck('master_biaya_id')->unique()->toArray();
        $tarifs = SettingTarif::where('is_active', true)
            ->where('tahun_angkatan', $tahunAngkatan)
            ->whereIn('master_biaya_id', $biayaIds)
            ->get();

        $masterBiayas = MasterBiaya::whereIn('id', $biayaIds)->get()->keyBy('id');

        try {
            DB::beginTransaction();

            $createdCount = 0;
            $totalNominalGenerated = 0;

            foreach ($students as $student) {
                $studentItems = [];
                $studentTotal = 0;

                foreach ($request->items as $reqItem) {
                    $mbId = (int)$reqItem['master_biaya_id'];
                    $hasCustom = isset($reqItem['nominal']) && is_numeric($reqItem['nominal']) && (float)$reqItem['nominal'] > 0;

                    if ($hasCustom) {
                        $nominal = (float)$reqItem['nominal'];
                    } else {
                        // Ambil dari tarif: spesifik prodi -> global -> nominal standar
                        $tarif = $tarifs->first(fn($t) => $t->master_biaya_id == $mbId && $t->program_studi_id == $student->program_studi_id)
                              ?? $tarifs->first(fn($t) => $t->master_biaya_id == $mbId && $t->program_studi_id === null);

                        $nominal = $tarif ? (float)$tarif->nominal : (float)($masterBiayas[$mbId]?->nominal_standar ?? 0);
                    }

                    if ($nominal > 0) {
                        $studentItems[] = [
                            'master_biaya_id' => $mbId,
                            'nominal' => $nominal,
                            'keterangan' => $reqItem['keterangan'] ?? null,
                        ];
                        $studentTotal += $nominal;
                    }
                }

                if ($studentTotal <= 0 || empty($studentItems)) {
                    continue;
                }

                $nomorTagihan = 'INV-MHS-' . date('Ymd') . '-' . strtoupper(Str::random(5));
                $tagihan = TagihanMahasiswa::create([
                    'mahasiswa_id' => $student->id,
                    'calon_mahasiswa_id' => null,
                    'tipe_referensi' => 'mahasiswa',
                    'tahun_akademik_id' => $taId,
                    'nomor_tagihan' => $nomorTagihan,
                    'total_tagihan' => $studentTotal,
                    'total_potongan' => 0,
                    'total_denda' => 0,
                    'total_bayar' => 0,
                    'status' => 'belum_bayar',
                    'requires_approval' => false,
                    'source_system' => 'sikeu_pembayaran_mahasiswa_massal',
                    'jatuh_tempo' => $request->jatuh_tempo,
                    'catatan_approval' => $request->catatan ?: ('Tagihan Massal Angkatan ' . $tahunAngkatan . ' - Semester ' . $request->semester),
                ]);

                foreach ($studentItems as $si) {
                    DetailTagihan::create([
                        'tagihan_id' => $tagihan->id,
                        'master_biaya_id' => $si['master_biaya_id'],
                        'nominal' => $si['nominal'],
                        'potongan' => 0,
                        'nominal_bersih' => $si['nominal'],
                        'keterangan' => $si['keterangan'],
                    ]);
                }

                $vaNumber = VaNumberService::generate($student->nim ?: (string)$student->id);
                VirtualAccount::create([
                    'tagihan_id' => $tagihan->id,
                    'va_number' => $vaNumber,
                    'bank_kode' => 'BANK_KAMPUS',
                    'bank_nama' => 'Bank Mitra Kampus Terintegrasi',
                    'nominal' => $studentTotal,
                    'expired_at' => \Carbon\Carbon::parse($request->jatuh_tempo)->endOfDay(),
                    'status' => 'aktif',
                ]);

                $createdCount++;
                $totalNominalGenerated += $studentTotal;
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Berhasil menerbitkan {$createdCount} tagihan massal untuk angkatan {$tahunAngkatan}.",
                'data' => [
                    'created_count' => $createdCount,
                    'total_nominal' => $totalNominalGenerated,
                    'tahun_angkatan' => $tahunAngkatan,
                    'program_studi_id' => $prodiId,
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses tagihan massal: ' . $e->getMessage(),
            ], 500);
        }
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

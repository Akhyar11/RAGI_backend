<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\StoreMasterKomponenGajiRequest;
use App\Http\Requests\Simpeg\UpdateMasterKomponenGajiRequest;
use App\Http\Requests\Simpeg\StoreMasterSkalaGajiRequest;
use App\Http\Requests\Simpeg\UpdateMasterSkalaGajiRequest;
use App\Models\Simpeg\GajiDetail;
use App\Models\Simpeg\GajiPegawai;
use App\Models\Simpeg\JabatanFungsionalAkademik;
use App\Models\Simpeg\MasterBracketPph21;
use App\Models\Simpeg\MasterKomponenGaji;
use App\Models\Simpeg\MasterSkalaGajiPokok;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PegawaiKomponenGaji;
use App\Services\Simpeg\PayrollCalculationService;
use App\Services\Simpeg\SikeuIntegrationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function __construct(
        protected PayrollCalculationService $calculationService
    ) {}

    /**
     * GET /api/simpeg/payroll
     * Daftar rekapan payroll gaji pegawai dengan server-side pagination, search & filter
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.payroll.read') && !$user->hasPermission('simpeg.payroll.view') && !$user->hasPermission('simpeg.payroll.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk melihat Slip Gaji / Payroll.'
            ], 403);
        }

        $query = GajiPegawai::with(['pegawai.unitKerja', 'pegawai.dosen.programStudi', 'jurnal', 'pengeluaranKampus']);

        // Scope pegawai jika bukan admin/payroll manager
        if (!$user->isAdmin() && !$user->hasPermission('simpeg.payroll.manage')) {
            $pegId = $user->pegawai?->id;
            if ($pegId) {
                $query->where('pegawai_id', $pegId);
            } else {
                return response()->json([
                    'status' => 'success',
                    'data' => [],
                    'meta' => [
                        'current_page' => 1,
                        'per_page' => 15,
                        'total' => 0,
                        'last_page' => 1,
                    ],
                ]);
            }
        } elseif ($request->filled('pegawai_id')) {
            $query->where('pegawai_id', $request->pegawai_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('periode_bulan_tahun', 'like', "%{$search}%")
                  ->orWhereHas('pegawai', function ($qp) use ($search) {
                      $qp->where('nama_lengkap', 'like', "%{$search}%")
                         ->orWhere('nip', 'like', "%{$search}%")
                         ->orWhere('nidn', 'like', "%{$search}%")
                         ->orWhere('nuptk', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('periode')) {
            $query->where('periode_bulan_tahun', $request->periode);
        }

        if ($request->filled('status_transfer')) {
            $query->where('status_transfer', $request->status_transfer);
        }

        $allowedSorts = ['periode_bulan_tahun', 'gaji_bersih', 'total_tunjangan', 'total_potongan', 'created_at', 'id'];
        $sortBy = in_array($request->query('sort_by'), $allowedSorts, true) ? $request->query('sort_by') : 'created_at';
        $sortOrder = strtolower($request->query('sort_dir', $request->query('sort_order', 'desc'))) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortOrder);

        $perPage = min(100, $request->integer('per_page', 15));
        $payroll = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data payroll berhasil dimuat',
            'data' => $payroll->items(),
            'meta' => [
                'current_page' => $payroll->currentPage(),
                'per_page' => $payroll->perPage(),
                'total' => $payroll->total(),
                'last_page' => $payroll->lastPage(),
                'from' => $payroll->firstItem(),
                'to' => $payroll->lastItem(),
            ],
            'filters' => [
                'search' => (string) $request->input('search', ''),
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * GET /api/simpeg/payroll/{id}
     * Rincian mendalam satu slip gaji beserta butir detail penerimaan & potongan
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.payroll.read') && !$user->hasPermission('simpeg.payroll.view') && !$user->hasPermission('simpeg.payroll.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk melihat rincian Slip Gaji.'
            ], 403);
        }

        $gaji = GajiPegawai::with([
            'pegawai.unitKerja',
            'pegawai.dosen',
            'details.komponen',
            'jurnal.details.akun',
            'pengeluaranKampus',
        ])->findOrFail($id);

        // Validasi kepemilikan data untuk pegawai biasa
        if (!$user->isAdmin() && !$user->hasPermission('simpeg.payroll.manage')) {
            if ($gaji->pegawai_id !== $user->pegawai?->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda hanya berhak melihat Slip Gaji milik sendiri.'
                ], 403);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Rincian slip gaji berhasil dimuat',
            'data' => $gaji,
        ]);
    }

    /**
     * POST /api/simpeg/payroll/generate
     * Hitung kalkulasi payroll terpadu fleksibel dari master komponen, presensi SIMPEG & SKS SIAKAD
     */
    public function generatePayroll(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.payroll.create') && !$user->hasPermission('simpeg.payroll.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk membuat kalkulasi payroll.'
            ], 403);
        }

        $validated = $request->validate([
            'periode' => 'required|string|regex:/^\d{4}-\d{2}$/', // Format: YYYY-MM
            'pegawai_id' => 'nullable|exists:simpeg_pegawai,id',
        ]);

        $periode = $validated['periode'];
        $pegawaiId = $validated['pegawai_id'] ?? null;

        $result = $this->calculationService->calculatePayroll($periode, $pegawaiId);

        return response()->json([
            'status' => 'success',
            'message' => "Kalkulasi payroll periode {$periode} berhasil diproses untuk {$result['total_pegawai']} pegawai!",
            'data' => $result,
        ]);
    }

    /**
     * POST /api/simpeg/payroll/submit-to-sikeu
     * Kirim pengajuan payroll satu periode ke Modul SIKEU
     */
    public function submitToSikeu(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.payroll.create') && !$user->hasPermission('simpeg.payroll.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk mengajukan payroll ke SIKEU.'
            ], 403);
        }

        $validated = $request->validate([
            'periode' => 'required|string',
        ]);

        $updatedCount = GajiPegawai::where('periode_bulan_tahun', $validated['periode'])
            ->whereIn('status_transfer', ['draft', 'cancelled'])
            ->update([
                'status_transfer' => 'submitted_to_sikeu',
                'submitted_at' => now(),
            ]);

        return response()->json([
            'status' => 'success',
            'message' => "Pengajuan payroll periode {$validated['periode']} ({$updatedCount} pegawai) berhasil dikirimkan ke modul SIKEU untuk proses pembayaran!",
        ]);
    }

    /**
     * POST /api/simpeg/payroll/{id}/process-payment
     * Eksekusi pencairan gaji, posting jurnal akuntansi seimbang SIKEU & mutasi kas
     */
    public function processPayment(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.payroll.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk memproses pembayaran gaji di SIKEU.'
            ], 403);
        }

        $gaji = GajiPegawai::with(['pegawai', 'details'])->findOrFail($id);

        $gaji->update([
            'status_transfer' => 'paid',
            'tanggal_transfer' => now(),
        ]);

        $sikeuJournal = SikeuIntegrationService::postPayrollJournal($gaji);

        return response()->json([
            'status' => 'success',
            'message' => "Pembayaran gaji {$gaji->pegawai?->nama_lengkap} periode {$gaji->periode_bulan_tahun} berhasil dibayarkan dan jurnal SIKEU diterbitkan!",
            'data' => $gaji->fresh(['jurnal', 'pengeluaranKampus']),
            'sikeu_journal' => $sikeuJournal,
        ]);
    }

    // ── MASTER KOMPONEN GAJI ──────────────────────────────────

    /**
     * GET /api/simpeg/payroll/komponen
     * Daftar master komponen gaji fleksibel
     */
    public function indexKomponen(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.payroll.read') && !$user->hasPermission('simpeg.payroll.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat master komponen gaji.'
            ], 403);
        }

        $query = MasterKomponenGaji::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%");
            });
        }

        if ($request->filled('jenis')) {
            $query->where('jenis', $request->jenis);
        }

        $query->orderBy('urutan', 'asc')->orderBy('id', 'asc');

        $perPage = min(100, $request->integer('limit', $request->integer('per_page', 20)));
        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data master komponen gaji berhasil dimuat',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/simpeg/payroll/komponen
     * Tambah master komponen gaji baru
     */
    public function storeKomponen(StoreMasterKomponenGajiRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.payroll.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menambah komponen gaji.'
            ], 403);
        }

        $komponen = MasterKomponenGaji::create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => "Komponen gaji '{$komponen->nama}' berhasil ditambahkan.",
            'data' => $komponen,
        ], 201);
    }

    /**
     * PUT /api/simpeg/payroll/komponen/{id}
     * Ubah konfigurasi master komponen gaji
     */
    public function updateKomponen(UpdateMasterKomponenGajiRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.payroll.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengubah komponen gaji.'
            ], 403);
        }

        $komponen = MasterKomponenGaji::findOrFail($id);
        $komponen->update($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => "Komponen gaji '{$komponen->nama}' berhasil diperbarui.",
            'data' => $komponen,
        ]);
    }

    /**
     * DELETE /api/simpeg/payroll/komponen/{id}
     * Hapus master komponen gaji
     */
    public function destroyKomponen(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.payroll.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menghapus komponen gaji.'
            ], 403);
        }

        $komponen = MasterKomponenGaji::findOrFail($id);
        $nama = $komponen->nama;
        $komponen->delete();

        return response()->json([
            'status' => 'success',
            'message' => "Komponen gaji '{$nama}' berhasil dihapus.",
        ]);
    }


    // ── MASTER SKALA GAJI POKOK (MASA KERJA) ──────────────────

    /**
     * GET /api/simpeg/payroll/skala-gaji
     */
    public function indexSkalaGaji(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.payroll.read') && !$user->hasPermission('simpeg.payroll.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses melihat skala gaji pokok.'
            ], 403);
        }

        $query = MasterSkalaGajiPokok::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_skala', 'like', "%{$search}%")
                  ->orWhere('golongan', 'like', "%{$search}%");
            });
        }

        if ($request->filled('golongan')) {
            $query->where('golongan', $request->golongan);
        }

        $query->orderBy('golongan', 'asc')->orderBy('masa_kerja_min_tahun', 'asc');

        $perPage = min(100, $request->integer('limit', $request->integer('per_page', 20)));
        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data skala gaji pokok berhasil dimuat',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/simpeg/payroll/skala-gaji
     */
    public function storeSkalaGaji(StoreMasterSkalaGajiRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.payroll.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses menambah skala gaji pokok.'
            ], 403);
        }

        $skala = MasterSkalaGajiPokok::create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => "Skala gaji '{$skala->nama_skala}' berhasil ditambahkan.",
            'data' => $skala,
        ], 201);
    }

    /**
     * PUT /api/simpeg/payroll/skala-gaji/{id}
     */
    public function updateSkalaGaji(UpdateMasterSkalaGajiRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.payroll.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses mengubah skala gaji pokok.'
            ], 403);
        }

        $skala = MasterSkalaGajiPokok::findOrFail($id);
        $skala->update($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => "Skala gaji '{$skala->nama_skala}' berhasil diperbarui.",
            'data' => $skala,
        ]);
    }

    /**
     * DELETE /api/simpeg/payroll/skala-gaji/{id}
     */
    public function destroySkalaGaji(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.payroll.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses menghapus skala gaji pokok.'
            ], 403);
        }

        $skala = MasterSkalaGajiPokok::findOrFail($id);
        $nama = $skala->nama_skala;
        $skala->delete();

        return response()->json([
            'status' => 'success',
            'message' => "Skala gaji '{$nama}' berhasil dihapus.",
        ]);
    }

    // ── TUNJANGAN JABATAN FUNGSIONAL AKADEMIK ──────────────────

    /**
     * GET /api/simpeg/payroll/jafung-tunjangan
     */
    public function indexJafungTunjangan(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.payroll.read') && !$user->hasPermission('simpeg.payroll.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses melihat tunjangan jabatan fungsional.'
            ], 403);
        }

        $query = JabatanFungsionalAkademik::query()->orderBy('angka_kredit_min', 'asc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('golongan', 'like', "%{$search}%");
            });
        }

        $data = $query->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Data tunjangan jabatan fungsional berhasil dimuat',
            'data' => $data,
        ]);
    }

    /**
     * PUT /api/simpeg/payroll/jafung-tunjangan/{id}
     */
    public function updateJafungTunjangan(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.payroll.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses mengubah tunjangan jabatan fungsional.'
            ], 403);
        }

        $validated = $request->validate([
            'tunjangan_nominal' => 'required|numeric|min:0',
        ]);

        $jafung = JabatanFungsionalAkademik::findOrFail($id);
        $jafung->update([
            'tunjangan_nominal' => $validated['tunjangan_nominal'],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Tunjangan jabatan fungsional '{$jafung->nama}' berhasil diperbarui.",
            'data' => $jafung,
        ]);
    }

    // ── MASTER BRACKET TARIF PPH 21 (TER) ──────────────────────

    /**
     * GET /api/simpeg/payroll/bracket-pph21
     */
    public function indexBracketPph21(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.payroll.read') && !$user->hasPermission('simpeg.payroll.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses melihat bracket tarif PPh 21.'
            ], 403);
        }

        $query = MasterBracketPph21::query()->orderBy('penghasilan_bruto_min', 'asc');
        $data = $query->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Data bracket tarif PPh 21 berhasil dimuat',
            'data' => $data,
        ]);
    }

    /**
     * PUT /api/simpeg/payroll/bracket-pph21/{id}
     */
    public function updateBracketPph21(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.payroll.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses mengubah bracket tarif PPh 21.'
            ], 403);
        }

        $validated = $request->validate([
            'penghasilan_bruto_min' => 'sometimes|required|numeric|min:0',
            'penghasilan_bruto_max' => 'nullable|numeric|min:0',
            'tarif_persen' => 'sometimes|required|numeric|min:0|max:1',
            'keterangan' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $bracket = MasterBracketPph21::findOrFail($id);
        $bracket->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => "Bracket tarif PPh 21 berhasil diperbarui.",
            'data' => $bracket,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\UpdatePegawaiKomponenGajiRequest;
use App\Models\Simpeg\MasterKomponenGaji;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PegawaiKomponenGaji;
use App\Services\AuditLogService;
use App\Services\Simpeg\PayrollCalculationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PegawaiKomponenGajiController extends Controller
{
    public function __construct(
        private PayrollCalculationService $calculationService
    ) {}

    /**
     * GET /api/simpeg/payroll/pegawai/{pegawaiId}/komponen
     * Ambil konfigurasi komponen gaji milik pegawai tertentu
     */
    public function show(Request $request, int $pegawaiId): JsonResponse
    {
        $user = $request->user();
        $canRead = $user && (
            $user->hasPermission('simpeg.payroll.read')
            || $user->hasPermission('simpeg.payroll.manage')
            || $user->hasPermission('simpeg.pegawai.read')
            || $user->hasPermission('simpeg.pegawai.manage')
            || $user->hasPermission('simpeg.pegawai.update')
            || $user->hasRole('superadmin')
            || $user->hasRole('admin')
        );

        if (!$canRead) {
            throw new AuthorizationException('Anda tidak memiliki hak akses melihat konfigurasi komponen pegawai.');
        }

        $pegawai = Pegawai::findOrFail($pegawaiId);
        $masterKomponens = MasterKomponenGaji::where('is_active', true)->orderBy('urutan', 'asc')->get();
        $customs = PegawaiKomponenGaji::where('pegawai_id', $pegawaiId)->get()->keyBy('komponen_gaji_id');

        $result = $masterKomponens->map(function ($mk) use ($customs) {
            $cust = $customs->get($mk->id);
            return [
                'komponen_gaji_id' => $mk->id,
                'kode' => $mk->kode,
                'nama' => $mk->nama,
                'jenis' => $mk->jenis,
                'tipe_nilai' => $mk->tipe_nilai,
                'nilai_default' => (float) $mk->nilai_default,
                'nominal_kustom' => $cust && $cust->nominal_kustom !== null ? (float) $cust->nominal_kustom : null,
                'nilai_kustom' => $cust && $cust->nominal_kustom !== null ? (float) $cust->nominal_kustom : null,
                'is_active' => $cust ? (bool) $cust->is_active : (bool) $mk->is_active,
                'catatan' => $cust?->catatan,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Data konfigurasi komponen gaji pegawai berhasil diambil',
            'data' => [
                'pegawai' => $pegawai,
                'komponen' => $result->values()->all(),
            ],
        ], 200);
    }

    /**
     * PUT /api/simpeg/payroll/pegawai/{pegawaiId}/komponen
     * Simpan / timpa konfigurasi komponen gaji pegawai
     */
    public function update(UpdatePegawaiKomponenGajiRequest $request, int $pegawaiId): JsonResponse
    {
        $user = $request->user();
        $canManage = $user && (
            $user->hasPermission('simpeg.payroll.manage')
            || $user->hasPermission('simpeg.pegawai.manage')
            || $user->hasPermission('simpeg.pegawai.create')
            || $user->hasPermission('simpeg.pegawai.update')
            || $user->hasRole('superadmin')
            || $user->hasRole('admin')
        );

        if (!$canManage) {
            throw new AuthorizationException('Anda tidak memiliki hak akses mengatur komponen gaji pegawai.');
        }

        $pegawai = Pegawai::findOrFail($pegawaiId);

        $this->calculationService->savePegawaiKomponen($pegawaiId, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => "Konfigurasi komponen gaji untuk {$pegawai->nama_lengkap} berhasil disimpan.",
            'data' => $pegawai->fresh(),
        ], 200);
    }

    /**
     * GET /api/simpeg/payroll/pegawai-matriks
     * Ambil daftar pegawai beserta seluruh nilai komponen gaji yang aktif (untuk Tab 1)
     */
    public function indexMatriks(Request $request): JsonResponse
    {
        $user = $request->user();
        $canRead = $user && (
            $user->hasPermission('simpeg.payroll.read')
            || $user->hasPermission('simpeg.payroll.manage')
            || $user->hasPermission('simpeg.pegawai.read')
            || $user->hasPermission('simpeg.pegawai.manage')
        );

        if (!$canRead) {
            throw new AuthorizationException('Anda tidak memiliki hak akses melihat matriks komponen gaji pegawai.');
        }

        $query = Pegawai::with(['unitKerja']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        if ($request->filled('unit_kerja_id')) {
            $query->where('unit_kerja_id', $request->unit_kerja_id);
        }

        if ($request->filled('jenis_pegawai')) {
            $query->where('jenis_pegawai', $request->jenis_pegawai);
        }

        if ($request->filled('terdampak_absensi')) {
            $query->where('terdampak_absensi', filter_var($request->terdampak_absensi, FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = min(100, max(1, $request->integer('per_page', 15)));
        $sortOrder = $request->query('sort_order') === 'desc' ? 'desc' : 'asc';
        $sortBy = in_array($request->query('sort_by'), ['nama_lengkap', 'nip', 'id', 'created_at']) ? $request->query('sort_by') : 'nama_lengkap';
        $pegawais = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

        // Ambil komponen yang kolomnya aktif (untuk header & mapping kolom Tab 1)
        $kolomKomponens = MasterKomponenGaji::where('is_active', true)
            ->where('is_kolom_aktif', true)
            ->orderBy('urutan', 'asc')
            ->get();

        $pegawaiIds = $pegawais->pluck('id')->all();
        $customs = PegawaiKomponenGaji::whereIn('pegawai_id', $pegawaiIds)
            ->get()
            ->groupBy('pegawai_id');

        $items = $pegawais->getCollection()->map(function ($peg) use ($kolomKomponens, $customs) {
            $pegCustoms = $customs->get($peg->id, collect())->keyBy('komponen_gaji_id');

            $komponenValues = [];
            foreach ($kolomKomponens as $kolom) {
                $cust = $pegCustoms->get($kolom->id);
                $nominal = $cust && $cust->nominal_kustom !== null
                    ? (float) $cust->nominal_kustom
                    : (float) $kolom->nilai_default;

                $komponenValues[$kolom->kode] = [
                    'komponen_id' => $kolom->id,
                    'kode' => $kolom->kode,
                    'nama' => $kolom->nama,
                    'jenis' => $kolom->jenis,
                    'nominal' => $nominal,
                    'is_custom' => $cust && $cust->nominal_kustom !== null,
                    'is_active' => $cust ? (bool) $cust->is_active : (bool) $kolom->is_active,
                ];
            }

            return [
                'id' => $peg->id,
                'nip' => $peg->nip,
                'nama_lengkap' => $peg->nama_lengkap,
                'unit_kerja' => $peg->unitKerja?->nama,
                'jenis_pegawai' => $peg->jenis_pegawai,
                'status_kepegawaian' => $peg->status_kepegawaian,
                'is_active' => (bool) $peg->is_active,
                'terdampak_absensi' => (bool) $peg->terdampak_absensi,
                'komponen_values' => $komponenValues,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Matriks komponen gaji pegawai berhasil dimuat',
            'data' => [
                'pegawai' => $items,
                'active_columns' => $kolomKomponens,
            ],
            'meta' => [
                'current_page' => $pegawais->currentPage(),
                'per_page' => $pegawais->perPage(),
                'total' => $pegawais->total(),
                'last_page' => $pegawais->lastPage(),
            ],
        ], 200);
    }

    /**
     * PATCH /api/simpeg/payroll/komponen/{id}/toggle-kolom
     * Toggle On/Off visibilitas kolom komponen di Tab 1 (dari Tab 2)
     */
    public function toggleKolomKomponen(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $canManage = $user && $user->hasPermission('simpeg.payroll.manage');

        if (!$canManage) {
            throw new AuthorizationException('Anda tidak memiliki hak akses mengubah kolom komponen gaji.');
        }

        $komponen = MasterKomponenGaji::findOrFail($id);
        $oldValues = $komponen->getOriginal();
        $komponen->is_kolom_aktif = !$komponen->is_kolom_aktif;
        $komponen->save();
        $newValues = $komponen->getChanges();

        try {
            AuditLogService::record(
                'simpeg',
                'UPDATE',
                'simpeg_master_komponen_gaji',
                $komponen->id,
                $oldValues,
                $newValues,
                $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log toggle kolom komponen: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => "Visibilitas kolom '{$komponen->nama}' berhasil diubah menjadi " . ($komponen->is_kolom_aktif ? 'Aktif' : 'Nonaktif') . ".",
            'data' => $komponen,
        ], 200);
    }

    /**
     * PATCH /api/simpeg/payroll/pegawai/{pegawaiId}/terdampak-absensi
     * Toggle status terdampak absensi pegawai (Tab 3)
     */
    public function updateTerdampakAbsensi(Request $request, int $pegawaiId): JsonResponse
    {
        $user = $request->user();
        $canManage = $user && (
            $user->hasPermission('simpeg.payroll.manage')
            || $user->hasPermission('simpeg.pegawai.manage')
        );

        if (!$canManage) {
            throw new AuthorizationException('Anda tidak memiliki hak akses mengubah status terdampak absensi pegawai.');
        }

        $pegawai = Pegawai::findOrFail($pegawaiId);
        $validated = $request->validate([
            'terdampak_absensi' => 'required|boolean',
        ]);

        $oldValues = $pegawai->getOriginal();
        $pegawai->terdampak_absensi = $validated['terdampak_absensi'];
        $pegawai->save();
        $newValues = $pegawai->getChanges();

        try {
            AuditLogService::record(
                'simpeg',
                'UPDATE',
                'simpeg_pegawai',
                $pegawai->id,
                $oldValues,
                $newValues,
                $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log update terdampak absensi: ' . $e->getMessage());
        }

        $statusStr = $pegawai->terdampak_absensi ? 'Terdampak Absensi' : 'Bebas Absensi (Tidak Terdampak)';

        return response()->json([
            'status' => 'success',
            'message' => "Status absensi pegawai {$pegawai->nama_lengkap} diubah menjadi {$statusStr}.",
            'data' => $pegawai,
        ], 200);
    }

    /**
     * POST /api/simpeg/payroll/pegawai/batch-terdampak-absensi
     * Bulk update status terdampak absensi beberapa pegawai (Tab 3)
     */
    public function batchTerdampakAbsensi(Request $request): JsonResponse
    {
        $user = $request->user();
        $canManage = $user && (
            $user->hasPermission('simpeg.payroll.manage')
            || $user->hasPermission('simpeg.pegawai.manage')
        );

        if (!$canManage) {
            throw new AuthorizationException('Anda tidak memiliki hak akses mengubah status absensi pegawai.');
        }

        $validated = $request->validate([
            'pegawai_ids' => 'required|array|min:1',
            'pegawai_ids.*' => 'required|integer|exists:simpeg_pegawai,id',
            'terdampak_absensi' => 'required|boolean',
        ]);

        $pegawais = Pegawai::whereIn('id', $validated['pegawai_ids'])->get();
        $oldValues = $pegawais->mapWithKeys(fn ($p) => [$p->id => ['terdampak_absensi' => (bool) $p->terdampak_absensi]])->all();

        $count = Pegawai::whereIn('id', $validated['pegawai_ids'])->update([
            'terdampak_absensi' => $validated['terdampak_absensi'],
        ]);

        try {
            AuditLogService::record(
                'simpeg',
                'UPDATE',
                'simpeg_pegawai',
                null,
                ['pegawai' => $oldValues],
                ['terdampak_absensi' => $validated['terdampak_absensi'], 'count' => $pegawais->count()],
                $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log batch update terdampak absensi: ' . $e->getMessage());
        }

        $statusStr = $validated['terdampak_absensi'] ? 'Terdampak Absensi' : 'Bebas Absensi';

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil memperbarui status {$count} pegawai menjadi {$statusStr}.",
            'data' => [
                'updated_count' => $count,
                'status' => $statusStr,
            ],
        ], 200);
    }
}

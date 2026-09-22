<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\UpdatePegawaiKomponenGajiRequest;
use App\Models\Simpeg\MasterKomponenGaji;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PegawaiKomponenGaji;
use App\Services\Simpeg\PayrollCalculationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}

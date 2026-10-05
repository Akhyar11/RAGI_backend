<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Spmb\TetapkanHasilSeleksiRequest;
use App\Models\Spmb\HasilSeleksi;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Services\AuditLogService;
use App\Services\Spmb\SpmbPendaftaranService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HasilSeleksiController extends Controller
{
    public function __construct(
        private readonly SpmbPendaftaranService $pendaftaranService
    ) {}

    /**
     * Detail hasil seleksi milik satu pendaftaran.
     */
    public function show(Request $request, $id): JsonResponse
    {
        if (! $request->user()?->hasPermission('spmb.seleksi.read')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat hasil seleksi.',
            ], 403);
        }

        $pendaftaran = PendaftaranCalonMhs::findOrFail($id);
        $hasil = HasilSeleksi::where('pendaftaran_id', $pendaftaran->id)->first();

        return response()->json([
            'status' => 'success',
            'message' => 'Hasil seleksi berhasil dimuat.',
            'data' => $hasil,
        ]);
    }

    /**
     * Tetapkan hasil seleksi (kelulusan) calon mahasiswa oleh Admin SPMB.
     */
    public function tetapkan(TetapkanHasilSeleksiRequest $request, $id): JsonResponse
    {
        $pendaftaran = PendaftaranCalonMhs::findOrFail($id);
        $existing = HasilSeleksi::where('pendaftaran_id', $pendaftaran->id)->first();
        $oldValues = $existing?->getOriginal();

        $hasil = $this->pendaftaranService->tetapkanKelulusan($pendaftaran, $request->validated());

        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'update',
                tableName: $hasil->getTable(),
                recordId: $hasil->id,
                oldValues: $oldValues,
                newValues: $hasil->toArray(),
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log penetapan hasil seleksi: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Hasil seleksi berhasil ditetapkan.',
            'data' => $hasil->load('programStudiDiterima'),
        ]);
    }
}

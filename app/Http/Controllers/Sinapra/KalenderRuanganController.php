<?php

namespace App\Http\Controllers\Sinapra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sinapra\GetKalenderRuanganRequest;
use App\Services\Sinapra\KalenderRuanganService;
use Illuminate\Http\JsonResponse;

class KalenderRuanganController extends Controller
{
    protected KalenderRuanganService $kalenderService;

    public function __construct(KalenderRuanganService $kalenderService)
    {
        $this->kalenderService = $kalenderService;
    }

    /**
     * Mengambil data jadwal terpadu ketersediaan ruangan (Peminjaman SINAPRA + Kuliah SIAKAD).
     *
     * @param GetKalenderRuanganRequest $request
     * @return JsonResponse
     */
    public function index(GetKalenderRuanganRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $result = $this->kalenderService->getSchedule($filters);

        return response()->json([
            'success' => true,
            'message' => 'Data kalender ketersediaan ruangan berhasil diambil',
            'data' => $result['events'],
            'meta' => [
                'start_date' => $result['start_date'],
                'end_date' => $result['end_date'],
                'total_events' => $result['total'],
            ],
        ], 200);
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PythonFaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaceRecognitionController extends Controller
{
    public function __construct(
        protected PythonFaceService $faceService
    ) {}

    /**
     * Cek status koneksi & engine Python Face Recognition di port 8001
     * GET /api/v1/face/health
     */
    public function health(): JsonResponse
    {
        $health = $this->faceService->getHealthData();

        return response()->json([
            'status' => 'success',
            'connected' => $this->faceService->isHealthy(),
            'target_url' => config('services.face_service.url', 'http://127.0.0.1:8001'),
            'python_service' => $health,
        ]);
    }

    /**
     * Ekstraksi embedding 512 dimensi dari sebuah foto wajah
     * POST /api/v1/face/extract
     */
    public function extract(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|string',
        ]);

        $embedding = $this->faceService->extractEmbedding($request->input('image'));

        if (!$embedding) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengekstraksi wajah. Pastikan wajah terlihat jelas dan server Python di port 8001 aktif.',
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Embedding wajah berhasil diekstraksi.',
            'dimension' => count($embedding),
            'embedding' => $embedding,
        ]);
    }

    /**
     * Verifikasi kecocokan foto live terhadap enrolled embedding
     * POST /api/v1/face/verify
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'live_image' => 'required|string',
            'enrolled_embedding' => 'required|array',
            'threshold' => 'nullable|numeric|min:0|max:1',
        ]);

        $result = $this->faceService->verifyFace(
            $request->input('live_image'),
            $request->input('enrolled_embedding'),
            $request->input('threshold')
        );

        if (!$result['success']) {
            return response()->json([
                'status' => 'error',
                'message' => $result['message'] ?? 'Verifikasi biometrik gagal.',
                'data' => $result,
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['is_match'] ? 'Wajah cocok.' : 'Wajah tidak cocok.',
            'data' => $result,
        ]);
    }

    /**
     * Multi-shot enrollment dari 3-5 foto
     * POST /api/v1/face/enroll
     */
    public function enroll(Request $request): JsonResponse
    {
        $request->validate([
            'images' => 'required|array|min:1',
        ]);

        $result = $this->faceService->enrollFromImages($request->input('images'));

        if (!$result['success']) {
            return response()->json([
                'status' => 'error',
                'message' => $result['message'] ?? 'Enrollment wajah gagal.',
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Enrollment wajah berhasil diproses.',
            'data' => $result,
        ]);
    }
}

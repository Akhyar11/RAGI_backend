<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PythonFaceService
{
    protected string $baseUrl;
    protected int $timeout;
    protected float $defaultThreshold;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.face_service.url', 'http://127.0.0.1:8001'), '/');
        $this->timeout = (int) config('services.face_service.timeout', 15);
        $this->defaultThreshold = (float) config('services.face_service.min_similarity', 0.68);
    }

    /**
     * Memeriksa apakah microservice AI Python aktif dan sehat.
     */
    public function isHealthy(): bool
    {
        try {
            $response = Http::timeout(3)->get("{$this->baseUrl}/health");
            return $response->successful() && ($response->json('status') === 'healthy' || $response->json('status') === 'ok');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Ambil data health check penuh dari Python.
     */
    public function getHealthData(): array
    {
        try {
            $response = Http::timeout(3)->get("{$this->baseUrl}/health");
            if ($response->successful()) {
                return $response->json();
            }
            return [
                'status' => 'error',
                'message' => 'Status HTTP ' . $response->status(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'offline',
                'message' => 'Tidak dapat terhubung ke server Python pada ' . $this->baseUrl . ': ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Ekstraksi vektor biometrik embedding (512 dimensi) dari satu foto wajah.
     */
    public function extractEmbedding(string $base64Image): ?array
    {
        try {
            $response = Http::timeout($this->timeout)->post("{$this->baseUrl}/api/v1/extract", [
                'image' => $base64Image,
            ]);

            if ($response->successful() && $response->json('success')) {
                return $response->json('embedding');
            }

            Log::warning('Gagal ekstraksi embedding dari Python service: ' . $response->body());
            return null;
        } catch (\Throwable $e) {
            Log::error('Koneksi ke Python Face Service gagal saat extractEmbedding: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Pendaftaran wajah multi-shot (3-5 foto) untuk menghasilkan centroid embedding.
     */
    public function enrollFromImages(array $images): array
    {
        try {
            $response = Http::timeout($this->timeout)->post("{$this->baseUrl}/api/v1/enroll", [
                'images' => $images,
            ]);

            if ($response->successful() && $response->json('success')) {
                return [
                    'success' => true,
                    'centroid_embedding' => $response->json('centroid_embedding'),
                    'samples_received' => $response->json('samples_received'),
                    'message' => $response->json('message'),
                ];
            }

            $errorMsg = $response->json('detail') ?? $response->json('message') ?? 'Gagal memproses pendaftaran wajah di microservice.';
            return [
                'success' => false,
                'message' => $errorMsg,
            ];
        } catch (\Throwable $e) {
            Log::error('Koneksi ke Python Face Service gagal saat enrollFromImages: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Layanan biometrik backend sedang offline atau tidak dapat dijangkau: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Memvalidasi kecocokan foto live presensi terhadap enrolled embedding karyawan.
     * Mengembalikan skor kesamaan (similarity: 0.0 - 1.0) dan boolean is_match.
     */
    public function verifyFace(string $liveBase64Image, array $enrolledEmbedding, ?float $threshold = null): array
    {
        $thresh = $threshold ?? $this->defaultThreshold;

        try {
            $response = Http::timeout($this->timeout)->post("{$this->baseUrl}/api/v1/verify", [
                'live_image' => $liveBase64Image,
                'enrolled_embedding' => array_map('floatval', $enrolledEmbedding),
                'threshold' => $thresh,
            ]);

            if ($response->successful() && $response->json('success')) {
                return [
                    'success' => true,
                    'is_match' => (bool) $response->json('is_match'),
                    'similarity' => (float) $response->json('similarity'),
                    'distance' => (float) $response->json('distance'),
                    'threshold' => (float) $response->json('threshold'),
                    'engine' => $response->json('engine'),
                    'live_embedding' => $response->json('live_embedding'),
                ];
            }

            $errorMsg = $response->json('detail') ?? $response->json('message') ?? 'Gagal memverifikasi biometrik wajah di server.';
            return [
                'success' => false,
                'is_match' => false,
                'similarity' => 0.0,
                'message' => $errorMsg,
            ];
        } catch (\Throwable $e) {
            Log::error('Koneksi ke Python Face Service gagal saat verifyFace: ' . $e->getMessage());
            return [
                'success' => false,
                'is_match' => false,
                'similarity' => 0.0,
                'message' => 'Layanan verifikasi biometrik backend tidak dapat dihubungi: ' . $e->getMessage(),
            ];
        }
    }
}

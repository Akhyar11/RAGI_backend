<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Services\Simpeg\TridharmaDossierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TridharmaDossierController extends Controller
{
    public function __construct(
        protected TridharmaDossierService $dossierService
    ) {}

    /**
     * Dapatkan dossier / portofolio Tridharma terpadu dosen
     */
    public function getDossier(int $id, Request $request): JsonResponse
    {
        $user = $request->user();

        $canRead = $user->isAdmin() ||
                   $user->hasPermission('simpeg.pegawai.read') ||
                   $user->hasPermission('simpeg.kinerja.read') ||
                   $user->hasPermission('simpeg.kinerja.evaluate') ||
                   ($user->pegawai && $user->pegawai->id === $id);

        if (!$canRead) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat portofolio Tridharma pegawai ini.',
            ], 403);
        }

        $dossier = $this->dossierService->getDossier($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Portofolio Tridharma terpadu berhasil dimuat.',
            'data' => $dossier,
        ]);
    }
}

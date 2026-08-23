<?php

namespace App\Http\Controllers\Api\Siakad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Siakad\NeoFeederService;
use App\Services\Siakad\NeoFeederSyncService;
use App\Models\Siakad\FeederSyncLog;
use App\Models\Siakad\FeederMapping;

class FeederSyncController extends Controller
{
    protected NeoFeederService $feederService;
    protected NeoFeederSyncService $syncService;

    public function __construct(NeoFeederService $feederService, NeoFeederSyncService $syncService)
    {
        $this->feederService = $feederService;
        $this->syncService = $syncService;
    }

    public function getConfig()
    {
        $config = $this->feederService->getConfig();
        $config['password'] = '******'; // Masked

        return response()->json([
            'status' => 'success',
            'data' => $config
        ]);
    }

    public function saveConfig(Request $request)
    {
        $request->validate([
            'url' => 'required|string',
            'username' => 'required|string',
            'password' => 'nullable|string',
        ]);

        $config = $this->feederService->saveConfig(
            $request->url,
            $request->username,
            $request->password
        );
        $config['password'] = '******';

        return response()->json([
            'status' => 'success',
            'message' => 'Konfigurasi Neo Feeder berhasil disimpan',
            'data' => $config
        ]);
    }

    public function getToken()
    {
        try {
            $token = $this->feederService->getToken();
            return response()->json([
                'status' => 'success',
                'message' => 'Berhasil mendapatkan token Neo Feeder',
                'data' => ['token' => $token]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function triggerSync(Request $request)
    {
        $request->validate([
            'entity_type' => 'required|in:mahasiswa,biodata_mahasiswa,riwayat_pendidikan_mahasiswa,dosen,mata_kuliah,kelas,penugasan_dosen',
        ]);

        $entity = $request->entity_type;
        $userId = $request->user()?->id;

        try {
            $log = match ($entity) {
                'mahasiswa' => $this->syncService->syncBatchMahasiswa($userId),
                'biodata_mahasiswa' => $this->syncService->syncBatchBiodataMahasiswa($userId),
                'riwayat_pendidikan_mahasiswa' => $this->syncService->syncBatchRiwayatPendidikanMahasiswa($userId),
                'dosen' => $this->syncService->syncBatchDosen($userId),
                'mata_kuliah' => $this->syncService->syncBatchMataKuliah($userId),
                'kelas' => $this->syncService->syncBatchKelasNilai($userId),
                'penugasan_dosen' => $this->syncService->syncBatchPenugasanDosen($userId),
            };

            return response()->json([
                'status' => 'success',
                'message' => "Sinkronisasi {$entity} ke Neo Feeder PDDikti selesai",
                'data' => $log
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getLogs(Request $request)
    {
        $logs = FeederSyncLog::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'status' => 'success',
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ]
        ]);
    }

    public function getMappings(Request $request)
    {
        $query = FeederMapping::query();

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }

        if ($request->filled('sync_status')) {
            $query->where('sync_status', $request->sync_status);
        }

        $mappings = $query->orderBy('updated_at', 'desc')->paginate($request->integer('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $mappings->items(),
            'meta' => [
                'current_page' => $mappings->currentPage(),
                'per_page' => $mappings->perPage(),
                'total' => $mappings->total(),
            ]
        ]);
    }
}

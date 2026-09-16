<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\StorePengajuanCutiRequest;
use App\Models\Simpeg\MasterJenisCuti;
use App\Models\Simpeg\PengajuanCuti;
use App\Services\Storage\FileStorageService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CutiController extends Controller
{
    public function __construct(private FileStorageService $files) {}
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.cuti.read') && 
            !$user->hasPermission('simpeg.cuti.request') && 
            !$user->hasPermission('simpeg.cuti.approve') && 
            !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk melihat Pengajuan Cuti.'
            ], 403);
        }

        $query = PengajuanCuti::with(['pegawai', 'approver', 'masterJenisCuti']);

        if ($request->has('pegawai_id')) {
            $query->where('pegawai_id', $request->pegawai_id);
        } elseif (!$user->isAdmin() && !$user->hasPermission('simpeg.cuti.manage') && !$user->hasPermission('simpeg.cuti.approve')) {
            $pegId = $user->pegawai?->id;
            if ($pegId) {
                $query->where('pegawai_id', $pegId);
            }
        }

        if ($request->filled('master_jenis_cuti_id')) {
            $query->where('master_jenis_cuti_id', $request->master_jenis_cuti_id);
        }

        if ($request->has('status_approval') && $request->status_approval !== '') {
            $query->where('status_approval', $request->status_approval);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('alasan', 'like', "%{$search}%")
                  ->orWhereHas('pegawai', function ($qp) use ($search) {
                      $qp->where('nama_lengkap', 'like', "%{$search}%")
                         ->orWhere('nip', 'like', "%{$search}%");
                  })
                  ->orWhereHas('masterJenisCuti', function ($qm) use ($search) {
                      $qm->where('nama', 'like', "%{$search}%");
                  });
            });
        }

        // Sorting
        $sortBy = in_array($request->sort_by, ['tanggal_mulai', 'tanggal_selesai', 'jumlah_hari', 'created_at']) 
            ? $request->sort_by 
            : 'created_at';
        $sortOrder = strtolower($request->sort_dir ?? $request->sort_order ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        if ($request->has('page') || $request->has('per_page') || $request->has('limit')) {
            $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
            $paginated = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Data retrieved successfully',
                'data' => $paginated->items(),
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

        $cuti = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => $cuti,
        ]);
    }

    public function store(StorePengajuanCutiRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.cuti.create') && 
            !$user->hasPermission('simpeg.cuti.request') && 
            !$user->hasPermission('simpeg.cuti.approve') && 
            !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk mengajukan Cuti.'
            ], 403);
        }

        $validated = $request->validated();
        $master = MasterJenisCuti::findOrFail($validated['master_jenis_cuti_id']);

        if ($master->lampiran_wajib && !$request->hasFile('file') && empty($validated['file_pendukung'])) {
            return response()->json([
                'status' => 'error',
                'message' => "Surat / berkas lampiran pendukung wajib diunggah untuk jenis izin: {$master->nama}.",
                'errors' => [
                    'file' => ["Surat / berkas lampiran pendukung wajib diunggah untuk jenis izin: {$master->nama}."],
                ],
            ], 422);
        }

        // Handle auto calculation for fixed duration leave
        if ($master->tipe_durasi === 'ditetapkan') {
            $durasi = max(1, (int) $master->durasi_hari);
            $start = Carbon::parse($validated['tanggal_mulai']);
            $end = (clone $start)->addDays($durasi - 1);
            $validated['tanggal_selesai'] = $end->toDateString();
            $validated['jumlah_hari'] = $durasi;
        } else {
            if (empty($validated['tanggal_selesai'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tanggal selesai cuti wajib diisi untuk jenis cuti fleksibel.',
                    'errors' => ['tanggal_selesai' => ['Tanggal selesai wajib diisi.']],
                ], 422);
            }
            if (empty($validated['jumlah_hari'])) {
                $start = Carbon::parse($validated['tanggal_mulai']);
                $end = Carbon::parse($validated['tanggal_selesai']);
                $validated['jumlah_hari'] = $start->diffInDays($end) + 1;
            }
        }

        // Set safe fallback for legacy column jenis_cuti
        $enumFallbacks = [
            'CUTI_TAHUNAN' => 'tahunan',
            'IZIN_SAKIT' => 'sakit',
            'CUTI_MELAHIRKAN' => 'melahirkan',
            'CUTI_ALASAN_PENTING' => 'alasan_penting',
            'CUTI_BESAR' => 'besar',
        ];
        $validated['jenis_cuti'] = $enumFallbacks[$master->kode] ?? 'alasan_penting';

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $validated['file_pendukung'] = $this->files->store($file, 'simpeg/cuti_lampiran', private: true);
        }

        unset($validated['file']);
        $cuti = PengajuanCuti::create($validated);
        $cuti->load(['pegawai', 'masterJenisCuti']);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan cuti berhasil dibuat',
            'data' => $cuti,
        ], 201);
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.cuti.update') && 
            !$user->hasPermission('simpeg.cuti.approve') && 
            !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk menyetujui / menolak Cuti.'
            ], 403);
        }

        $validated = $request->validate([
            'status_approval' => 'required|in:pending,approved,rejected',
            'catatan_approval' => 'nullable|string',
        ]);

        $cuti = PengajuanCuti::with(['pegawai', 'masterJenisCuti'])->findOrFail($id);
        $cuti->update([
            'status_approval' => $validated['status_approval'],
            'catatan_approval' => $validated['catatan_approval'] ?? null,
            'approved_by' => $user?->id,
        ]);

        // Send WhatsApp & Email Notification
        $pegawaiNama = $cuti->pegawai ? $cuti->pegawai->nama_lengkap : 'Pegawai';
        $phone = $cuti->pegawai->telepon ?? '08123456789';
        $statusUpper = strtoupper($cuti->status_approval);
        $msg = "Halo {$pegawaiNama}, pengajuan cuti Anda tanggal {$cuti->tanggal_mulai} s/d {$cuti->tanggal_selesai} telah DI-{$statusUpper} oleh SDM Kampus.";

        $waLog = \App\Services\Notification\CampusNotificationService::sendWhatsApp($phone, $msg);
        $mailLog = \App\Services\Notification\CampusNotificationService::sendEmail("{$pegawaiNama}@campus.ac.id", "Status Pengajuan Cuti {$statusUpper}", $msg);

        return response()->json([
            'status' => 'success',
            'message' => 'Status pengajuan cuti berhasil diperbarui',
            'data' => $cuti,
            'notifications' => [
                'whatsapp' => $waLog,
                'email' => $mailLog,
            ],
        ]);
    }
}

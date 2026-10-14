<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use App\Models\Siakad\CutiMahasiswa;
use App\Models\Siakad\Khs;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\StatusAkademikLog;
use App\Models\Siakad\AbsensiMahasiswa;
use App\Models\Siakad\Pertemuan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class StatusAkademikController extends Controller
{
    // --- CUTI MAHASISWA (pengajuan -> approve, blokir KRS via status) ---
    public function listCuti(Request $request)
    {
        Gate::authorize('siakad.mahasiswa.read');
        $query = CutiMahasiswa::with(['mahasiswa.programStudi']);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('mahasiswa_id')) $query->where('mahasiswa_id', $request->mahasiswa_id);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('mahasiswa', fn($q) => $q->where('nama_lengkap', 'like', "%{$s}%")->orWhere('nim', 'like', "%{$s}%"));
        }
        $allowedSort = ['created_at', 'updated_at', 'id'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);
        $perPage = min(100, $request->integer('per_page', 15));
        $data = $query->paginate($perPage);
        return response()->json([
            'status' => 'success', 'message' => 'Data cuti mahasiswa berhasil dimuat',
            'data' => $data->items(),
            'meta' => ['current_page' => $data->currentPage(), 'per_page' => $data->perPage(), 'total' => $data->total(), 'last_page' => $data->lastPage()],
        ]);
    }

    public function storeCuti(Request $request)
    {
        Gate::authorize('siakad.mahasiswa.manage');
        $validated = $request->validate([
            'mahasiswa_id' => 'required|exists:siakad_mahasiswa,id',
            'tahun_akademik_id' => 'required|exists:siakad_tahun_akademik,id',
            'alasan' => 'required|string',
            'file_surat' => 'nullable|string|max:255',
        ]);
        $cuti = CutiMahasiswa::create(array_merge($validated, ['status' => 'pending']));
        return response()->json(['status' => 'success', 'message' => 'Pengajuan cuti berhasil dibuat', 'data' => $cuti], 201);
    }

    public function prosesCuti(Request $request, $id)
    {
        Gate::authorize('siakad.mahasiswa.manage');
        $request->validate(['status' => 'required|in:disetujui,ditolak', 'alasan' => 'nullable|string']);
        return DB::transaction(function () use ($request, $id) {
            $cuti = CutiMahasiswa::with('mahasiswa')->findOrFail($id);
            $cuti->update(['status' => $request->status, 'diproses_oleh' => $request->user()?->id]);
            if ($request->status === 'disetujui' && $cuti->mahasiswa) {
                $cuti->mahasiswa->update(['status' => 'cuti']);
            }
            return response()->json(['status' => 'success', 'message' => "Pengajuan cuti {$request->status}.", 'data' => $cuti]);
        });
    }

    // --- RIWAYAT STATUS ---
    public function listStatusLog(Request $request)
    {
        Gate::authorize('siakad.mahasiswa.read');
        $query = StatusAkademikLog::with('mahasiswa');
        if ($request->filled('mahasiswa_id')) $query->where('mahasiswa_id', $request->mahasiswa_id);
        $allowedSort = ['created_at', 'updated_at', 'id'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);
        $perPage = min(100, $request->integer('per_page', 15));
        $data = $query->paginate($perPage);
        return response()->json([
            'status' => 'success', 'data' => $data->items(),
            'meta' => ['current_page' => $data->currentPage(), 'per_page' => $data->perPage(), 'total' => $data->total(), 'last_page' => $data->lastPage()],
        ]);
    }

    // --- KHS (list + kunci/pengesahan) ---
    public function listKhs(Request $request)
    {
        Gate::authorize('siakad.nilai.read');
        $query = Khs::with(['mahasiswa.programStudi', 'tahunAkademik']);
        if ($request->filled('mahasiswa_id')) $query->where('mahasiswa_id', $request->mahasiswa_id);
        if ($request->filled('tahun_akademik_id')) $query->where('tahun_akademik_id', $request->tahun_akademik_id);
        if ($request->filled('program_studi_id')) {
            $prodiId = $request->program_studi_id;
            $query->whereHas('mahasiswa', fn($q) => $q->where('program_studi_id', $prodiId));
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('mahasiswa', fn($q) => $q->where('nama_lengkap', 'like', "%{$s}%")->orWhere('nim', 'like', "%{$s}%"));
        }
        $allowedSort = ['created_at', 'updated_at', 'id'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);
        $perPage = min(100, $request->integer('per_page', 15));
        $data = $query->paginate($perPage);
        return response()->json([
            'status' => 'success', 'data' => $data->items(),
            'meta' => ['current_page' => $data->currentPage(), 'per_page' => $data->perPage(), 'total' => $data->total(), 'last_page' => $data->lastPage()],
        ]);
    }

    public function lockKhs(Request $request, $id)
    {
        Gate::authorize('siakad.nilai.manage');
        $request->validate(['is_locked' => 'required|boolean']);
        $khs = Khs::findOrFail($id);
        $khs->update(['is_locked' => $request->boolean('is_locked')]);
        \App\Services\AuditLogService::record(module: 'SIAKAD', action: $khs->is_locked ? 'approve' : 'update', tableName: 'siakad_khs', recordId: $khs->id, request: $request);
        return response()->json(['status' => 'success', 'message' => $khs->is_locked ? 'KHS dikunci dan disahkan.' : 'Kunci KHS dibuka.', 'data' => $khs]);
    }

    /**
     * Pengesahan massal KHS per periode (+prodi opsional), pengganti
     * pengesahan satu-per-satu ala cetak massal legacy.
     */
    public function bulkLockKhs(Request $request)
    {
        Gate::authorize('siakad.nilai.manage');
        $validated = $request->validate([
            'tahun_akademik_id' => 'required|exists:siakad_tahun_akademik,id',
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'is_locked' => 'required|boolean',
        ]);

        $query = Khs::where('tahun_akademik_id', $validated['tahun_akademik_id']);
        if (!empty($validated['program_studi_id'])) {
            $query->whereHas('mahasiswa', fn($q) => $q->where('program_studi_id', $validated['program_studi_id']));
        }
        $oldValues = ['is_locked_sebelum' => (clone $query)->where('is_locked', !$validated['is_locked'])->count()];
        $count = $query->update(['is_locked' => $validated['is_locked']]);

        try {
            \App\Services\AuditLogService::record(
                module: 'SIAKAD',
                action: $validated['is_locked'] ? 'approve' : 'update',
                tableName: 'siakad_khs',
                recordId: null,
                oldValues: $oldValues,
                newValues: array_merge($validated, ['affected' => $count]),
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log bulkLockKhs: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => $validated['is_locked']
                ? "Pengesahan massal selesai: {$count} KHS dikunci."
                : "Kunci massal dibuka: {$count} KHS.",
            'data' => ['affected' => $count],
        ]);
    }

    // --- REKAP ABSENSI (syarat UAS 75%) ---
    public function rekapAbsensi(Request $request, $kelasId)
    {
        Gate::authorize('siakad.nilai.read');
        $pertemuanIds = Pertemuan::where('kelas_id', $kelasId)->pluck('id');
        $total = $pertemuanIds->count();
        $rows = AbsensiMahasiswa::with('mahasiswa')
            ->whereIn('pertemuan_id', $pertemuanIds)
            ->get()
            ->groupBy('mahasiswa_id')
            ->map(function ($items) use ($total) {
                $hadir = $items->where('status', 'hadir')->count();
                $pct = $total > 0 ? round($hadir / $total * 100, 1) : 0;
                return [
                    'mahasiswa' => $items->first()?->mahasiswa,
                    'total_pertemuan' => $total,
                    'hadir' => $hadir,
                    'sakit' => $items->where('status', 'sakit')->count(),
                    'izin' => $items->where('status', 'izin')->count(),
                    'alfa' => $items->where('status', 'alfa')->count(),
                    'persentase_hadir' => $pct,
                    'eligible_uas' => $pct >= 75,
                ];
            })->values();
        return response()->json(['status' => 'success', 'data' => $rows]);
    }
}

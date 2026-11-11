<?php

namespace App\Http\Controllers\Sippm;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Models\Sippm\SkemaKegiatan;
use App\Models\Sippm\PeriodeHibah;

class MasterSippmController extends Controller
{
    public function indexSkema()
    {
        Gate::authorize('sippm.skema.read');

        $skema = SkemaKegiatan::orderBy('created_at', 'desc')->get();
        return response()->json([
            'status' => 'success',
            'data' => $skema,
        ]);
    }

    public function storeSkema(Request $request)
    {
        Gate::authorize('sippm.skema.create');

        if ($request->has('nama_skema') && !$request->has('nama')) {
            $request->merge(['nama' => $request->input('nama_skema')]);
        }
        if ($request->has('kode_skema') && !$request->has('kode')) {
            $request->merge(['kode' => $request->input('kode_skema')]);
        }
        if ($request->has('jenis_kegiatan') && !$request->has('tipe')) {
            $request->merge(['tipe' => $request->input('jenis_kegiatan')]);
        }
        if ($request->has('maksimal_dana') && !$request->has('maksimal_anggaran')) {
            $request->merge(['maksimal_anggaran' => $request->input('maksimal_dana')]);
        }

        $validated = $request->validate([
            'kode' => 'required|string|max:50|unique:sippm_skema_kegiatan,kode',
            'nama' => 'required|string|max:255',
            'tipe' => 'required|in:penelitian,pengabdian',
            'sumber_dana' => 'required|in:internal,dikti_bima,mitra_industri,mandiri',
            'maksimal_anggaran' => 'nullable|numeric|min:0',
            'deskripsi' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $skema = SkemaKegiatan::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Skema kegiatan berhasil ditambahkan.',
            'data' => $skema,
        ], 201);
    }

    public function indexPeriode()
    {
        Gate::authorize('sippm.periode.read');

        $periode = PeriodeHibah::orderBy('tahun_anggaran', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $periode,
        ]);
    }

    public function storePeriode(Request $request)
    {
        Gate::authorize('sippm.periode.create');
        if ($request->has('nama_periode') && !$request->has('nama_gelombang')) {
            $request->merge(['nama_gelombang' => $request->input('nama_periode')]);
        }
        if ($request->has('tgl_buka') && !$request->has('tgl_buka_proposal')) {
            $request->merge(['tgl_buka_proposal' => $request->input('tgl_buka')]);
        }
        if ($request->has('tgl_tutup') && !$request->has('tgl_tutup_proposal')) {
            $request->merge(['tgl_tutup_proposal' => $request->input('tgl_tutup')]);
        }

        $validated = $request->validate([
            'tahun_anggaran' => 'required|string|max:20',
            'nama_gelombang' => 'required|string|max:100',
            'tgl_buka_proposal' => 'required|date',
            'tgl_tutup_proposal' => 'required|date|after_or_equal:tgl_buka_proposal',
            'tgl_tutup_monev' => 'nullable|date',
            'tgl_tutup_laporan' => 'nullable|date',
            'is_active' => 'boolean',
        ]);

        $periode = PeriodeHibah::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Gelombang periode hibah berhasil dibuat.',
            'data' => $periode,
        ], 201);
    }

    public function updateSkema(Request $request, $id)
    {
        Gate::authorize('sippm.skema.update');

        $skema = SkemaKegiatan::findOrFail($id);

        if ($request->has('nama_skema') && !$request->has('nama')) {
            $request->merge(['nama' => $request->input('nama_skema')]);
        }
        if ($request->has('kode_skema') && !$request->has('kode')) {
            $request->merge(['kode' => $request->input('kode_skema')]);
        }
        if ($request->has('jenis_kegiatan') && !$request->has('tipe')) {
            $request->merge(['tipe' => $request->input('jenis_kegiatan')]);
        }
        if ($request->has('maksimal_dana') && !$request->has('maksimal_anggaran')) {
            $request->merge(['maksimal_anggaran' => $request->input('maksimal_dana')]);
        }

        $validated = $request->validate([
            'kode' => 'sometimes|required|string|max:50|unique:sippm_skema_kegiatan,kode,' . $id,
            'nama' => 'sometimes|required|string|max:255',
            'tipe' => 'sometimes|required|in:penelitian,pengabdian',
            'sumber_dana' => 'sometimes|required|in:internal,dikti_bima,mitra_industri,mandiri',
            'maksimal_anggaran' => 'nullable|numeric|min:0',
            'deskripsi' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $skema->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Skema kegiatan berhasil diperbarui.',
            'data' => $skema,
        ]);
    }

    public function destroySkema($id)
    {
        Gate::authorize('sippm.skema.delete');

        $skema = SkemaKegiatan::findOrFail($id);
        $skema->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Skema kegiatan berhasil dihapus.',
        ]);
    }

    public function updatePeriode(Request $request, $id)
    {
        Gate::authorize('sippm.periode.update');

        $periode = PeriodeHibah::findOrFail($id);

        if ($request->has('nama_periode') && !$request->has('nama_gelombang')) {
            $request->merge(['nama_gelombang' => $request->input('nama_periode')]);
        }
        if ($request->has('tgl_buka') && !$request->has('tgl_buka_proposal')) {
            $request->merge(['tgl_buka_proposal' => $request->input('tgl_buka')]);
        }
        if ($request->has('tgl_tutup') && !$request->has('tgl_tutup_proposal')) {
            $request->merge(['tgl_tutup_proposal' => $request->input('tgl_tutup')]);
        }

        $validated = $request->validate([
            'tahun_anggaran' => 'sometimes|required|string|max:20',
            'nama_gelombang' => 'sometimes|required|string|max:100',
            'tgl_buka_proposal' => 'sometimes|required|date',
            'tgl_tutup_proposal' => 'sometimes|required|date|after_or_equal:tgl_buka_proposal',
            'tgl_tutup_monev' => 'nullable|date',
            'tgl_tutup_laporan' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        $periode->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Periode hibah berhasil diperbarui.',
            'data' => $periode,
        ]);
    }

    public function destroyPeriode($id)
    {
        Gate::authorize('sippm.periode.delete');

        $periode = PeriodeHibah::findOrFail($id);
        $periode->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Periode hibah berhasil dihapus.',
        ]);
    }

    public function getUpmMetrics(Request $request, \App\Services\Sippm\SippmIntegrationService $integrationService)
    {
        $tahun = $request->get('tahun', date('Y'));
        $metrics = $integrationService->getUpmIkuMetrics((int) $tahun);

        return response()->json([
            'status' => 'success',
            'data' => $metrics,
        ]);
    }

    public function processDisbursementCallback(Request $request, $pencairanId, \App\Services\Sippm\SippmIntegrationService $integrationService)
    {
        $pencairan = \App\Models\Sippm\PencairanDanaHibah::findOrFail($pencairanId);
        $result = $integrationService->processSikeuDisbursement($pencairan);

        return response()->json([
            'status' => 'success',
            'message' => 'Callback pencairan dana dari SIKEU berhasil diproses.',
            'data' => $result,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Sippm;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Models\Sippm\ReviewerKegiatan;
use App\Models\Sippm\ProposalKegiatan;
use App\Services\Sippm\ReviewerService;

class ReviewerKegiatanController extends Controller
{
    protected $reviewerService;

    public function __construct(ReviewerService $reviewerService)
    {
        $this->reviewerService = $reviewerService;
    }

    public function myAssignedProposals(Request $request)
    {
        Gate::authorize('sippm.reviewer.read');

        $reviewerPegawaiId = $request->input('reviewer_pegawai_id') ?? $request->user()?->pegawai_id;
        $perPage = min(100, $request->integer('per_page', 15));
        $sortOrder = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';

        $query = ReviewerKegiatan::with(['proposal.skema', 'proposal.ketuaPegawai', 'penilaian']);

        if ($reviewerPegawaiId) {
            $query->where('reviewer_pegawai_id', $reviewerPegawaiId);
        }

        $assigned = $query->orderBy('tgl_penugasan', $sortOrder)->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar penugasan reviewer berhasil dimuat.',
            'data' => $assigned->items(),
            'meta' => [
                'current_page' => $assigned->currentPage(),
                'per_page' => $assigned->perPage(),
                'total' => $assigned->total(),
                'last_page' => $assigned->lastPage(),
            ],
        ], 200);
    }

    public function submitPenilaian(Request $request, $reviewerId)
    {
        Gate::authorize('sippm.reviewer.evaluate');

        $reviewer = ReviewerKegiatan::find($reviewerId);
        if (!$reviewer) {
            $reviewer = ReviewerKegiatan::where('proposal_kegiatan_id', $reviewerId)->firstOrFail();
        }

        // Normalize frontend aliases
        $input = $request->all();
        if (isset($input['skor_rab']) && !isset($input['skor_rencana_anggaran'])) {
            $input['skor_rencana_anggaran'] = $input['skor_rab'];
        }
        if (isset($input['catatan_reviewer']) && !isset($input['catatan_revisi'])) {
            $input['catatan_revisi'] = $input['catatan_reviewer'];
        }
        if (isset($input['rekomendasi'])) {
            if ($input['rekomendasi'] === 'terima') {
                $input['rekomendasi'] = 'diterima';
            } elseif ($input['rekomendasi'] === 'tolak') {
                $input['rekomendasi'] = 'ditolak';
            }
        }
        $request->merge($input);

        $validated = $request->validate([
            'skor_rekam_jejak' => 'required|numeric|min:0|max:100',
            'skor_substansi' => 'required|numeric|min:0|max:100',
            'skor_rencana_anggaran' => 'required|numeric|min:0|max:100',
            'skor_total' => 'nullable|numeric|min:0|max:100',
            'rekomendasi' => 'required|in:diterima,revisi,ditolak',
            'catatan_revisi' => 'nullable|string',
            'file_penilaian' => 'nullable|string',
        ]);

        $penilaian = $this->reviewerService->submitPenilaian($reviewer, $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Penilaian reviewer berhasil disimpan.',
            'data' => $penilaian,
        ]);
    }

    public function finalizeDecision(Request $request, $proposalId)
    {
        Gate::authorize('sippm.proposal.finalize');

        $proposal = ProposalKegiatan::findOrFail($proposalId);

        $validated = $request->validate([
            'status' => 'required|in:lolos,ditolak,revisi',
            'anggaran_disetujui' => 'nullable|numeric|min:0',
        ]);

        $finalized = $this->reviewerService->finalizeProposal(
            $proposal, 
            $validated['status'], 
            $validated['anggaran_disetujui'] ?? null
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Keputusan akhir LPPM berhasil disimpan.',
            'data' => $finalized,
        ]);
    }
}

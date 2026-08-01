<?php

namespace App\Http\Controllers\Sippm;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
        $reviewerPegawaiId = $request->input('reviewer_pegawai_id') ?? $request->user()?->pegawai_id;

        $query = ReviewerKegiatan::with(['proposal.skema', 'proposal.ketuaPegawai', 'penilaian']);

        if ($reviewerPegawaiId) {
            $query->where('reviewer_pegawai_id', $reviewerPegawaiId);
        }

        $assigned = $query->orderBy('tgl_penugasan', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $assigned,
        ]);
    }

    public function submitPenilaian(Request $request, $reviewerId)
    {
        $reviewer = ReviewerKegiatan::findOrFail($reviewerId);

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

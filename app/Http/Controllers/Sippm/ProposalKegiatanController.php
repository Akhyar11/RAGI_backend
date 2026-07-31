<?php

namespace App\Http\Controllers\Sippm;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sippm\ProposalKegiatan;
use App\Services\Sippm\ProposalService;

class ProposalKegiatanController extends Controller
{
    protected $proposalService;

    public function __construct(ProposalService $proposalService)
    {
        $this->proposalService = $proposalService;
    }

    public function index(Request $request)
    {
        $query = ProposalKegiatan::with(['skema', 'periode', 'ketuaPegawai', 'anggota']);

        if ($request->has('ketua_pegawai_id')) {
            $query->where('ketua_pegawai_id', $request->ketua_pegawai_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('skema_id')) {
            $query->where('skema_id', $request->skema_id);
        }

        $proposals = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $proposals,
        ]);
    }

    public function show($id)
    {
        $proposal = ProposalKegiatan::with([
            'skema', 'periode', 'ketuaPegawai', 'anggota.pegawai',
            'reviewer.reviewerPegawai.user', 'reviewer.penilaian',
            'kontrak.pencairanDana', 'kontrak.laporan',
            'publikasi', 'hkiDanBuku'
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $proposal,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'periode_id' => 'required|exists:periode_hibah,id',
            'skema_id' => 'required|exists:skema_kegiatan,id',
            'ketua_pegawai_id' => 'required|exists:pegawai,id',
            'mitra_kerjasama_id' => 'nullable|integer',
            'judul' => 'required|string',
            'abstrak' => 'required|string',
            'rumpun_ilmu' => 'required|string|max:150',
            'target_tkt' => 'nullable|integer|min:1|max:9',
            'anggaran_diajukan' => 'required|numeric|min:0',
            'file_proposal' => 'required|string',
            'anggota' => 'nullable|array',
        ]);

        $proposal = $this->proposalService->createProposal($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Proposal berhasil didaftarkan.',
            'data' => $proposal,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $proposal = ProposalKegiatan::findOrFail($id);

        $validated = $request->validate([
            'judul' => 'nullable|string',
            'abstrak' => 'nullable|string',
            'rumpun_ilmu' => 'nullable|string',
            'target_tkt' => 'nullable|integer|min:1|max:9',
            'anggaran_diajukan' => 'nullable|numeric|min:0',
            'file_proposal' => 'nullable|string',
        ]);

        $updated = $this->proposalService->updateProposal($proposal, $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Data proposal berhasil diperbarui.',
            'data' => $updated,
        ]);
    }

    public function submit($id)
    {
        $proposal = ProposalKegiatan::findOrFail($id);
        $submitted = $this->proposalService->submitProposal($proposal);

        return response()->json([
            'status' => 'success',
            'message' => 'Proposal resmi diajukan ke LPPM.',
            'data' => $submitted,
        ]);
    }

    public function assignReviewer(Request $request, $id)
    {
        $proposal = ProposalKegiatan::findOrFail($id);

        $validated = $request->validate([
            'reviewer_pegawai_id' => 'required|exists:pegawai,id',
        ]);

        $reviewer = $this->proposalService->assignReviewer($proposal, $validated['reviewer_pegawai_id']);

        return response()->json([
            'status' => 'success',
            'message' => 'Reviewer berhasil ditugaskan.',
            'data' => $reviewer,
        ]);
    }
}

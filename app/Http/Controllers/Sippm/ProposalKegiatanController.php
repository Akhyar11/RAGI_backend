<?php

namespace App\Http\Controllers\Sippm;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        Gate::authorize('sippm.proposal.read');

        $query = ProposalKegiatan::with(['skema', 'periode', 'ketuaPegawai', 'anggota.pegawai']);

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
        Gate::authorize('sippm.proposal.read');

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
        Gate::authorize('sippm.proposal.create');

        if ($request->has('periode_hibah_id') && !$request->has('periode_id')) {
            $request->merge(['periode_id' => $request->input('periode_hibah_id')]);
        }
        if ($request->has('skema_kegiatan_id') && !$request->has('skema_id')) {
            $request->merge(['skema_id' => $request->input('skema_kegiatan_id')]);
        }
        if ($request->has('dana_diusulkan') && !$request->has('anggaran_diajukan')) {
            $request->merge(['anggaran_diajukan' => $request->input('dana_diusulkan')]);
        }
        if (!$request->has('file_proposal') || empty($request->input('file_proposal'))) {
            $request->merge(['file_proposal' => 'dokumen_proposal_usulan.pdf']);
        }
        if (!$request->has('ketua_pegawai_id') && $request->user()?->pegawai_id) {
            $request->merge(['ketua_pegawai_id' => $request->user()->pegawai_id]);
        }

        $validated = $request->validate([
            'periode_id' => 'required|exists:sippm_periode_hibah,id',
            'skema_id' => 'required|exists:sippm_skema_kegiatan,id',
            'ketua_pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'mitra_kerjasama_id' => 'nullable|integer',
            'mata_kuliah_id' => 'nullable|integer',
            'judul' => 'required|string',
            'abstrak' => 'required|string',
            'rumpun_ilmu' => 'required|string|max:150',
            'target_tkt' => 'nullable|integer|min:1|max:9',
            'anggaran_diajukan' => 'required|numeric|min:0',
            'file_proposal' => 'nullable|string',
            'anggota' => 'nullable|array',
            'anggota.*.jenis_tim' => 'nullable|string|in:dosen,tendik,mahasiswa,dosen_eksternal,eksternal',
            'anggota.*.jenis_anggota' => 'nullable|string',
            'anggota.*.pegawai_id' => 'nullable|integer',
            'anggota.*.mahasiswa_id' => 'nullable|integer',
            'anggota.*.mata_kuliah_id' => 'nullable|integer',
            'anggota.*.nama_eksternal' => 'nullable|string|max:255',
            'anggota.*.instansi_eksternal' => 'nullable|string|max:255',
            'anggota.*.nidn_eksternal' => 'nullable|string|max:50',
            'anggota.*.peran_dalam_tim' => 'nullable|string|max:100',
            'anggota.*.tugas_kegiatan' => 'nullable|string',
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
        Gate::authorize('sippm.proposal.update');

        $proposal = ProposalKegiatan::findOrFail($id);

        if ($request->has('dana_diusulkan') && !$request->has('anggaran_diajukan')) {
            $request->merge(['anggaran_diajukan' => $request->input('dana_diusulkan')]);
        }
        if ($request->has('dana_disetujui') && !$request->has('anggaran_disetujui')) {
            $request->merge(['anggaran_disetujui' => $request->input('dana_disetujui')]);
        }

        $validated = $request->validate([
            'judul' => 'nullable|string',
            'abstrak' => 'nullable|string',
            'rumpun_ilmu' => 'nullable|string',
            'target_tkt' => 'nullable|integer|min:1|max:9',
            'anggaran_diajukan' => 'nullable|numeric|min:0',
            'anggaran_disetujui' => 'nullable|numeric|min:0',
            'file_proposal' => 'nullable|string',
            'mata_kuliah_id' => 'nullable|integer',
            'status' => 'nullable|string',
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
        Gate::authorize('sippm.proposal.update');

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
        Gate::authorize('sippm.reviewer.assign');

        $proposal = ProposalKegiatan::findOrFail($id);

        $validated = $request->validate([
            'reviewer_pegawai_id' => 'required|exists:simpeg_pegawai,id',
        ]);

        $reviewer = $this->proposalService->assignReviewer($proposal, $validated['reviewer_pegawai_id']);

        return response()->json([
            'status' => 'success',
            'message' => 'Reviewer berhasil ditugaskan.',
            'data' => $reviewer,
        ]);
    }

    /**
     * Get active courses for a specific student from SIAKAD for Grade Conversion integration.
     */
    public function getActiveMataKuliahMahasiswa($mahasiswaId)
    {
        Gate::authorize('sippm.proposal.read');

        $mataKuliah = $this->proposalService->getActiveMataKuliahForMahasiswa((int) $mahasiswaId);

        return response()->json([
            'status' => 'success',
            'data' => $mataKuliah,
        ]);
    }

    /**
     * Get active Tendik reference list from SIMPEG.
     */
    public function getTendikReference()
    {
        Gate::authorize('sippm.proposal.read');

        $tendik = $this->proposalService->getTendikReference();

        return response()->json([
            'status' => 'success',
            'data' => $tendik,
        ]);
    }

    /**
     * Get active Dosen reference list from SIMPEG.
     */
    public function getDosenReference()
    {
        Gate::authorize('sippm.proposal.read');

        $dosen = $this->proposalService->getDosenReference();

        return response()->json([
            'status' => 'success',
            'data' => $dosen,
        ]);
    }

    /**
     * Get list of logbook entries for a proposal.
     */
    public function getLogbook(Request $request, $proposalId)
    {
        Gate::authorize('sippm.proposal.read');

        $proposal = ProposalKegiatan::findOrFail($proposalId);

        if (!$request->user()->can('sippm.proposal.finalize') && (int) $proposal->ketua_pegawai_id !== (int) $request->user()?->pegawai_id) {
            abort(403, 'Anda tidak memiliki hak untuk mengakses logbook proposal ini.');
        }

        $perPage = min(100, $request->integer('per_page', 15));
        $sortBy = in_array($request->input('sort_by'), ['tgl_kegiatan', 'created_at', 'persentase_capaian']) ? $request->input('sort_by') : 'tgl_kegiatan';
        $sortOrder = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';

        $logbooks = \App\Models\Sippm\LogbookKegiatan::where('proposal_id', $proposal->id)
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data logbook kegiatan berhasil diambil.',
            'data' => $logbooks->items(),
            'meta' => [
                'current_page' => $logbooks->currentPage(),
                'per_page' => $logbooks->perPage(),
                'total' => $logbooks->total(),
                'last_page' => $logbooks->lastPage(),
            ],
        ], 200);
    }

    /**
     * Store a new logbook entry for a proposal.
     */
    public function storeLogbook(Request $request, $proposalId)
    {
        Gate::authorize('sippm.proposal.update');

        $proposal = ProposalKegiatan::findOrFail($proposalId);

        if (!$request->user()->can('sippm.proposal.finalize') && (int) $proposal->ketua_pegawai_id !== (int) $request->user()?->pegawai_id) {
            abort(403, 'Anda tidak memiliki hak untuk mengelola logbook proposal ini.');
        }

        if ($request->has('tanggal') && !$request->has('tgl_kegiatan')) {
            $request->merge(['tgl_kegiatan' => $request->input('tanggal')]);
        }

        $validated = $request->validate([
            'tgl_kegiatan' => 'required|date',
            'persentase_capaian' => 'required|numeric|min:0|max:100',
            'uraian_kegiatan' => 'required|string',
            'hambatan' => 'nullable|string',
        ]);

        $logbook = $this->proposalService->addLogbookEntry($proposal, $validated, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Catatan logbook kegiatan berhasil disimpan.',
            'data' => $logbook,
        ], 201);
    }
}

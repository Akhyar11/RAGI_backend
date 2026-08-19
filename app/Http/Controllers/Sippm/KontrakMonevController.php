<?php

namespace App\Http\Controllers\Sippm;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sippm\ProposalKegiatan;
use App\Models\Sippm\KontrakKegiatan;
use App\Services\Sippm\KontrakMonevService;

class KontrakMonevController extends Controller
{
    protected $kontrakMonevService;

    public function __construct(KontrakMonevService $kontrakMonevService)
    {
        $this->kontrakMonevService = $kontrakMonevService;
    }

    public function indexKontrak()
    {
        $kontrak = KontrakKegiatan::with(['proposal.skema', 'proposal.ketuaPegawai', 'pencairanDana', 'laporan'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $kontrak,
        ]);
    }

    public function storeKontrak(Request $request, $proposalId)
    {
        $proposal = ProposalKegiatan::findOrFail($proposalId);

        if ($request->has('nominal_dana') && !$request->has('dana_disetujui')) {
            $request->merge(['dana_disetujui' => $request->input('nominal_dana')]);
        }

        $validated = $request->validate([
            'nomor_kontrak' => 'nullable|string|max:100',
            'dana_disetujui' => 'nullable|numeric|min:0',
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'required|date|after:tgl_mulai',
            'file_kontrak' => 'nullable|string',
        ]);

        $kontrak = $this->kontrakMonevService->createKontrak($proposal, $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Kontrak SPK berhasil diterbitkan.',
            'data' => $kontrak,
        ], 201);
    }

    public function requestPencairan(Request $request, $kontrakId)
    {
        $kontrak = KontrakKegiatan::findOrFail($kontrakId);

        $validated = $request->validate([
            'termin_ke' => 'required|integer|min:1',
            'persen_pencairan' => 'required|numeric|min:1|max:100',
        ]);

        $pencairan = $this->kontrakMonevService->requestPencairan(
            $kontrak,
            $validated['termin_ke'],
            $validated['persen_pencairan']
        );

        // Auto-integrate to SIKEU Accounting & Pemasukan Kampus
        $sikeuIntegration = null;
        try {
            $sippmSikeuService = app(\App\Services\Sikeu\SippmSikeuService::class);
            $sikeuIntegration = $sippmSikeuService->recordHibahDisbursement($kontrak, $pencairan);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("SIKEU Integration Warning on SIPPM Pencairan: " . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan pencairan dana termin berhasil dikirim dan dicatat di SIKEU.',
            'data' => $pencairan,
            'sikeu_integration' => $sikeuIntegration,
        ], 201);
    }

    public function submitLaporan(Request $request, $kontrakId)
    {
        $kontrak = KontrakKegiatan::findOrFail($kontrakId);

        $validated = $request->validate([
            'jenis_laporan' => 'required|in:kemajuan,akhir',
            'file_laporan' => 'required|string',
            'file_logbook' => 'nullable|string',
            'file_penggunaan_anggaran' => 'nullable|string',
            'persentase_capaian' => 'nullable|integer|min:0|max:100',
        ]);

        $laporan = $this->kontrakMonevService->submitLaporan(
            $kontrak,
            $validated['jenis_laporan'],
            $validated
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Laporan berhasil diunggah.',
            'data' => $laporan,
        ]);
    }

    public function uploadSpkTtdBasah(Request $request, $kontrakId)
    {
        $kontrak = KontrakKegiatan::findOrFail($kontrakId);

        $validated = $request->validate([
            'file_spk_ttd' => 'required|string',
        ]);

        $updated = $this->kontrakMonevService->uploadSpkTtdBasah($kontrak, $validated['file_spk_ttd']);

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen SPK bertanda tangan basah berhasil di-upload oleh Ketua Pengusul.',
            'data' => $updated,
        ]);
    }

    public function approveSpk(Request $request, $kontrakId)
    {
        $kontrak = KontrakKegiatan::findOrFail($kontrakId);

        $validated = $request->validate([
            'catatan' => 'nullable|string',
        ]);

        $pencairan = $this->kontrakMonevService->approveSpkDokumen($kontrak, $validated['catatan'] ?? null);

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen SPK telah disetujui Admin SIPPM. Status Termin 1 diperbarui menjadi Waiting to Disburse.',
            'data' => $pencairan,
        ]);
    }

    public function uploadResiSikeu(Request $request, $pencairanId)
    {
        $validated = $request->validate([
            'bukti_transfer' => 'required|string',
        ]);

        $pencairan = $this->kontrakMonevService->uploadResiSikeu((int) $pencairanId, $validated['bukti_transfer']);

        return response()->json([
            'status' => 'success',
            'message' => 'Bukti resi transfer SIKEU berhasil diunggah. Status Termin 1 diperbarui menjadi Already Disburse.',
            'data' => $pencairan,
        ]);
    }
}


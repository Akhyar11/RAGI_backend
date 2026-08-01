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

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan pencairan dana termin berhasil dikirim.',
            'data' => $pencairan,
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
}

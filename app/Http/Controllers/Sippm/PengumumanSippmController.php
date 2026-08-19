<?php

namespace App\Http\Controllers\Sippm;

use App\Http\Controllers\Controller;
use App\Models\Sippm\PengumumanHibah;
use App\Services\Sippm\PengumumanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PengumumanSippmController extends Controller
{
    public function __construct(private PengumumanService $pengumumanService) {}

    /**
     * Get active published announcement for lecturers.
     */
    public function getActive(): JsonResponse
    {
        $active = $this->pengumumanService->getActiveAnnouncement();

        return response()->json([
            'status' => 'success',
            'message' => 'Active announcement retrieved successfully',
            'data' => $active,
        ]);
    }

    /**
     * Get all announcements list for admin.
     */
    public function index(Request $request): JsonResponse
    {
        $list = $this->pengumumanService->getAnnouncementsList($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Announcements list retrieved successfully',
            'data' => $list,
        ]);
    }

    /**
     * Create a new draft announcement.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nomor_surat' => 'required|string|max:100',
            'tgl_surat' => 'required|date',
            'hal_surat' => 'nullable|string|max:255',
            'tahun_anggaran' => 'required|string|max:20',
            'tgl_buka_proposal' => 'required|date',
            'tgl_tutup_proposal' => 'required|date|after_or_equal:tgl_buka_proposal',
            'nama_ketua_uppm' => 'required|string|max:150',
            'nama_direktur' => 'required|string|max:150',
            'kualifikasi_dosen' => 'nullable|string',
            'kategori_pendanaan' => 'nullable|string',
            'lampiran_jadwal' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $pengumuman = $this->pengumumanService->createDraft($validator->validated(), $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Draft pengumuman hibah berhasil dibuat',
            'data' => $pengumuman,
        ], 201);
    }

    /**
     * Upload signed scanned PDF document.
     */
    public function uploadSigned(Request $request, $id): JsonResponse
    {
        $pengumuman = PengumumanHibah::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'file_signed_pdf' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240', // max 10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updated = $this->pengumumanService->uploadSignedDocument($pengumuman, $request->file('file_signed_pdf'));

        return response()->json([
            'status' => 'success',
            'message' => 'Scan dokumen TTD basah berhasil diunggah',
            'data' => $updated,
        ]);
    }

    /**
     * Upload proposal template files.
     */
    public function uploadTemplate(Request $request, $id): JsonResponse
    {
        $pengumuman = PengumumanHibah::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:mitra_indo,mitra_intl',
            'file_template' => 'required|file|mimes:doc,docx,pdf|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updated = $this->pengumumanService->uploadProposalTemplate(
            $pengumuman,
            $request->input('type'),
            $request->file('file_template')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'File template proposal berhasil diunggah',
            'data' => $updated,
        ]);
    }

    /**
     * Publish announcement & activate period.
     */
    public function publish($id): JsonResponse
    {
        $pengumuman = PengumumanHibah::findOrFail($id);

        if (!$pengumuman->file_signed_pdf_path && $pengumuman->status !== 'pending_scan') {
            return response()->json([
                'status' => 'error',
                'message' => 'Silakan unggah scan surat ber-TTD basah terlebih dahulu sebelum mempublish pengumuman.',
            ], 400);
        }

        $published = $this->pengumumanService->publishAnnouncement($pengumuman);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengumuman Penerimaan Proposal Hibah berhasil dipublish dan periode aktif!',
            'data' => $published,
        ]);
    }

    /**
     * Render printable HTML layout matching exact letter image.
     */
    public function renderDraftHtml($id)
    {
        $p = PengumumanHibah::findOrFail($id);
        
        $tglFormatted = \Carbon\Carbon::parse($p->tgl_surat)->locale('id')->isoFormat('D MMMM YYYY');

        $kopPath = public_path('images/kop_surat_kampus.png');
        $kopSuratBase64 = file_exists($kopPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($kopPath))
            : asset('images/kop_surat_kampus.png');

        return response()->view('pdf.pengumuman_draft', [
            'p' => $p,
            'tglFormatted' => $tglFormatted,
            'kopSuratBase64' => $kopSuratBase64,
        ]);
    }
}


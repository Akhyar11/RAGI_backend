<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use App\Models\Siakad\DokumenToken;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\TahunAkademik;
use App\Services\Siakad\DokumenService;
use App\Services\Siakad\KrsService;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DokumenController extends Controller
{
    public function __construct(
        protected DokumenService $dokumenService,
        protected KrsService $krsService
    ) {}

    /**
     * Terbitkan dokumen resmi KHS / Transkrip + QR validasi publik.
     * POST /api/v1/siakad/perkuliahan/dokumen/terbitkan
     */
    public function terbitkan(Request $request)
    {
        Gate::authorize('siakad.nilai.read');

        $validated = $request->validate([
            'jenis' => ['required', Rule::in(DokumenToken::JENIS_DOKUMEN)],
            'mahasiswa_id' => 'nullable|exists:siakad_mahasiswa,id',
            'tahun_akademik_id' => 'nullable|exists:siakad_tahun_akademik,id',
        ]);

        $mhs = $this->krsService->resolveMahasiswa($request->user()?->id, $request->input('mahasiswa_id'));
        $mhs->load(['programStudi.fakultas']);
        $taId = $validated['tahun_akademik_id'] ?? TahunAkademik::where('is_active', true)->value('id');

        $dokumen = $this->dokumenService->terbitkan($validated['jenis'], $mhs, $taId ? (int) $taId : null);

        $validasiUrl = rtrim((string) config('app.frontend_url'), '/') . '/validasi-dokumen/' . $dokumen->token;
        $writer = new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd()));

        return response()->json([
            'status' => 'success',
            'message' => "Dokumen {$validated['jenis']} resmi diterbitkan.",
            'data' => [
                'mahasiswa' => $mhs,
                'jenis' => $dokumen->jenis,
                'nomor_dokumen' => $dokumen->nomor_dokumen,
                'snapshot' => $dokumen->snapshot,
                'tanggal_terbit' => $dokumen->tanggal_terbit?->translatedFormat('d F Y'),
                'token' => $dokumen->token,
                'validasi_url' => $validasiUrl,
                'qr_code_svg' => $writer->writeString($validasiUrl),
            ],
        ], 201);
    }

    /**
     * Validasi publik via QR (tanpa login, throttle anti-enumerasi).
     * GET /api/v1/siakad/dokumen/validasi/{token}
     */
    public function validasi(string $token)
    {
        $dokumen = DokumenToken::with(['mahasiswa.programStudi', 'tahunAkademik'])
            ->where('token', $token)
            ->first();
        if (!$dokumen || !$dokumen->is_active) {
            return response()->json(['status' => 'error', 'message' => 'Dokumen tidak ditemukan atau sudah tidak berlaku.'], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen valid dan terdaftar di sistem.',
            'data' => [
                'is_valid' => true,
                'verified_at' => now()->translatedFormat('d F Y H:i'),
                'jenis' => $dokumen->jenis,
                'nomor_dokumen' => $dokumen->nomor_dokumen,
                'tanggal_terbit' => $dokumen->tanggal_terbit?->translatedFormat('d F Y'),
                'snapshot' => $dokumen->snapshot,
                'mahasiswa' => $dokumen->mahasiswa ? [
                    'nama_lengkap' => $dokumen->mahasiswa->nama_lengkap,
                    'nim' => $dokumen->mahasiswa->nim,
                    'program_studi' => $dokumen->mahasiswa->programStudi?->nama,
                    'angkatan' => $dokumen->mahasiswa->angkatan,
                ] : null,
                'periode' => $dokumen->tahunAkademik?->nama,
            ],
        ]);
    }

    /**
     * Monitoring dokumen terbit (BAAK).
     */
    public function index(Request $request)
    {
        Gate::authorize('siakad.mahasiswa.read');

        $query = DokumenToken::with(['mahasiswa.programStudi', 'tahunAkademik']);
        if ($request->filled('jenis')) {
            $query->where('jenis', $request->jenis);
        }
        if ($request->filled('mahasiswa_id')) {
            $query->where('mahasiswa_id', $request->mahasiswa_id);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('nomor_dokumen', 'like', "%{$s}%")
                    ->orWhereHas('mahasiswa', fn($mq) => $mq->where('nama_lengkap', 'like', "%{$s}%")->orWhere('nim', 'like', "%{$s}%"));
            });
        }

        $allowedSort = ['tanggal_terbit', 'created_at', 'id'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'tanggal_terbit';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $perPage = min(100, $request->integer('per_page', 15));
        $data = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen akademik terbit berhasil dimuat',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use App\Models\Siakad\KartuUjianToken;
use App\Models\Siakad\Krs;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\TahunAkademik;
use App\Services\Siakad\KartuUjianService;
use App\Services\Siakad\KrsService;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class KartuUjianController extends Controller
{
    public function __construct(
        protected KartuUjianService $kartuService,
        protected KrsService $krsService
    ) {}

    /**
     * Terbitkan kartu ujian UTS/UAS (syarat lunas SIKEU). Mencetak ulang
     * menyegarkan token + tanggal cetak.
     * GET /api/v1/siakad/perkuliahan/kartu-ujian?tipe_ujian=UTS
     */
    public function cetak(Request $request)
    {
        Gate::authorize('siakad.krs.read');

        $validated = $request->validate([
            'tipe_ujian' => ['required', Rule::in(KartuUjianToken::TIPE_UJIAN)],
            'mahasiswa_id' => 'nullable|exists:siakad_mahasiswa,id',
            'tahun_akademik_id' => 'nullable|exists:siakad_tahun_akademik,id',
        ]);

        $mhs = $this->krsService->resolveMahasiswa($request->user()?->id, $request->input('mahasiswa_id'));
        $mhs->load(['programStudi.fakultas']);
        $ta = $validated['tahun_akademik_id'] ?? null
            ? TahunAkademik::findOrFail($validated['tahun_akademik_id'])
            : TahunAkademik::where('is_active', true)->first();
        if (!$ta) {
            return response()->json(['status' => 'error', 'message' => 'Tahun akademik aktif belum ditetapkan.'], 422);
        }

        $token = $this->kartuService->terbitkan($mhs, (int) $ta->id, $validated['tipe_ujian'], $request->ip(), $request->userAgent());

        $krs = Krs::with(['krsDetails.kelas.mataKuliah', 'krsDetails.kelas.ruangan', 'krsDetails.kelas.dosenPengampu.dosen'])
            ->where('mahasiswa_id', $mhs->id)
            ->where('tahun_akademik_id', $ta->id)
            ->first();
        $jadwal = ($krs?->krsDetails ?? collect())->map(fn($d) => [
            'kode_mk' => $d->kelas?->mataKuliah?->kode_mk,
            'nama_mk' => $d->kelas?->mataKuliah?->nama,
            'sks' => $d->kelas?->mataKuliah?->total_sks,
            'kelas' => $d->kelas?->nama_kelas,
            'hari' => $d->kelas?->hari,
            'jam' => $d->kelas?->jam_mulai && $d->kelas?->jam_selesai
                ? substr((string) $d->kelas->jam_mulai, 0, 5) . '-' . substr((string) $d->kelas->jam_selesai, 0, 5) : null,
            'ruangan' => $d->kelas?->ruangan?->nama,
            'dosen' => $d->kelas?->dosenPengampu?->first()?->dosen?->nama_lengkap,
        ])->values();

        $validasiUrl = rtrim((string) config('app.frontend_url'), '/') . '/validasi-kartu-ujian/' . $token->token;
        $writer = new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd()));

        return response()->json([
            'status' => 'success',
            'message' => "Kartu {$validated['tipe_ujian']} berhasil diterbitkan.",
            'data' => [
                'mahasiswa' => $mhs,
                'tahun_akademik' => $ta,
                'tipe_ujian' => $validated['tipe_ujian'],
                'jadwal' => $jadwal,
                'keuangan_lunas' => true,
                'token' => $token->token,
                'tanggal_cetak' => $token->tanggal_cetak?->translatedFormat('d F Y H:i'),
                'validasi_url' => $validasiUrl,
                'qr_code_svg' => $writer->writeString($validasiUrl),
            ],
        ]);
    }

    /**
     * Validasi publik via QR (tanpa login, throttle anti-enumerasi).
     * GET /api/v1/siakad/kartu-ujian/validasi/{token}
     */
    public function validasi(string $token)
    {
        $kartu = KartuUjianToken::with(['mahasiswa.programStudi', 'tahunAkademik'])
            ->where('token', $token)
            ->first();
        if (!$kartu || !$kartu->is_active) {
            return response()->json(['status' => 'error', 'message' => 'Kartu ujian tidak ditemukan atau sudah tidak berlaku.'], 404);
        }

        $mhs = $kartu->mahasiswa;
        return response()->json([
            'status' => 'success',
            'message' => 'Kartu ujian valid dan terdaftar di sistem.',
            'data' => [
                'is_valid' => true,
                'verified_at' => now()->translatedFormat('d F Y H:i'),
                'tipe_ujian' => $kartu->tipe_ujian,
                'tanggal_cetak' => $kartu->tanggal_cetak?->translatedFormat('d F Y H:i'),
                'mahasiswa' => $mhs ? [
                    'nama_lengkap' => $mhs->nama_lengkap,
                    'nim' => $mhs->nim,
                    'program_studi' => $mhs->programStudi?->nama,
                    'angkatan' => $mhs->angkatan,
                    'status' => $mhs->status,
                ] : null,
                'tahun_akademik' => $kartu->tahunAkademik?->nama,
                'keuangan_lunas' => $mhs ? $this->kartuService->isKeuanganLunas($mhs) : false,
            ],
        ]);
    }

    /**
     * Monitoring BAAK: kartu terbit per periode + tipe.
     */
    public function monitoring(Request $request)
    {
        Gate::authorize('siakad.mahasiswa.read');

        $query = KartuUjianToken::with(['mahasiswa.programStudi', 'tahunAkademik']);
        if ($request->filled('tahun_akademik_id')) {
            $query->where('tahun_akademik_id', $request->tahun_akademik_id);
        }
        if ($request->filled('tipe_ujian')) {
            $query->where('tipe_ujian', $request->tipe_ujian);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('mahasiswa', fn($q) => $q->where('nama_lengkap', 'like', "%{$s}%")->orWhere('nim', 'like', "%{$s}%"));
        }

        $perPage = min(100, $request->integer('per_page', 15));
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $data = $query->orderBy('tanggal_cetak', $sortOrder)->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Monitoring kartu ujian berhasil dimuat',
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

    /**
     * Nonaktifkan kartu (mis. diterbitkan ulang bermasalah / pelanggaran).
     */
    public function nonaktifkan(Request $request, $id)
    {
        Gate::authorize('siakad.mahasiswa.manage');

        $kartu = KartuUjianToken::findOrFail($id);
        $kartu->update(['is_active' => false]);

        return response()->json([
            'status' => 'success',
            'message' => 'Kartu ujian dinonaktifkan.',
            'data' => $kartu,
        ]);
    }
}

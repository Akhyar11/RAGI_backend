<?php

namespace App\Http\Controllers\Sippm;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Models\Sippm\PublikasiIlmiah;
use App\Models\Sippm\HkiDanBuku;
use App\Services\Sippm\LuaranService;

class LuaranSippmController extends Controller
{
    protected $luaranService;
    protected $syncService;

    public function __construct(LuaranService $luaranService, \App\Services\Sippm\PublikasiSyncService $syncService)
    {
        $this->luaranService = $luaranService;
        $this->syncService = $syncService;
    }

    public function fetchExternalPublikasi(Request $request)
    {
        Gate::authorize('sippm.luaran.read');

        $validated = $request->validate([
            'source' => 'required|in:doi,scopus,sinta',
            'identifier' => 'required|string|max:255',
        ]);

        try {
            $data = $this->syncService->fetchExternalData($validated['source'], $validated['identifier']);
            return response()->json([
                'status' => 'success',
                'message' => 'Data publikasi berhasil ditarik dari ' . strtoupper($validated['source']),
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function importExternalPublikasi(Request $request)
    {
        Gate::authorize('sippm.luaran.create');

        $validated = $request->validate([
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'proposal_id' => 'nullable|exists:sippm_proposal_kegiatan,id',
            'judul_artikel' => 'required|string',
            'jenis_publikasi' => 'required|string',
            'nama_jurnal_prosiding' => 'required|string',
            'indexing' => 'nullable|string',
            'volume_issue_tahun' => 'nullable|string',
            'doi' => 'nullable|string',
            'url_artikel' => 'nullable|string',
            'scopus_eid' => 'nullable|string',
            'sinta_article_id' => 'nullable|string',
            'citation_count' => 'nullable|integer',
            'publisher' => 'nullable|string',
        ]);

        $pegawai = \App\Models\Simpeg\Pegawai::findOrFail($validated['pegawai_id']);
        $publikasi = $this->syncService->importExternalPublikasi($pegawai, $validated, $validated['proposal_id'] ?? null);

        return response()->json([
            'status' => 'success',
            'message' => 'Data publikasi berhasil di-import dan disinkronkan ke sistem.',
            'data' => $publikasi,
        ], 201);
    }

    public function indexPublikasi(Request $request)
    {
        Gate::authorize('sippm.luaran.read');

        $query = PublikasiIlmiah::with(['proposal', 'pegawai']);

        if ($request->has('pegawai_id')) {
            $query->where('pegawai_id', $request->pegawai_id);
        }

        if ($request->has('indexing')) {
            $query->where('indexing', $request->indexing);
        }

        $publikasi = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $publikasi,
        ]);
    }

    public function storePublikasi(Request $request)
    {
        Gate::authorize('sippm.luaran.create');

        if (!$request->has('pegawai_id') && $request->user()?->pegawai_id) {
            $request->merge(['pegawai_id' => $request->user()->pegawai_id]);
        }
        if ($request->has('nama_jurnal') && !$request->has('nama_jurnal_prosiding')) {
            $request->merge(['nama_jurnal_prosiding' => $request->input('nama_jurnal')]);
        }
        if ($request->has('doi_url') && !$request->has('doi')) {
            $request->merge(['doi' => $request->input('doi_url')]);
        }
        if ($request->has('tahun') && !$request->has('volume_issue_tahun')) {
            $request->merge(['volume_issue_tahun' => 'Tahun ' . $request->input('tahun')]);
        }
        if ($request->has('kategori_publikasi') && !$request->has('jenis_publikasi')) {
            $kat = $request->input('kategori_publikasi');
            $mapped = PublikasiIlmiah::KATEGORI_MAP[$kat] ?? ['jenis' => 'jurnal_nasional_terakreditasi', 'indexing' => 'lainnya'];
            $request->merge([
                'jenis_publikasi' => $mapped['jenis'],
                'indexing' => $request->input('indexing', $mapped['indexing']),
            ]);
        }

        $validated = $request->validate([
            'proposal_id' => 'nullable|exists:sippm_proposal_kegiatan,id',
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'judul_artikel' => 'required|string',
            'jenis_publikasi' => 'required|in:jurnal_internasional_bereputasi,jurnal_nasional_terakreditasi,prosiding_internasional,prosiding_nasional,jurnal_non_akreditasi',
            'nama_jurnal_prosiding' => 'required|string|max:255',
            'indexing' => 'nullable|in:scopus_q1,scopus_q2,scopus_q3,scopus_q4,sinta_1,sinta_2,sinta_3,sinta_4,sinta_5,sinta_6,wos,lainnya',
            'volume_issue_tahun' => 'required|string|max:100',
            'doi' => 'nullable|string|max:150',
            'url_artikel' => 'nullable|string|max:255',
            'file_artikel' => 'nullable|string|max:255',
        ]);

        $publikasi = $this->luaranService->createPublikasi($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Publikasi ilmiah berhasil didaftarkan.',
            'data' => $publikasi,
        ], 201);
    }

    public function indexHki(Request $request)
    {
        Gate::authorize('sippm.luaran.read');

        $query = HkiDanBuku::with(['proposal', 'pegawai']);

        if ($request->has('pegawai_id')) {
            $query->where('pegawai_id', $request->pegawai_id);
        }

        $hki = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $hki,
        ]);
    }

    public function storeHki(Request $request)
    {
        Gate::authorize('sippm.luaran.create');

        if (!$request->has('pegawai_id') && $request->user()?->pegawai_id) {
            $request->merge(['pegawai_id' => $request->user()->pegawai_id]);
        }
        if ($request->has('judul_hki') && !$request->has('judul')) {
            $request->merge(['judul' => $request->input('judul_hki')]);
        }
        if ($request->has('nomor_pendaftaran') && !$request->has('nomor_pencatatan_isbn')) {
            $request->merge(['nomor_pencatatan_isbn' => $request->input('nomor_pendaftaran')]);
        }
        if ($request->has('kategori_hki') && !$request->has('jenis_luaran')) {
            $kat = $request->input('kategori_hki');
            $request->merge(['jenis_luaran' => HkiDanBuku::KATEGORI_MAP[$kat] ?? 'hak_cipta']);
        }

        $validated = $request->validate([
            'proposal_id' => 'nullable|exists:sippm_proposal_kegiatan,id',
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'jenis_luaran' => 'required|in:paten,hak_cipta,desain_industri,rahasia_dagang,buku_ajar,buku_monograf,book_chapter',
            'judul' => 'required|string',
            'nomor_pencatatan_isbn' => 'required|string|max:100|unique:sippm_hki_dan_buku,nomor_pencatatan_isbn',
            'penerbit_lembaga' => 'required|string|max:255',
            'tgl_terbit_catat' => 'required|date',
            'file_sertifikat_buku' => 'nullable|string|max:255',
        ]);

        $hki = $this->luaranService->createHkiDanBuku($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Data HKI / Buku berhasil didaftarkan.',
            'data' => $hki,
        ], 201);
    }

    public function verifyPublikasi($id)
    {
        Gate::authorize('sippm.luaran.verify');

        $publikasi = PublikasiIlmiah::findOrFail($id);
        $verified = $this->luaranService->verifyPublikasi($publikasi);

        return response()->json([
            'status' => 'success',
            'message' => 'Publikasi ilmiah berhasil diverifikasi oleh LPPM.',
            'data' => $verified,
        ]);
    }

    public function verifyHki($id)
    {
        Gate::authorize('sippm.luaran.verify');

        $hki = HkiDanBuku::findOrFail($id);
        $verified = $this->luaranService->verifyHkiDanBuku($hki);

        return response()->json([
            'status' => 'success',
            'message' => 'Data HKI / Buku berhasil diverifikasi oleh LPPM.',
            'data' => $verified,
        ]);
    }
}

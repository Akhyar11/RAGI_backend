<?php

namespace App\Http\Controllers\Sippm;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sippm\PublikasiIlmiah;
use App\Models\Sippm\HkiDanBuku;
use App\Services\Sippm\LuaranService;

class LuaranSippmController extends Controller
{
    protected $luaranService;

    public function __construct(LuaranService $luaranService)
    {
        $this->luaranService = $luaranService;
    }

    public function indexPublikasi(Request $request)
    {
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
        $validated = $request->validate([
            'proposal_id' => 'nullable|exists:proposal_kegiatan,id',
            'pegawai_id' => 'required|exists:pegawai,id',
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
        $validated = $request->validate([
            'proposal_id' => 'nullable|exists:proposal_kegiatan,id',
            'pegawai_id' => 'required|exists:pegawai,id',
            'jenis_luaran' => 'required|in:paten,hak_cipta,desain_industri,rahasia_dagang,buku_ajar,buku_monograf,book_chapter',
            'judul' => 'required|string',
            'nomor_pencatatan_isbn' => 'required|string|max:100|unique:hki_dan_buku,nomor_pencatatan_isbn',
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
        $hki = HkiDanBuku::findOrFail($id);
        $verified = $this->luaranService->verifyHkiDanBuku($hki);

        return response()->json([
            'status' => 'success',
            'message' => 'Data HKI / Buku berhasil diverifikasi oleh LPPM.',
            'data' => $verified,
        ]);
    }
}

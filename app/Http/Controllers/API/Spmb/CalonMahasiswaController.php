<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Services\Spmb\SpmbPendaftaranService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CalonMahasiswaController extends Controller
{
    protected SpmbPendaftaranService $pendaftaranService;

    public function __construct(SpmbPendaftaranService $pendaftaranService)
    {
        $this->pendaftaranService = $pendaftaranService;
    }

    /**
     * Get my registration data
     */
    public function myPendaftaran(Request $request): JsonResponse
    {
        $user = $request->user();
        $pendaftaran = PendaftaranCalonMhs::with(['gelombangPenerimaan', 'pembayaranSpmb', 'hasilSeleksi'])
            ->where('user_id', $user->id)
            ->first();

        $tagihanData = null;
        if ($pendaftaran) {
            $tagihan = \App\Models\Sikeu\TagihanMahasiswa::with('virtualAccount')
                ->where('calon_mahasiswa_id', $pendaftaran->id)
                ->where('source_system', 'SPMB')
                ->first();
                
            if ($tagihan) {
                $tagihanData = [
                    'tagihan' => $tagihan,
                    'virtual_account' => $tagihan->virtualAccount
                ];
            }
        }

        if ($pendaftaran && $tagihanData) {
            $pendaftaran->tagihan_info = $tagihanData;
        }

        return response()->json([
            'status' => 'success',
            'data' => $pendaftaran ? [
                'pendaftaran' => $pendaftaran,
                'tagihan' => $tagihanData
            ] : null
        ]);
    }

    /**
     * Submit biodata pendaftaran (Draft)
     */
    public function storeBiodata(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'gelombang_id' => 'required|exists:gelombang_penerimaan,id',
            'program_studi_id' => 'required|integer',
            'program_studi_pilihan2_id' => 'nullable|integer',
            'nama_lengkap' => 'required|string|max:255',
            'nik' => 'required|string|size:16',
            'tanggal_lahir' => 'required|date',
            'tempat_lahir' => 'required|string',
            'jenis_kelamin' => 'required|in:L,P',
            'alamat' => 'required|string',
            'asal_sekolah' => 'required|string',
            'jurusan_sekolah' => 'required|string',
        ]);

        $pendaftaran = PendaftaranCalonMhs::updateOrCreate(
            ['user_id' => $user->id, 'gelombang_id' => $validated['gelombang_id']],
            array_merge($validated, [
                'no_pendaftaran' => 'REG-' . date('Ymd') . '-' . rand(1000, 9999),
                'kewarganegaraan' => 'WNI',
                'status' => 'draft',
                'status_pembayaran' => 'belum_bayar'
            ])
        );

        // Fetch tarif using SpmbSikeuService
        $sikeuService = app(\App\Services\Sikeu\SpmbSikeuService::class);
        $gelombang = \App\Models\Spmb\GelombangPenerimaan::find($validated['gelombang_id']);
        $nominal = $sikeuService->getTarifPendaftaranSpmb($gelombang->jalur_masuk_id, $gelombang->id);

        // Generate External Bill via internal Request
        $payload = [
            'calon_mahasiswa_id' => $pendaftaran->id,
            'tipe_referensi' => 'calon_mahasiswa',
            'tahun_akademik_id' => $gelombang->tahun_akademik_id ?? 1,
            'source_system' => 'SPMB',
            'requires_approval' => false,
            'keterangan' => 'Pendaftaran SPMB - ' . $validated['nama_lengkap'],
            'details' => [
                [
                    'jenis_biaya_kode' => 'SPMB_ADM',
                    'nominal' => $nominal,
                    'keterangan' => 'Biaya Formulir Pendaftaran SPMB'
                ]
            ]
        ];

        $externalReq = \Illuminate\Http\Request::create('/api/v1/sikeu/tagihan/external', 'POST', $payload);
        $res = app(\App\Http\Controllers\Sikeu\ExternalTagihanController::class)->createExternalBill($externalReq);
        $vaData = json_decode($res->getContent(), true);

        return response()->json([
            'status' => 'success',
            'message' => 'Biodata berhasil disimpan dan Tagihan diterbitkan.',
            'data' => [
                'pendaftaran' => $pendaftaran,
                'tagihan' => $vaData['data'] ?? null
            ]
        ]);
    }

    /**
     * Submit formulir secara final (Lock)
     */
    public function finalize(Request $request): JsonResponse
    {
        $user = $request->user();
        $pendaftaran = PendaftaranCalonMhs::where('user_id', $user->id)->firstOrFail();

        $this->pendaftaranService->submitPendaftaran($pendaftaran);

        return response()->json([
            'status' => 'success',
            'message' => 'Pendaftaran berhasil disubmit.',
            'data' => $pendaftaran
        ]);
    }
}

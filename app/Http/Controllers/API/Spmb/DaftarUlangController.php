<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\HasilSeleksi;
use App\Events\Spmb\MahasiswaDiterima;
use Illuminate\Http\JsonResponse;

class DaftarUlangController extends Controller
{
    public function generateTagihan(Request $request, $pendaftaran_id): JsonResponse
    {
        $hasil = HasilSeleksi::where('pendaftaran_id', $pendaftaran_id)->firstOrFail();
        
        if ($hasil->status !== 'lulus') {
            return response()->json(['message' => 'Peserta belum lulus.'], 400);
        }

        // Hitung UKT statis untuk demo (Bisa dari SIKEU Master)
        $nominalUKT = 5000000;

        $payload = [
            'calon_mahasiswa_id' => $pendaftaran_id,
            'tipe_referensi' => 'calon_mahasiswa',
            'tahun_akademik_id' => 1,
            'source_system' => 'SPMB',
            'requires_approval' => false,
            'keterangan' => 'Tagihan Daftar Ulang (UKT) - Pendaftaran ID ' . $pendaftaran_id,
            'details' => [
                [
                    'jenis_biaya_kode' => 'UKT_SMT1',
                    'nominal' => $nominalUKT,
                    'keterangan' => 'Biaya UKT Semester 1'
                ]
            ]
        ];

        $externalReq = Request::create('/api/v1/sikeu/tagihan/external', 'POST', $payload);
        $res = app(\App\Http\Controllers\Sikeu\ExternalTagihanController::class)->createExternalBill($externalReq);
        $vaData = json_decode($res->getContent(), true);

        $hasil->update(['status_daftar_ulang' => 'menunggu_pembayaran']);

        return response()->json([
            'status' => 'success',
            'message' => 'Tagihan Daftar Ulang berhasil dibuat.',
            'data' => $vaData['data'] ?? null
        ]);
    }

    public function konfirmasi(Request $request, $pendaftaran_id): JsonResponse
    {
        $pendaftaran = PendaftaranCalonMhs::findOrFail($pendaftaran_id);
        $hasil = HasilSeleksi::where('pendaftaran_id', $pendaftaran_id)->firstOrFail();

        if ($hasil->status_daftar_ulang === 'lunas') {
            return response()->json(['message' => 'Sudah melakukan daftar ulang.'], 400);
        }

        $hasil->update(['status_daftar_ulang' => 'lunas']);

        // Trigger Event ke SIAKAD
        event(new MahasiswaDiterima($pendaftaran));

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar Ulang selesai. Mahasiswa berhasil dikonversi ke SIAKAD.'
        ]);
    }
}

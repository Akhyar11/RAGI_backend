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
        $pendaftaran = PendaftaranCalonMhs::with(['gelombang_penerimaan', 'hasilSeleksi'])->findOrFail($pendaftaran_id);
        $hasil = HasilSeleksi::where('pendaftaran_id', $pendaftaran_id)->firstOrFail();
        
        if ($hasil->status !== 'lulus') {
            return response()->json(['message' => 'Peserta belum lulus seleksi.'], 400);
        }

        if ($hasil->status_daftar_ulang === 'lunas') {
            return response()->json(['message' => 'Sudah menyelesaikan daftar ulang.'], 400);
        }

        if ($hasil->status_daftar_ulang === 'menunggu_pembayaran') {
            return response()->json(['message' => 'Tagihan daftar ulang sudah dibuat, silakan lanjutkan pembayaran.'], 400);
        }

        // Cari Tarif UKT untuk Prodi dan Tahun Akademik bersangkutan
        $prodiId = $hasil->program_studi_diterima_id ?? $pendaftaran->program_studi_id;
        $tahunAkademikId = $pendaftaran->gelombang_penerimaan->tahun_akademik_id ?? 1;

        $tarifUkt = \App\Models\Spmb\TarifUktSpmb::with('masterBiaya')
                        ->where('program_studi_id', $prodiId)
                        ->where('tahun_akademik_id', $tahunAkademikId)
                        ->where('is_active', true)
                        ->orderBy('nominal', 'asc') // Ambil UKT kelompok terendah jika tidak ada data spesifik mahasiswa
                        ->first();

        // Jika UKT di SPMB tidak ada, maka cek ke Master Tarif SIKEU
        if (!$tarifUkt) {
            $tarifUkt = \App\Models\Sikeu\TarifSpmb::with('masterBiaya')
                            ->whereHas('masterBiaya', function($q) {
                                $q->where('kode', 'like', '%UKT%')->orWhere('nama', 'like', '%UKT%');
                            })->first(); 
        }

        $nominalUKT = $tarifUkt ? $tarifUkt->nominal : 5000000;
        $kodeBiaya = ($tarifUkt && $tarifUkt->masterBiaya) ? $tarifUkt->masterBiaya->kode : 'UKT_SMT1';

        $payload = [
            'calon_mahasiswa_id' => $pendaftaran_id,
            'tipe_referensi' => 'calon_mahasiswa',
            'tahun_akademik_id' => $tahunAkademikId,
            'source_system' => 'SPMB',
            'requires_approval' => false,
            'keterangan' => 'Tagihan Daftar Ulang (UKT) - Pendaftaran ID ' . $pendaftaran_id,
            'details' => [
                [
                    'master_biaya_kode' => $kodeBiaya,
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

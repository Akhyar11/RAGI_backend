<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Spmb\StoreBiodataRequest;
use App\Models\Sikeu\DetailTagihan;
use App\Models\Sikeu\PaymentGatewayConfig;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\VirtualAccount;
use App\Models\Spmb\BerkasRequirement;
use App\Models\Spmb\DokumenPendaftaran;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Services\Sikeu\ExternalTagihanService;
use App\Services\Spmb\MasterBiayaService;
use App\Services\Spmb\SpmbPendaftaranService;
use App\Services\Storage\FileStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CalonMahasiswaController extends Controller
{
    protected SpmbPendaftaranService $pendaftaranService;

    public function __construct(
        SpmbPendaftaranService $pendaftaranService,
        private FileStorageService $files
    ) {
        $this->pendaftaranService = $pendaftaranService;
    }

    /**
     * Get my registration data
     */
    public function myPendaftaran(Request $request): JsonResponse
    {
        $user = $request->user();
        $pendaftaran = PendaftaranCalonMhs::with([
            'gelombangPenerimaan',
            'programStudi',
            'programStudiPilihan2',
            'pembayaranSpmb',
            'hasilSeleksi',
            'dokumenPendaftaran',
            'progressAlur.masterAlur',
            'referrer:id,username,name,referral_code',
        ])
            ->where('user_id', $user->id)
            ->first();

        $tagihanData = null;
        if ($pendaftaran) {
            $tagihan = TagihanMahasiswa::with('virtualAccount')
                ->where('calon_mahasiswa_id', $pendaftaran->id)
                ->where('source_system', 'SPMB')
                ->where(function ($q) {
                    $q->where('tipe_referensi', 'spmb_pendaftaran')->orWhereNull('tipe_referensi');
                })
                ->first();

            if ($tagihan) {
                $tagihanData = [
                    'tagihan' => $tagihan,
                    'virtual_account' => $tagihan->virtualAccount,
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
                'tagihan' => $tagihanData,
            ] : null,
        ]);
    }

    /**
     * Submit biodata pendaftaran (Draft)
     */
    public function storeBiodata(StoreBiodataRequest $request): JsonResponse
    {
        $user = $request->user();

        $pendaftaran = $this->pendaftaranService->saveBiodata($user, $request->validated());
        $namaLengkap = $pendaftaran->nama_lengkap ?? 'Calon Mahasiswa';

        // Fetch / Re-use existing External Bill via SIKEU to prevent duplicate invoices on multi-step draft saves
        $existingTagihan = TagihanMahasiswa::with('virtualAccount')
            ->where('calon_mahasiswa_id', $pendaftaran->id)
            ->where('source_system', 'SPMB')
            ->where(function ($q) {
                $q->where('tipe_referensi', 'spmb_pendaftaran')->orWhereNull('tipe_referensi');
            })
            ->first();

        $tagihanPayload = null;
        if ($existingTagihan) {
            $tagihanPayload = [
                'tagihan' => $existingTagihan,
                'virtual_account' => $existingTagihan->virtualAccount,
            ];
        } else {
            // Beban awal pendaftaran disusun oleh service (termasuk fallback).
            $masterBiayaService = app(MasterBiayaService::class);
            $gelombangId = $pendaftaran->gelombang_id ?? 1;
            $details = $masterBiayaService->buildDetailBebanPendaftaran($gelombangId, $pendaftaran->program_studi_id);

            // Generate External Bill via internal Request
            $payload = [
                'calon_mahasiswa_id' => $pendaftaran->id,
                'tipe_referensi' => 'spmb_pendaftaran',
                'source_system' => 'SPMB',
                'master_biaya_tipe' => 'spmb_adm',
                'requires_approval' => false,
                'keterangan' => 'Pendaftaran SPMB - '.$namaLengkap,
                'details' => $details,
            ];

            // Generate External Bill via SIKEU service
            try {
                $issued = app(ExternalTagihanService::class)->issueExternalBill($payload);
                $tagihanPayload = [
                    'tagihan' => $issued['tagihan'],
                    'virtual_account' => $issued['virtual_account'],
                ];
            } catch (\Throwable $e) {
                // Biodata tetap tersimpan; penerbitan tagihan dapat diulang via reissue-va.
                Log::warning('Gagal menerbitkan tagihan eksternal SPMB: '.$e->getMessage());
                $tagihanPayload = null;
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Biodata berhasil disimpan dan Tagihan diterbitkan.',
            'data' => [
                'pendaftaran' => $pendaftaran,
                'tagihan' => $tagihanPayload,
            ],
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
            'data' => $pendaftaran,
        ]);
    }

    /**
     * Reissue / Generasi Ulang Nomor Virtual Account (Tanpa Menghapus Biodata)
     */
    public function reissueVa(Request $request): JsonResponse
    {
        $user = $request->user();
        $pendaftaran = PendaftaranCalonMhs::where('user_id', $user->id)->first();
        if (! $pendaftaran) {
            return response()->json(['status' => 'error', 'message' => 'Pendaftaran tidak ditemukan.'], 404);
        }

        $tagihan = TagihanMahasiswa::where('calon_mahasiswa_id', $pendaftaran->id)
            ->where('source_system', 'SPMB')
            ->where(function ($q) {
                $q->where('tipe_referensi', 'spmb_pendaftaran')->orWhereNull('tipe_referensi');
            })
            ->first();

        if ($tagihan) {
            // Reset status to belum_bayar
            $pendaftaran->update(['status_pembayaran' => 'belum_bayar']);
            $newNomorTagihan = 'INV-SPMB-'.date('Ymd').'-'.strtoupper(Str::random(5));
            $tagihan->update([
                'status' => 'belum_bayar',
                'nomor_tagihan' => $newNomorTagihan,
            ]);

            // Delete old Virtual Account record
            VirtualAccount::where('tagihan_id', $tagihan->id)->delete();

            // Re-generate VA via Xendit or local fallback (Defaulting to BRI as active Xendit channel)
            $bankCode = 'BRI';
            $vaNumber = '13282'.date('ymd').str_pad($tagihan->id, 5, '0', STR_PAD_LEFT);
            $totalBayar = (float) $tagihan->total_bayar;

            $pgConfig = PaymentGatewayConfig::where('is_active', true)->first();
            $apiKey = $pgConfig->api_key_encrypted ?? $pgConfig->public_key_encrypted ?? null;

            if ($pgConfig && $pgConfig->gateway_name === 'xendit' && ! empty($apiKey)) {
                try {
                    $xenditRes = Http::withoutVerifying()
                        ->withBasicAuth($apiKey, '')
                        ->post('https://api.xendit.co/callback_virtual_accounts', [
                            'external_id' => $newNomorTagihan,
                            'bank_code' => $bankCode,
                            'name' => 'SPMB Calon Mhs #'.$pendaftaran->id,
                            'expected_amount' => (int) $totalBayar,
                            'is_closed' => true,
                            'expiration_date' => date('c', strtotime('+30 days')),
                        ]);

                    if ($xenditRes->successful()) {
                        $xData = $xenditRes->json();
                        $vaNumber = $xData['account_number'] ?? $vaNumber;
                        $bankCode = $xData['bank_code'] ?? $bankCode;
                    }
                } catch (\Throwable $e) {
                    Log::warning('Xendit VA Reissue Exception: '.$e->getMessage());
                }
            }

            $newVa = VirtualAccount::create([
                'tagihan_id' => $tagihan->id,
                'va_number' => $vaNumber,
                'bank_kode' => $bankCode,
                'bank_nama' => 'Bank '.$bankCode,
                'nominal' => $totalBayar,
                'expired_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'status' => 'aktif',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Nomor Virtual Account berhasil diperbarui.',
                'data' => [
                    'pendaftaran' => $pendaftaran,
                    'tagihan' => [
                        'tagihan' => $tagihan,
                        'virtual_account' => $newVa,
                    ],
                ],
            ]);
        }

        return response()->json(['status' => 'error', 'message' => 'Tagihan tidak ditemukan.'], 404);
    }

    /**
     * Reset / Hapus Draf Pendaftaran & Tagihan Lama (Untuk Pengujian Ulang)
     */
    public function resetPendaftaran(Request $request): JsonResponse
    {
        $user = $request->user();
        $pendaftaran = PendaftaranCalonMhs::where('user_id', $user->id)->first();
        if ($pendaftaran) {
            $tagihan = TagihanMahasiswa::where('calon_mahasiswa_id', $pendaftaran->id)
                ->where('source_system', 'SPMB')
                ->first();
            if ($tagihan) {
                VirtualAccount::where('tagihan_id', $tagihan->id)->delete();
                DetailTagihan::where('tagihan_id', $tagihan->id)->delete();
                $tagihan->delete();
            }
            $pendaftaran->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Draf pendaftaran dan VA lama berhasil direset. Silakan buat pendaftaran baru.',
        ]);
    }

    /**
     * Upload Berkas / Dokumen Pendaftaran Calon Mahasiswa
     */
    public function uploadBerkas(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'jenis_berkas' => 'nullable|string',
            'jenis_dokumen' => 'nullable|string',
            'berkas_requirement_id' => 'nullable|integer|exists:spmb_berkas_requirement,id',
        ]);

        $user = $request->user();
        $pendaftaran = PendaftaranCalonMhs::where('user_id', $user->id)->first();

        if (! $pendaftaran) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pendaftaran tidak ditemukan. Harap isi data registrasi terlebih dahulu.',
            ], 404);
        }

        $jenisDokumen = $request->jenis_dokumen ?: $request->jenis_berkas;
        $requirementId = $request->berkas_requirement_id;

        if ($requirementId && empty($jenisDokumen)) {
            $req = BerkasRequirement::find($requirementId);
            if ($req) {
                $jenisDokumen = $req->jenis_dokumen;
            }
        }

        if (empty($jenisDokumen) && empty($requirementId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Jenis dokumen atau berkas requirement ID wajib diisi.',
            ], 422);
        }

        $file = $request->file('file');
        $filePath = $this->files->store($file, 'spmb/dokumen_pendaftaran', private: true);

        // Delete old file if existing record exists
        $query = DokumenPendaftaran::where('pendaftaran_id', $pendaftaran->id);
        if ($requirementId) {
            $query->where(function ($q) use ($requirementId, $jenisDokumen) {
                $q->where('berkas_requirement_id', $requirementId);
                if ($jenisDokumen) {
                    $q->orWhere('jenis_dokumen', $jenisDokumen);
                }
            });
        } else {
            $query->where('jenis_dokumen', $jenisDokumen);
        }

        $existingDoc = $query->first();

        if ($existingDoc && ! empty($existingDoc->file_path)) {
            $this->files->delete($existingDoc->file_path, private: true);
        }

        $docData = [
            'file_path' => $filePath,
            'is_verified' => false,
            'catatan' => null,
        ];

        if ($requirementId) {
            $docData['berkas_requirement_id'] = $requirementId;
        }
        if ($jenisDokumen) {
            $docData['jenis_dokumen'] = $jenisDokumen;
        }

        if ($existingDoc) {
            $existingDoc->update($docData);
            $dokumen = $existingDoc;
        } else {
            $dokumen = DokumenPendaftaran::create(array_merge([
                'pendaftaran_id' => $pendaftaran->id,
                'jenis_dokumen' => $jenisDokumen ?: 'dokumen',
                'berkas_requirement_id' => $requirementId,
            ], $docData));
        }

        $fileUrl = $this->files->temporaryUrl($filePath) ?? $this->files->url($filePath, private: true);

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen '.strtoupper($jenisDokumen ?: 'pendaftaran').' berhasil diunggah.',
            'data' => array_merge($dokumen->toArray(), [
                'file_url' => $fileUrl,
                'jenis_berkas' => $dokumen->jenis_dokumen,
            ]),
        ]);
    }
}

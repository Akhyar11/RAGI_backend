<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Services\Spmb\SpmbPendaftaranService;
use App\Services\Storage\FileStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
            'progressAlur.masterAlur'
        ])
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
            'gelombang_id' => 'sometimes|nullable|integer',
            'program_studi_id' => 'sometimes|nullable|integer',
            'program_studi_pilihan2_id' => 'sometimes|nullable|integer',
            'master_tipe_jalur_id' => 'sometimes|nullable|exists:core_master_tipe_jalur,id',
            'nama_lengkap' => 'sometimes|nullable|string|max:255',
            'nik' => 'sometimes|nullable|string|max:20',
            'tanggal_lahir' => 'sometimes|nullable|date',
            'tempat_lahir' => 'sometimes|nullable|string',
            'jenis_kelamin' => 'sometimes|nullable|in:L,P',
            'agama' => 'sometimes|nullable|string',
            'status_sipil' => 'sometimes|nullable|in:Belum Kawin,Kawin,Janda,Duda',
            'kewarganegaraan' => 'sometimes|nullable|string',
            'no_hp' => 'sometimes|nullable|string',
            'alamat' => 'sometimes|nullable|string',
            'provinsi' => 'sometimes|nullable|string',
            'kota_kabupaten' => 'sometimes|nullable|string',
            'kecamatan' => 'sometimes|nullable|string',
            'kode_pos' => 'sometimes|nullable|string',
            'asal_sekolah' => 'sometimes|nullable|string',
            'alamat_sekolah' => 'sometimes|nullable|string',
            'npsn_sekolah' => 'sometimes|nullable|string',
            'jurusan_sekolah' => 'sometimes|nullable|string',
            'tahun_lulus' => 'sometimes|nullable|string',
            'nilai_rata_rapor' => 'sometimes|nullable|numeric',
            'nama_ayah' => 'sometimes|nullable|string',
            'pekerjaan_ayah' => 'sometimes|nullable|string',
            'nama_ibu' => 'sometimes|nullable|string',
            'pekerjaan_ibu' => 'sometimes|nullable|string',
            'penghasilan_ortu' => 'sometimes|nullable|string',
            'nama_ortu' => 'sometimes|nullable|string',
            'alamat_ortu' => 'sometimes|nullable|string',
            'telp_ortu' => 'sometimes|nullable|string',
            'nama_wali' => 'sometimes|nullable|string',
            'telepon_wali' => 'sometimes|nullable|string',
            'asal_lulusan' => 'sometimes|nullable|in:sekolah,pt',
            'asal_pt' => 'sometimes|nullable|string',
            'jenis_pt' => 'sometimes|nullable|string',
            'alamat_pt' => 'sometimes|nullable|string',
            'jenjang_pt' => 'sometimes|nullable|string',
            'progdi_pt' => 'sometimes|nullable|string',
            'ipk_pt' => 'sometimes|nullable|string',
            'nim_pt' => 'sometimes|nullable|string',
            'tahun_lulus_pt' => 'sometimes|nullable|string',
        ]);

        $existing = PendaftaranCalonMhs::where('user_id', $user->id)->first();
        $noPendaftaran = ($existing && !empty($existing->no_pendaftaran)) 
            ? $existing->no_pendaftaran 
            : ('REG-' . date('Ymd') . '-' . rand(1000, 9999));

        // GELOMBANG IMMUTABILITY PROTECTION:
        // If candidate already registered/paid in a gelombang, lock gelombang_id so future admin gelombang changes won't affect them.
        if ($existing && !empty($existing->gelombang_id)) {
            $validated['gelombang_id'] = $existing->gelombang_id;
        }

        // Sanitasi input: jika nik kosong string, jadikan null agar tidak memicu duplikat unique key
        if (array_key_exists('nik', $validated)) {
            if (empty(trim((string)$validated['nik']))) {
                $validated['nik'] = $existing ? $existing->nik : null;
            }
        }

        // Pastikan nama_lengkap memiliki nilai representatif meskipun pada Step 1 awal
        $namaLengkap = !empty($validated['nama_lengkap']) 
            ? $validated['nama_lengkap'] 
            : ($existing->nama_lengkap ?? $user->name ?? $user->username ?? 'Calon Mahasiswa');

        $pendaftaran = PendaftaranCalonMhs::updateOrCreate(
            ['user_id' => $user->id],
            array_merge($validated, [
                'nama_lengkap' => $namaLengkap,
                'no_pendaftaran' => $noPendaftaran,
                'kewarganegaraan' => $validated['kewarganegaraan'] ?? $existing->kewarganegaraan ?? 'WNI',
                'status' => $existing ? $existing->status : 'draft',
                'status_pembayaran' => $existing ? $existing->status_pembayaran : 'belum_bayar'
            ])
        );

        // Fetch / Re-use existing External Bill via SIKEU to prevent duplicate invoices on multi-step draft saves
        $existingTagihan = \App\Models\Sikeu\TagihanMahasiswa::with('virtualAccount')
            ->where('calon_mahasiswa_id', $pendaftaran->id)
            ->where('source_system', 'SPMB')
            ->first();

        $tagihanPayload = null;
        if ($existingTagihan) {
            $tagihanPayload = [
                'tagihan' => $existingTagihan,
                'virtual_account' => $existingTagihan->virtualAccount
            ];
        } else {
            // Fetch tarif using SpmbSikeuService
            $sikeuService = app(\App\Services\Sikeu\SpmbSikeuService::class);
            $gelombangId = $pendaftaran->gelombang_id ?? $validated['gelombang_id'] ?? 1;
            $gelombang = \App\Models\Spmb\GelombangPenerimaan::with('masterBiaya')->find($gelombangId);
            $nominal = $sikeuService->getTarifPendaftaranSpmb($gelombang->jalur_masuk_id ?? 1, $gelombang->id ?? 1);
            if ($nominal <= 0) {
                $nominal = ($gelombang && $gelombang->biaya_pendaftaran > 0) ? (float) $gelombang->biaya_pendaftaran : 250000.00;
            }

            $biayaKode = ($gelombang && $gelombang->masterBiaya) ? $gelombang->masterBiaya->kode : 'SPMB_ADM';
            $biayaNama = ($gelombang && $gelombang->masterBiaya) ? $gelombang->masterBiaya->nama : 'Biaya Formulir Pendaftaran SPMB';

            // Generate External Bill via internal Request
            $payload = [
                'calon_mahasiswa_id' => $pendaftaran->id,
                'tipe_referensi' => 'calon_mahasiswa',
                'tahun_akademik_id' => $gelombang->tahun_akademik_id ?? 1,
                'source_system' => 'SPMB',
                'requires_approval' => false,
                'keterangan' => 'Pendaftaran SPMB - ' . $namaLengkap,
                'details' => [
                    [
                        'master_biaya_kode' => $biayaKode,
                        'nominal' => $nominal,
                        'keterangan' => $biayaNama
                    ]
                ]
            ];

            $externalReq = \Illuminate\Http\Request::create('/api/v1/sikeu/tagihan/external', 'POST', $payload);
            $res = app(\App\Http\Controllers\Sikeu\ExternalTagihanController::class)->createExternalBill($externalReq);
            $vaData = json_decode($res->getContent(), true);
            $tagihanPayload = $vaData['data'] ?? null;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Biodata berhasil disimpan dan Tagihan diterbitkan.',
            'data' => [
                'pendaftaran' => $pendaftaran,
                'tagihan' => $tagihanPayload
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

    /**
     * Reissue / Generasi Ulang Nomor Virtual Account (Tanpa Menghapus Biodata)
     */
    public function reissueVa(Request $request): JsonResponse
    {
        $user = $request->user();
        $pendaftaran = PendaftaranCalonMhs::where('user_id', $user->id)->first();
        if (!$pendaftaran) {
            return response()->json(['status' => 'error', 'message' => 'Pendaftaran tidak ditemukan.'], 404);
        }

        $tagihan = \App\Models\Sikeu\TagihanMahasiswa::where('calon_mahasiswa_id', $pendaftaran->id)
            ->where('source_system', 'SPMB')
            ->first();

        if ($tagihan) {
            // Reset status to belum_bayar
            $pendaftaran->update(['status_pembayaran' => 'belum_bayar']);
            $newNomorTagihan = 'INV-SPMB-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(5));
            $tagihan->update([
                'status' => 'belum_bayar',
                'nomor_tagihan' => $newNomorTagihan,
            ]);

            // Delete old Virtual Account record
            \App\Models\Sikeu\VirtualAccount::where('tagihan_id', $tagihan->id)->delete();

            // Re-generate VA via Xendit or local fallback (Defaulting to BRI as active Xendit channel)
            $bankCode = 'BRI';
            $vaNumber = '13282' . date('ymd') . str_pad($tagihan->id, 5, '0', STR_PAD_LEFT);
            $totalBayar = (float) $tagihan->total_bayar;

            $pgConfig = \App\Models\Sikeu\PaymentGatewayConfig::where('is_active', true)->first();
            $apiKey = $pgConfig->api_key_encrypted ?? $pgConfig->public_key_encrypted ?? null;

            if ($pgConfig && $pgConfig->gateway_name === 'xendit' && !empty($apiKey)) {
                try {
                    $xenditRes = \Illuminate\Support\Facades\Http::withoutVerifying()
                        ->withBasicAuth($apiKey, '')
                        ->post('https://api.xendit.co/callback_virtual_accounts', [
                            'external_id' => $newNomorTagihan,
                            'bank_code' => $bankCode,
                            'name' => 'SPMB Calon Mhs #' . $pendaftaran->id,
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
                    \Illuminate\Support\Facades\Log::warning("Xendit VA Reissue Exception: " . $e->getMessage());
                }
            }

            $newVa = \App\Models\Sikeu\VirtualAccount::create([
                'tagihan_id' => $tagihan->id,
                'va_number' => $vaNumber,
                'bank_kode' => $bankCode,
                'bank_nama' => 'Bank ' . $bankCode,
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
                        'virtual_account' => $newVa
                    ]
                ]
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
            $tagihan = \App\Models\Sikeu\TagihanMahasiswa::where('calon_mahasiswa_id', $pendaftaran->id)
                ->where('source_system', 'SPMB')
                ->first();
            if ($tagihan) {
                \App\Models\Sikeu\VirtualAccount::where('tagihan_id', $tagihan->id)->delete();
                \App\Models\Sikeu\DetailTagihan::where('tagihan_id', $tagihan->id)->delete();
                $tagihan->delete();
            }
            $pendaftaran->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Draf pendaftaran dan VA lama berhasil direset. Silakan buat pendaftaran baru.'
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

        if (!$pendaftaran) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pendaftaran tidak ditemukan. Harap isi data registrasi terlebih dahulu.'
            ], 404);
        }

        $jenisDokumen = $request->jenis_dokumen ?: $request->jenis_berkas;
        $requirementId = $request->berkas_requirement_id;

        if ($requirementId && empty($jenisDokumen)) {
            $req = \App\Models\Spmb\BerkasRequirement::find($requirementId);
            if ($req) {
                $jenisDokumen = $req->jenis_dokumen;
            }
        }

        if (empty($jenisDokumen) && empty($requirementId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Jenis dokumen atau berkas requirement ID wajib diisi.'
            ], 422);
        }

        $file = $request->file('file');
        $filePath = $this->files->store($file, 'spmb/dokumen_pendaftaran', private: true);

        // Delete old file if existing record exists
        $query = \App\Models\Spmb\DokumenPendaftaran::where('pendaftaran_id', $pendaftaran->id);
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

        if ($existingDoc && !empty($existingDoc->file_path)) {
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
            $dokumen = \App\Models\Spmb\DokumenPendaftaran::create(array_merge([
                'pendaftaran_id' => $pendaftaran->id,
                'jenis_dokumen' => $jenisDokumen ?: 'dokumen',
                'berkas_requirement_id' => $requirementId,
            ], $docData));
        }

        $fileUrl = $this->files->temporaryUrl($filePath) ?? $this->files->url($filePath, private: true);

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen ' . strtoupper($jenisDokumen ?: 'pendaftaran') . ' berhasil diunggah.',
            'data' => array_merge($dokumen->toArray(), [
                'file_url' => $fileUrl,
                'jenis_berkas' => $dokumen->jenis_dokumen,
            ])
        ]);
    }
}

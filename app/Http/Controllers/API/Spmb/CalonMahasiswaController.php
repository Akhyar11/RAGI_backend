<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Spmb\StoreBiodataRequest;
use App\Http\Requests\Spmb\IssueSpmbRegistrationInvoiceRequest;
use App\Models\Sikeu\DetailTagihan;
use App\Models\Sikeu\PaymentGatewayConfig;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\VirtualAccount;
use App\Models\Spmb\BerkasRequirement;
use App\Models\Spmb\DokumenPendaftaran;
use App\Models\Spmb\GelombangPenerimaan;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Services\Sikeu\ExternalTagihanService;
use App\Services\Spmb\MasterBiayaService;
use App\Services\Spmb\SpmbPendaftaranService;
use App\Services\Spmb\SpmbKodeDiskonService;
use App\Services\AuditLogService;
use App\Services\Storage\FileStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CalonMahasiswaController extends Controller
{
    protected SpmbPendaftaranService $pendaftaranService;

    public function __construct(
        SpmbPendaftaranService $pendaftaranService,
        private FileStorageService $files,
        private SpmbKodeDiskonService $kodeDiskonService
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
            'referrer:id,username,name,referral_code',
            'kodeDiskonUsage.kodeDiskon',
        ])
            ->where('user_id', $user->id)
            ->first();

        if ($pendaftaran) {
            app(\App\Services\Sikeu\SpmbOnlineSettlementService::class)
                ->reconcileMidtransForApplicant($pendaftaran->id);
            $pendaftaran->refresh();
        }

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

        // Tagihan daftar ulang (agar halaman checkout bisa membayar VA yang benar).
        $daftarUlangTagihanData = null;
        if ($pendaftaran) {
            $duTagihan = TagihanMahasiswa::with('virtualAccount')
                ->where('calon_mahasiswa_id', $pendaftaran->id)
                ->where('source_system', 'SPMB')
                ->where('tipe_referensi', TagihanMahasiswa::TIPE_SPMB_DAFTAR_ULANG)
                ->latest('id')
                ->first();

            if ($duTagihan) {
                $daftarUlangTagihanData = [
                    'tagihan' => $duTagihan,
                    'virtual_account' => $duTagihan->virtualAccount,
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $pendaftaran ? [
                'pendaftaran' => $pendaftaran,
                'kode_diskon_usage' => $pendaftaran->kodeDiskonUsage ? [
                    'id' => $pendaftaran->kodeDiskonUsage->id,
                    'kode_diskon' => $pendaftaran->kodeDiskonUsage->kode_diskon,
                    'subtotal_komponen' => (float) $pendaftaran->kodeDiskonUsage->subtotal_komponen,
                    'nominal_diskon' => (float) $pendaftaran->kodeDiskonUsage->nominal_diskon,
                    'tagihan_id' => $pendaftaran->kodeDiskonUsage->tagihan_id,
                    'digunakan_at' => $pendaftaran->kodeDiskonUsage->digunakan_at,
                ] : null,
                'tagihan' => $tagihanData,
                'daftar_ulang_tagihan' => $daftarUlangTagihanData,
                'tagihan_list' => app(\App\Services\Spmb\SpmbBillingService::class)->envelope(
                    TagihanMahasiswa::where('calon_mahasiswa_id', $pendaftaran->id)->where('source_system', 'SPMB')->orderBy('id')->get()->all()
                )['tagihan_list'],
            ] : null,
        ]);
    }

    /**
     * Submit biodata pendaftaran (Draft)
     */
    public function storeBiodata(StoreBiodataRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        if (array_key_exists('kode_diskon', $validated)) {
            $validated['kode_diskon'] = $validated['kode_diskon'] === null
                ? null
                : (Str::upper(trim((string) $validated['kode_diskon'])) ?: null);
        }

        $existingPendaftaran = PendaftaranCalonMhs::where('user_id', $user->id)->first();
        $existingTagihan = $existingPendaftaran
            ? TagihanMahasiswa::where('calon_mahasiswa_id', $existingPendaftaran->id)
                ->where('source_system', 'SPMB')
                ->where(function ($q) {
                    $q->where('tipe_referensi', 'spmb_pendaftaran')->orWhereNull('tipe_referensi');
                })
                ->first()
            : null;

        if ($existingTagihan && $existingPendaftaran) {
            foreach (['gelombang_id', 'program_studi_id'] as $lockedField) {
                if (array_key_exists($lockedField, $validated)
                    && (int) $validated[$lockedField] !== (int) $existingPendaftaran->{$lockedField}) {
                    throw ValidationException::withMessages([
                        $lockedField => 'Pilihan pendaftaran tidak dapat diubah setelah invoice terbit.',
                    ]);
                }
            }
            if (array_key_exists('kode_diskon', $validated)
                && $validated['kode_diskon'] !== $existingPendaftaran->kode_diskon) {
                throw ValidationException::withMessages([
                    'kode_diskon' => 'Kode diskon tidak dapat diubah setelah invoice terbit.',
                ]);
            }
        }

        if (! $existingTagihan) {
            $gelombangLocked = $existingPendaftaran
                && $existingPendaftaran->gelombang_id
                && $existingPendaftaran->status !== PendaftaranCalonMhs::STATUS_DRAFT;
            $gelombangId = $gelombangLocked
                ? $existingPendaftaran->gelombang_id
                : ($validated['gelombang_id'] ?? $existingPendaftaran?->gelombang_id);
            $gelombang = $gelombangId ? GelombangPenerimaan::find($gelombangId) : null;
            if (! $gelombang || ! $gelombang->pendaftaran_dibuka) {
                throw ValidationException::withMessages([
                    'gelombang_id' => 'Pendaftaran hanya dapat dibuat pada gelombang yang sedang dibuka.',
                ]);
            }
        }

        $previousKodeDiskon = $existingPendaftaran?->kode_diskon;
        $pendaftaran = $this->pendaftaranService->saveBiodata($user, $validated);
        if (array_key_exists('kode_diskon', $validated) && $previousKodeDiskon !== $pendaftaran->kode_diskon) {
            try {
                AuditLogService::record(
                    module: 'SPMB',
                    action: 'update',
                    tableName: 'spmb_pendaftaran_calon_mhs',
                    recordId: $pendaftaran->id,
                    oldValues: ['kode_diskon' => $previousKodeDiskon],
                    newValues: ['kode_diskon' => $pendaftaran->kode_diskon],
                    request: $request,
                );
            } catch (\Throwable $exception) {
                Log::warning('Gagal mencatat audit pilihan kode diskon draft SPMB: '.$exception->getMessage());
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Draft biodata berhasil disimpan.',
            'data' => [
                'pendaftaran' => $pendaftaran,
                'tagihan' => null,
            ],
        ]);
    }

    /** Issue the unpaid registration invoice for the authenticated applicant. */
    public function issueRegistrationInvoice(IssueSpmbRegistrationInvoiceRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $pendaftaran = PendaftaranCalonMhs::query()->where('user_id', $request->user()->id)->firstOrFail();
        $alreadyIssued = false;
        $issued = DB::transaction(function () use ($pendaftaran, $validated, &$alreadyIssued): array {
            $locked = PendaftaranCalonMhs::query()->lockForUpdate()->findOrFail($pendaftaran->id);
            $billing = app(\App\Services\Spmb\SpmbBillingService::class);
            $existingBills = $billing->stageQuery($locked->id)
                ->with(['virtualAccount', 'detailTagihan'])
                ->orderBy('id')
                ->get();
            if ($existingBills->isNotEmpty()) {
                $alreadyIssued = true;
                $requestedCode = array_key_exists('kode_diskon', $validated)
                    ? $validated['kode_diskon']
                    : $locked->kode_diskon;
                $this->kodeDiskonService->ensureCodeMatchesRegistration($locked, $requestedCode);

                return $billing->envelope($existingBills->all());
            }

            $gelombang = $locked->gelombangPenerimaan;
            if (! $gelombang || ! $gelombang->pendaftaran_dibuka) {
                throw ValidationException::withMessages([
                    'gelombang_id' => 'Gelombang pendaftaran sudah tidak dibuka; invoice baru tidak dapat diterbitkan.',
                ]);
            }

            $kodeDiskon = array_key_exists('kode_diskon', $validated)
                ? $validated['kode_diskon']
                : $locked->kode_diskon;
            if ($kodeDiskon !== $locked->kode_diskon) {
                $locked->update(['kode_diskon' => $kodeDiskon]);
            }

            $details = app(MasterBiayaService::class)->buildDetailBebanPendaftaran(
                $locked->gelombangPenerimaan?->jalur_masuk_id,
                $locked->program_studi_id,
                $locked->gelombang_id,
            );
            $potonganCalon = app(\App\Services\Spmb\SpmbPotonganCalonService::class)
                ->buildPotonganPayload($locked, 'pendaftaran', $details);
            $penggunaanDiskon = $this->kodeDiskonService->applyToRegistration(
                $locked,
                $kodeDiskon,
                $details,
                $potonganCalon,
            );
            $potongan = array_merge(
                $potonganCalon,
                $this->kodeDiskonService->toTagihanPotongan($penggunaanDiskon),
            );

            $result = $billing->issue([
                'calon_mahasiswa_id' => $locked->id,
                'tipe_referensi' => TagihanMahasiswa::TIPE_SPMB_PENDAFTARAN,
                'source_system' => 'SPMB',
                'master_biaya_tipe' => 'spmb_adm',
                'requires_approval' => false,
                'allow_local_va' => true,
                'keterangan' => 'Pendaftaran SPMB - '.($locked->nama_lengkap ?? 'Calon Mahasiswa'),
                'details' => $details,
                'potongan' => $potongan,
            ]);
            if ($penggunaanDiskon && ! empty($result['tagihan']?->id)) {
                $this->kodeDiskonService->attachTagihan($penggunaanDiskon, (int) $result['tagihan']->id);
            }

            return [
                'tagihan' => $result['tagihan'],
                'virtual_account' => $result['virtual_account'],
                'tagihan_list' => $result['tagihan_list'],
            ];
        });

        if (! $alreadyIssued && ! empty($issued['tagihan']?->id)) {
            try {
                $usage = $pendaftaran->fresh()->kodeDiskonUsage;
                AuditLogService::record(
                    module: 'SPMB',
                    action: 'create',
                    tableName: 'sikeu_tagihan_mahasiswa',
                    recordId: (int) $issued['tagihan']->id,
                    newValues: [
                        'calon_mahasiswa_id' => $pendaftaran->id,
                        'tipe_referensi' => TagihanMahasiswa::TIPE_SPMB_PENDAFTARAN,
                        'total_tagihan' => (float) $issued['tagihan']->total_tagihan,
                        'total_potongan' => (float) $issued['tagihan']->total_potongan,
                        'kode_diskon' => $usage?->kode_diskon,
                        'kode_diskon_usage_id' => $usage?->id,
                    ],
                    request: $request,
                );
            } catch (\Throwable $exception) {
                Log::warning('Gagal mencatat audit penerbitan invoice SPMB: '.$exception->getMessage());
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => $alreadyIssued
                ? 'Invoice pendaftaran sudah tersedia.'
                : 'Invoice pendaftaran berhasil diterbitkan dan menunggu pembayaran.',
            'data' => $issued,
        ], $alreadyIssued ? 200 : 201);
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

        if ($tagihan && ($tagihan->payment_gateway_config_id || (float) $tagihan->total_bayar > 0)) {
            throw ValidationException::withMessages(['tagihan' => 'Tagihan ini tetap menggunakan kanal yang diterbitkan. Lanjutkan melalui halaman Pembayaran SPMB.']);
        }

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

            $pgConfig = PaymentGatewayConfig::active();
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
            if (TagihanMahasiswa::where('calon_mahasiswa_id', $pendaftaran->id)->where(function ($query) {
                $query->whereNotNull('payment_gateway_config_id')->orWhere('total_bayar', '>', 0);
            })->exists()) {
                throw ValidationException::withMessages(['tagihan' => 'Pendaftaran dengan tagihan kanal pembayaran atau pembayaran tercatat tidak dapat direset.']);
            }
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

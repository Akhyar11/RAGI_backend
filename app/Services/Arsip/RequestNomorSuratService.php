<?php

namespace App\Services\Arsip;

use App\Models\Arsip\RequestNomorSurat;
use App\Services\AuditLogService;
use App\Services\Storage\FileStorageService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RequestNomorSuratService
{
    public function __construct(
        protected NomorSuratService $nomorSuratService,
        protected FileStorageService $fileStorage
    ) {}

    /**
     * Mengambil daftar request nomor surat dengan filter dan pagination.
     */
    public function getPaginated(array $filters = [], int $perPage = 15, ?int $onlyUserId = null): LengthAwarePaginator
    {
        $query = RequestNomorSurat::with([
            'user:id,name,email,username',
            'verifikator:id,name,email',
            'nomorSurat:id,nomor_surat,nomor_urut,tanggal_surat,status,request_id',
        ]);

        if ($onlyUserId) {
            $query->where('user_id', $onlyUserId);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['module_origin'])) {
            $query->where('module_origin', $filters['module_origin']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('kode_request', 'like', "%{$search}%")
                  ->orWhere('perihal', 'like', "%{$search}%")
                  ->orWhere('tujuan', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('username', 'like', "%{$search}%");
                  });
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Mengajukan permohonan nomor surat baru dari modul mana pun.
     */
    public function createRequest(array $data, int $userId, ?UploadedFile $lampiran = null): RequestNomorSurat
    {
        return DB::transaction(function () use ($data, $userId, $lampiran) {
            $year = Carbon::now()->year;
            $randomStr = strtoupper(Str::random(4));
            $kodeRequest = "REQ-ARSIP-{$year}-" . time() . "-{$randomStr}";

            $lampiranPath = null;
            if ($lampiran) {
                $lampiranPath = $this->fileStorage->store($lampiran, 'arsip/lampiran-request', private: true);
            }

            $request = RequestNomorSurat::create([
                'kode_request' => $kodeRequest,
                'module_origin' => $data['module_origin'] ?? 'general',
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => !empty($data['reference_id']) ? (int) $data['reference_id'] : null,
                'user_id' => $userId,
                'perihal' => $data['perihal'],
                'tujuan' => $data['tujuan'] ?? null,
                'tanggal_surat' => $data['tanggal_surat'] ?? Carbon::now()->toDateString(),
                'kode_unit' => strtoupper(trim($data['kode_unit'] ?? 'REK')),
                'kode_klasifikasi' => strtoupper(trim($data['kode_klasifikasi'] ?? 'DII')),
                'jumlah_nomor' => max(1, (int) ($data['jumlah_nomor'] ?? 1)),
                'catatan_pemohon' => $data['catatan_pemohon'] ?? null,
                'dokumen_lampiran_path' => $lampiranPath,
                'status' => 'menunggu_verifikasi',
            ]);

            AuditLogService::record(
                module: 'ARSIP',
                action: 'request_nomor_surat',
                tableName: 'core_arsip_request_nomor',
                recordId: $request->id,
                oldValues: [],
                newValues: $request->toArray()
            );

            return $request->load('user');
        });
    }

    /**
     * Memverifikasi (menyetujui atau menolak) request nomor surat oleh Admin Arsip.
     */
    public function verifyRequest(
        int $requestId, 
        string $action, 
        ?string $catatan, 
        int $verifierId,
        ?string $kodeKlasifikasi = null,
        ?string $kodeUnit = null,
        ?string $perihal = null,
        ?string $tujuan = null
    ): RequestNomorSurat
    {
        return DB::transaction(function () use ($requestId, $action, $catatan, $verifierId, $kodeKlasifikasi, $kodeUnit, $perihal, $tujuan) {
            $request = RequestNomorSurat::findOrFail($requestId);

            if ($request->status !== 'menunggu_verifikasi') {
                throw new InvalidArgumentException("Request nomor surat ini sudah diverifikasi sebelumnya ({$request->status}).");
            }

            $oldValues = $request->toArray();

            if ($action === 'setujui' || $action === 'approve') {
                // Admin Arsip memiliki otoritas menentukan / mengubah klasifikasi, unit, dan redaksi perihal
                if (!empty($kodeKlasifikasi)) {
                    $request->kode_klasifikasi = strtoupper(trim($kodeKlasifikasi));
                }
                if (!empty($kodeUnit)) {
                    $request->kode_unit = strtoupper(trim($kodeUnit));
                }
                if (!empty($perihal)) {
                    $request->perihal = trim($perihal);
                }
                if ($tujuan !== null) {
                    $request->tujuan = trim($tujuan);
                }

                $request->status = 'disetujui';
                $request->verified_by = $verifierId;
                $request->verified_at = Carbon::now();
                $request->catatan_verifikasi = $catatan;
                $request->save();

                // Otomatis generate nomor surat sejumlah yang diminta
                $payloadGenerate = [
                    'tanggal_surat' => $request->tanggal_surat,
                    'kode_unit' => $request->kode_unit,
                    'kode_klasifikasi' => $request->kode_klasifikasi,
                    'perihal' => $request->perihal,
                    'tujuan' => $request->tujuan,
                    'status' => 'terpakai',
                    'module_origin' => $request->module_origin,
                    'request_id' => $request->id,
                    'reference_type' => $request->reference_type,
                    'reference_id' => $request->reference_id,
                    'catatan' => "Digenerate dari verifikasi {$request->kode_request}",
                    'jumlah_nomor' => $request->jumlah_nomor,
                ];

                $generated = null;
                if ($request->jumlah_nomor === 1) {
                    $generated = $this->nomorSuratService->generateSatuan($payloadGenerate, $verifierId);
                } else {
                    $generated = $this->nomorSuratService->generateBulk($payloadGenerate, $verifierId);
                }

                // Callback sinkronisasi ke entitas pemohon (jika ada reference_type & reference_id)
                if ($request->reference_type && $request->reference_id) {
                    $firstNomor = is_array($generated) ? ($generated[0]->nomor_surat ?? null) : ($generated->nomor_surat ?? null);
                    if ($firstNomor) {
                        $this->syncNumberToEntity($request->reference_type, (int) $request->reference_id, $firstNomor);
                    }
                }
            } else {
                $request->status = 'ditolak';
                $request->verified_by = $verifierId;
                $request->verified_at = Carbon::now();
                $request->catatan_verifikasi = $catatan ?? 'Permohonan nomor surat ditolak.';
                $request->save();
            }

            AuditLogService::record(
                module: 'ARSIP',
                action: 'verifikasi_request_nomor_surat',
                tableName: 'core_arsip_request_nomor',
                recordId: $request->id,
                oldValues: $oldValues,
                newValues: $request->fresh()->toArray()
            );

            return $request->load(['user', 'verifikator', 'nomorSurat']);
        });
    }

    /**
     * Sinkronisasi nomor surat resmi yang telah disahkan ke model pemohon (seperti SIMPEG Surat Tugas)
     */
    protected function syncNumberToEntity(string $referenceType, int $referenceId, string $nomorSurat): void
    {
        try {
            if ($referenceType === \App\Models\Simpeg\SuratTugas::class || $referenceType === 'simpeg_surat_tugas' || str_ends_with($referenceType, 'SuratTugas')) {
                $st = \App\Models\Simpeg\SuratTugas::find($referenceId);
                if ($st) {
                    $st->update(['nomor_surat' => $nomorSurat]);

                    // Update referensi eksternal di SIKEU jika ada pengajuan pencairan kas terkait
                    if ($st->sikeu_pencairan_id) {
                        \App\Models\Sikeu\PengajuanPencairanKas::where('id', $st->sikeu_pencairan_id)
                            ->update(['referensi_eksternal' => $nomorSurat]);
                    }
                }
            }

            if ($referenceType === \App\Models\Spmb\PendaftaranCalonMhs::class || $referenceType === 'spmb_pendaftaran' || str_ends_with($referenceType, 'PendaftaranCalonMhs')) {
                $pendaftaran = \App\Models\Spmb\PendaftaranCalonMhs::find($referenceId);
                if ($pendaftaran) {
                    $pendaftaran->update(['nomor_sk' => $nomorSurat]);
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal sinkronisasi nomor surat ke {$referenceType} #{$referenceId}: " . $e->getMessage());
        }
    }
}

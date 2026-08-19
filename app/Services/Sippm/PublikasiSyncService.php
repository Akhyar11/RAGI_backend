<?php

namespace App\Services\Sippm;

use App\Models\Simpeg\Pegawai;
use App\Models\Sippm\PublikasiIlmiah;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PublikasiSyncService
{
    protected ScopusIntegrationService $scopusService;
    protected SintaIntegrationService $sintaService;
    protected AuditLogService $auditLog;

    public function __construct(
        ScopusIntegrationService $scopusService,
        SintaIntegrationService $sintaService,
        AuditLogService $auditLog
    ) {
        $this->scopusService = $scopusService;
        $this->sintaService = $sintaService;
        $this->auditLog = $auditLog;
    }

    /**
     * Fetch publication entries preview from external sources.
     */
    public function fetchExternalData(string $source, string $identifier): array
    {
        $source = strtolower(trim($source));
        $identifier = trim($identifier);

        if (empty($identifier)) {
            throw new InvalidArgumentException("Identifier (DOI, Scopus ID, atau SINTA ID) wajib diisi.");
        }

        if ($source === 'doi') {
            return [$this->scopusService->fetchByDoi($identifier)];
        } elseif ($source === 'scopus') {
            return $this->scopusService->fetchByScopusId($identifier);
        } elseif ($source === 'sinta') {
            return $this->sintaService->fetchBySintaIdOrNidn($identifier);
        } else {
            throw new InvalidArgumentException("Sumber integrasi tidak valid. Gunakan 'doi', 'scopus', atau 'sinta'.");
        }
    }

    /**
     * Save/Import external publication into database and record audit trail.
     */
    public function importExternalPublikasi(Pegawai $pegawai, array $data, ?int $proposalId = null): PublikasiIlmiah
    {
        return DB::transaction(function () use ($pegawai, $data, $proposalId) {
            $existingQuery = PublikasiIlmiah::where('pegawai_id', $pegawai->id);

            if (!empty($data['doi'])) {
                $existingQuery->where('doi', $data['doi']);
            } elseif (!empty($data['scopus_eid'])) {
                $existingQuery->where('scopus_eid', $data['scopus_eid']);
            } elseif (!empty($data['sinta_article_id'])) {
                $existingQuery->where('sinta_article_id', $data['sinta_article_id']);
            } else {
                $existingQuery->where('judul_artikel', $data['judul_artikel']);
            }

            $publikasi = $existingQuery->first();

            $payload = [
                'proposal_id' => $proposalId ?? $data['proposal_id'] ?? null,
                'pegawai_id' => $pegawai->id,
                'judul_artikel' => $data['judul_artikel'],
                'jenis_publikasi' => $data['jenis_publikasi'] ?? 'jurnal_internasional_bereputasi',
                'nama_jurnal_prosiding' => $data['nama_jurnal_prosiding'] ?? 'Jurnal Terakreditasi',
                'indexing' => $data['indexing'] ?? 'scopus_q1',
                'volume_issue_tahun' => $data['volume_issue_tahun'] ?? ('Vol. 1, ' . date('Y')),
                'doi' => $data['doi'] ?? null,
                'url_artikel' => $data['url_artikel'] ?? null,
                'scopus_eid' => $data['scopus_eid'] ?? null,
                'sinta_article_id' => $data['sinta_article_id'] ?? null,
                'citation_count' => (int) ($data['citation_count'] ?? 0),
                'publisher' => $data['publisher'] ?? null,
                'is_verified_lppm' => true,
                'synced_at' => now(),
            ];

            if ($publikasi) {
                $oldData = $publikasi->toArray();
                $publikasi->update($payload);
                
                AuditLogService::record(
                    module: 'SIPPM',
                    action: 'UPDATE_SINTA_SCOPUS_PUBLIKASI',
                    tableName: 'publikasi_ilmiah',
                    recordId: $publikasi->id,
                    oldValues: $oldData,
                    newValues: $publikasi->toArray()
                );
            } else {
                $publikasi = PublikasiIlmiah::create($payload);

                AuditLogService::record(
                    module: 'SIPPM',
                    action: 'IMPORT_SINTA_SCOPUS_PUBLIKASI',
                    tableName: 'publikasi_ilmiah',
                    recordId: $publikasi->id,
                    oldValues: null,
                    newValues: $publikasi->toArray()
                );
            }

            return $publikasi;
        });
    }
}

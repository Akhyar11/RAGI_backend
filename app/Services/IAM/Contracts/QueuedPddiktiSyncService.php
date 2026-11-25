<?php

namespace App\Services\IAM\Contracts;

use App\Models\IamApiIntegration;

/**
 * Service sync PDDIKTI yang dijalankan worker queue: fetch dan persist per batch, progress di cache, lock per integrasi.
 */
interface QueuedPddiktiSyncService
{
    /** Dijalankan worker. Menulis progress bila $progressId diberikan. */
    public function processPddikti(IamApiIntegration $integration, ?string $progressId = null): array;

    /** Awalan kunci cache progress, mis. "siakad-prodi-pddikti-sync:". */
    public function progressPrefix(): string;

    public function lockKey(): string;

    /** Nama entitas untuk pesan, mis. "program studi". */
    public function syncLabel(): string;
}

<?php

namespace App\Jobs;

use App\Services\AuditLogService;
use App\Services\Sikeu\SikeuMigratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class MigrateSikeuJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600; // 1 jam maksimum eksekusi background

    public function __construct(public readonly array $options = [])
    {
        $this->onQueue('migrations');
    }

    public function handle(SikeuMigratorService $service): void
    {
        Log::info('MigrateSikeuJob mulai dieksekusi oleh queue worker', $this->options);

        try {
            $result = $service->runMigration($this->options);
            Log::info('MigrateSikeuJob selesai dengan sukses', $result);

            try {
                AuditLogService::record(
                    module: (string) config('sikeu.migrator.module_key', 'sikeu'),
                    action: 'run_migration',
                    tableName: 'sikeu_jurnal_umum',
                    recordId: null,
                    oldValues: [],
                    newValues: $result,
                    request: null
                );
            } catch (Throwable $e) {
                Log::warning('Gagal mencatat audit log run migration SIKEU: ' . $e->getMessage());
            }
        } catch (Throwable $e) {
            Log::error('MigrateSikeuJob gagal dieksekusi: ' . $e->getMessage(), [
                'exception' => $e,
                'options'   => $this->options,
            ]);
            throw $e;
        }
    }
}

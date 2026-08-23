<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditTableNames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:table-names';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit database table names to ensure they follow the module_table convention.';

    /**
     * List of acceptable module prefixes.
     *
     * @var array
     */
    protected $validPrefixes = [
        'iam_',
        'sikeu_',
        'spmb_',
        'sippm_',
        'simpeg_',
        'sinapra_',
        'siakad_',
        'core_', // for shared/core tables
    ];

    /**
     * List of tables that are exempt from the naming convention (e.g. Laravel defaults).
     *
     * @var array
     */
    protected $exemptTables = [
        'migrations',
        'personal_access_tokens',
        'failed_jobs',
        'password_reset_tokens',
        'oauth_auth_codes',
        'oauth_access_tokens',
        'oauth_refresh_tokens',
        'oauth_clients',
        'oauth_personal_access_clients',
        'oauth_device_codes',
        'oauth_app_clients',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'sessions',
        'password_resets',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Table Name Audit...');

        $tablesRaw = DB::select('SHOW TABLES');
        $tables = array_map('current', json_decode(json_encode($tablesRaw), true));
        $violations = [];

        foreach ($tables as $table) {
            if (in_array($table, $this->exemptTables)) {
                continue;
            }

            $hasValidPrefix = false;
            foreach ($this->validPrefixes as $prefix) {
                if (str_starts_with($table, $prefix)) {
                    $hasValidPrefix = true;
                    break;
                }
            }

            if (!$hasValidPrefix) {
                $violations[] = $table;
            }
        }

        if (!empty($violations)) {
            $this->error('Found ' . count($violations) . ' tables violating the naming convention:');
            foreach ($violations as $violation) {
                $this->line('- ' . $violation);
            }
            $this->newLine();
            $this->error('Audit failed! All domain tables must be prefixed with their module name (e.g. sikeu_..., spmb_...).');
            return Command::FAILURE;
        }

        $this->info('All tables follow the naming convention!');
        return Command::SUCCESS;
    }
}

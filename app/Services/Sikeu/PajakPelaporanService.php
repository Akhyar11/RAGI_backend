<?php

namespace App\Services\Sikeu;

use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\AkunPajak;
use App\Models\Sikeu\DetailJurnalUmum;
use App\Models\Sikeu\JurnalUmum;
use App\Models\SystemSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PajakPelaporanService
{
    private const MODE_SETTING_KEY = 'sikeu.pajak_akun_mode';

    public function listAccounts(array $filters): LengthAwarePaginator
    {
        $allowedSort = ['kode_akun', 'nama_akun', 'updated_at'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSort, true)
            ? $filters['sort_by']
            : 'kode_akun';
        $sortOrder = ($filters['sort_order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $query = AkunKeuangan::with('pajakAlias');
        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($builder) use ($search) {
                $builder->where('kode_akun', 'like', "%{$search}%")
                    ->orWhere('nama_akun', 'like', "%{$search}%")
                    ->orWhereHas('pajakAlias', fn ($alias) => $alias->where('nama_akun_pajak', 'like', "%{$search}%"));
            });
        }

        return $query->orderBy($sortBy, $sortOrder)
            ->paginate(min(100, max(1, (int) ($filters['per_page'] ?? 15))));
    }

    public function currentMode(): string
    {
        return SystemSetting::get(self::MODE_SETTING_KEY, 'dev') === 'publik' ? 'publik' : 'dev';
    }

    public function updateAlias(int $accountId, ?string $alias, ?int $userId): AkunPajak
    {
        return DB::transaction(function () use ($accountId, $alias, $userId) {
            AkunKeuangan::findOrFail($accountId);

            return AkunPajak::updateOrCreate(
                ['akun_id' => $accountId],
                [
                    'nama_akun_pajak' => ($trimmed = trim((string) $alias)) === '' ? null : $trimmed,
                    'updated_by' => $userId,
                ]
            );
        });
    }

    public function updateMode(string $mode, ?int $userId): string
    {
        return DB::transaction(function () use ($mode, $userId) {
            $oldMode = $this->currentMode();
            $setting = SystemSetting::set(
                self::MODE_SETTING_KEY,
                $mode,
                'Mode nama akun khusus pelaporan pajak SIKEU'
            );

            \App\Services\AuditLogService::record(
                module: 'SIKEU',
                action: 'update',
                tableName: 'core_system_settings',
                recordId: $setting->id,
                oldValues: ['key' => self::MODE_SETTING_KEY, 'value' => $oldMode],
                newValues: ['key' => self::MODE_SETTING_KEY, 'value' => $mode, 'updated_by' => $userId]
            );

            return $mode;
        });
    }

    public function applyPublicAliasesToAccounts(iterable $accounts): void
    {
        if ($this->currentMode() !== 'publik') {
            return;
        }

        $accountList = collect($accounts)
            ->filter(fn ($account) => $account instanceof AkunKeuangan)
            ->values();
        if ($accountList->isEmpty()) {
            return;
        }

        $aliases = AkunPajak::query()
            ->whereIn('akun_id', $accountList->pluck('id'))
            ->whereNotNull('nama_akun_pajak')
            ->pluck('nama_akun_pajak', 'akun_id');

        foreach ($accountList as $account) {
            if ($aliases->has($account->id)) {
                $account->setAttribute('nama_akun', $aliases->get($account->id));
            }
        }
    }

    public function applyPublicAliasesToJournals(iterable $journals): void
    {
        if ($this->currentMode() !== 'publik') {
            return;
        }

        $accounts = collect($journals)
            ->flatMap(fn (JurnalUmum $journal) => $journal->details->pluck('akun'))
            ->filter();
        $this->applyPublicAliasesToAccounts($accounts);
    }

    public function applyPublicAliasesToLedgerEntries(iterable $entries): void
    {
        if ($this->currentMode() !== 'publik') {
            return;
        }

        $this->applyPublicAliasesToAccounts(
            collect($entries)->map(fn (DetailJurnalUmum $entry) => $entry->akun)->filter()
        );
    }
}

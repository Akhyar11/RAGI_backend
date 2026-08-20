<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuditLogService
{
    /**
     * Daftar field yang nilainya tidak boleh disimpan di log (akan disensor)
     */
    protected static array $hiddenFields = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'token',
        'access_token',
        'refresh_token',
        'sso_token',
    ];

    /**
     * Merekam aktivitas ke dalam tabel audit_logs
     */
    public static function record(
        string $module,
        string $action,
        string $tableName,
        ?int $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null
    ): void {
        try {
            $request = $request ?? request();

            // Sembunyikan field sensitif
            $safeOldValues = self::sanitize($oldValues);
            $safeNewValues = self::sanitize($newValues);

            AuditLog::create([
                'user_id'    => auth()->id(), // Bisa null jika belum login (e.g. login failed)
                'module'     => strtoupper($module),
                'action'     => strtolower($action),
                'table_name' => $tableName,
                'record_id'  => $recordId,
                'old_values' => $safeOldValues,
                'new_values' => $safeNewValues,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Audit log tidak boleh membuat request gagal, cukup catat ke file log internal
            Log::error('Gagal mencatat audit log: ' . $e->getMessage(), [
                'module' => $module,
                'action' => $action
            ]);
        }
    }

    /**
     * Alias method log untuk kompatibilitas observer/controller
     */
    public static function log(
        string $module,
        string $action,
        string $tableName,
        ?int $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null
    ): void {
        self::record($module, $action, $tableName, $recordId, $oldValues, $newValues, $request);
    }

    /**
     * Menghilangkan field sensitif dari array sebelum disimpan
     */
    protected static function sanitize(?array $data): ?array
    {
        if (empty($data)) {
            return null;
        }

        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), self::$hiddenFields)) {
                $data[$key] = '********';
            }
        }

        return $data;
    }
}

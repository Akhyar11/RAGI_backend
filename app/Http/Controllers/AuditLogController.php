<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    /**
     * Display a listing of the audit logs.
     */
    public function index(Request $request)
    {
        // Pastikan hanya admin (atau user ber-permission) yang bisa melihat
        Gate::authorize('viewAny', AuditLog::class);

        $query = AuditLog::with('user:id,username,email')
            ->orderBy('created_at', 'desc');

        // Fitur pencarian berdasarkan module, action, table_name
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('module', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('table_name', 'like', "%{$search}%");
            });
        }

        // Fitur filter berdasarkan user_id spesifik
        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        $perPage = $request->input('per_page', 15);
        $logs = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data retrieved successfully',
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
            ],
            'filters' => [
                'search' => $search,
                'user_id' => $userId ?? null,
                'sort_by' => 'created_at',
                'sort_order' => 'desc',
            ]
        ]);
    }

    /**
     * Display the specified audit log.
     */
    public function show($id)
    {
        $log = AuditLog::with('user:id,username,email')->find($id);

        if (!$log) {
            return response()->json([
                'status' => 'error',
                'message' => 'Log tidak ditemukan',
                'data' => null
            ], 404);
        }

        Gate::authorize('view', $log);

        return response()->json([
            'status' => 'success',
            'message' => 'Data retrieved successfully',
            'data' => $log
        ]);
    }
}

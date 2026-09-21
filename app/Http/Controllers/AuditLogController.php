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

        $query = AuditLog::with('user:id,username,name,email');

        // Fitur pencarian bebas
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('module', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('table_name', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('payload', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('username', 'like', "%{$search}%")
                         ->orWhere('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('username')) {
            $username = $request->input('username');
            $query->whereHas('user', function ($uq) use ($username) {
                $uq->where('username', 'like', "%{$username}%")
                   ->orWhere('name', 'like', "%{$username}%");
            });
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', "%{$request->action}%");
        }

        if ($request->filled('ip_address')) {
            $query->where('ip_address', 'like', "%{$request->ip_address}%");
        }

        if ($request->filled('payload')) {
            $query->where('payload', 'like', "%{$request->payload}%");
        }

        if ($request->filled('created_at')) {
            $query->whereDate('created_at', $request->created_at);
        }

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
        $allowedSortColumns = ['id', 'user_id', 'action', 'ip_address', 'module', 'table_name', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSortColumns) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

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
                'username' => $request->username,
                'action' => $request->action,
                'ip_address' => $request->ip_address,
                'payload' => $request->payload,
                'created_at' => $request->created_at,
                'user_id' => $userId ?? null,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
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

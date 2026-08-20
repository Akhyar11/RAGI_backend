<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserSessionIam;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UserSessionController extends Controller
{
    /**
     * Display a listing of the active sessions for the authenticated user.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = UserSessionIam::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');
            
        $perPage = $request->input('per_page', 15);
        $sessions = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data retrieved successfully',
            'data' => $sessions->items(),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
                'last_page' => $sessions->lastPage(),
                'from' => $sessions->firstItem(),
                'to' => $sessions->lastItem(),
            ],
            'filters' => [
                'search' => null,
                'sort_by' => 'created_at',
                'sort_order' => 'desc',
            ]
        ]);
    }

    /**
     * Revoke a specific session.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $session = UserSessionIam::where('user_id', $user->id)->find($id);

        if (!$session) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sesi tidak ditemukan atau Anda tidak memiliki akses.'
            ], 404);
        }

        // Revoke the actual passport token if it matches the session
        // In Passport, we can revoke by deleting or updating 'revoked' = 1
        DB::table('oauth_access_tokens')
            ->where('id', $session->token)
            ->update(['revoked' => true]);

        // Also delete from our tracking table
        $session->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Sesi berhasil dihapus (logout dari perangkat).'
        ]);
    }

    /**
     * Revoke all other sessions except the current one.
     */
    public function destroyOthers(Request $request)
    {
        $user = $request->user();
        $currentTokenId = $user->token()->id;

        // Get all sessions except the current one
        $otherSessions = UserSessionIam::where('user_id', $user->id)
            ->where('token', '!=', $currentTokenId)
            ->get();

        if ($otherSessions->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Tidak ada sesi lain yang aktif.'
            ]);
        }

        $otherTokenIds = $otherSessions->pluck('token')->toArray();

        // Revoke passport tokens
        DB::table('oauth_access_tokens')
            ->whereIn('id', $otherTokenIds)
            ->update(['revoked' => true]);

        // Delete from tracking table
        UserSessionIam::whereIn('id', $otherSessions->pluck('id'))->delete();

        return response()->json([
            'status' => 'success',
            'message' => count($otherSessions) . ' sesi lain berhasil dihapus (logout dari perangkat lain).'
        ]);
    }

    /**
     * Admin: Display all active sessions across all users.
     */
    public function adminIndex(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) abort(403);

        $query = UserSessionIam::with('user:id,username,email')->orderBy('created_at', 'desc');
            
        $perPage = $request->input('per_page', 15);
        $sessions = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data retrieved successfully',
            'data' => $sessions->items(),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
                'last_page' => $sessions->lastPage(),
                'from' => $sessions->firstItem(),
                'to' => $sessions->lastItem(),
            ],
            'filters' => [
                'search' => null,
                'sort_by' => 'created_at',
                'sort_order' => 'desc',
            ]
        ]);
    }

    /**
     * Admin: Revoke any specific session globally.
     */
    public function adminDestroy(Request $request, $id)
    {
        $session = UserSessionIam::find($id);

        if (!$session) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sesi tidak ditemukan.'
            ], 404);
        }

        if (!$request->user()->isSuperAdmin()) abort(403);

        DB::table('oauth_access_tokens')
            ->where('id', $session->token)
            ->update(['revoked' => true]);

        $session->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Sesi berhasil diputus paksa (force logout).'
        ]);
    }
}

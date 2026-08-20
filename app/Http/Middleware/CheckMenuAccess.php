<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Menu;
use App\Models\SystemSetting;

class CheckMenuAccess
{
    /**
     * Handle an incoming request dynamically based on DB menu_role relation.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
        }

        // Superadmin & Admin bypass
        $superAdminRole = SystemSetting::where('key', 'superadmin_role')->value('value') ?? 'superadmin';
        if ($user->roles()->whereIn('slug', ['superadmin', 'admin', $superAdminRole])->exists()) {
            return $next($request);
        }

        // 1. Extract target URL from request path
        $targetUrl = '/' . ltrim($request->path(), 'api/');

        // 2. Direct DB query to menus table by url column
        $menu = Menu::where('url', $targetUrl)->first();
        if ($menu) {
            $roleIds = $user->roles()->pluck('roles.id')->toArray();
            $hasAccess = $menu->roles()->whereIn('roles.id', $roleIds)->exists();

            if (!$hasAccess) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access. Menu ini belum diaktifkan untuk role Anda di database.'
                ], 403);
            }
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Grants access when the authenticated user holds *any* of the given
 * permissions, e.g. ->middleware('permission:clients.update,clients.manage').
 */
class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Ce compte est désactivé.'], 403);
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => sprintf(
                'Accès refusé : la permission « %s » est requise.',
                $permissions[0] ?? 'inconnue',
            ),
        ], 403);
    }
}

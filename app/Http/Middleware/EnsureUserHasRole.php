<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $userRole = $user->role->value ?? (string)$user->role;

        if (!in_array($userRole, $roles, true)) {
            return response()->json([
                'message' => 'Access denied: insufficient privileges for role ' . implode('/', $roles),
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token auth for the offline student app.
 *
 * Deliberately not Sanctum: one hashed column and this middleware covers a
 * single-device-per-student product with no extra dependency. Swapping in
 * Sanctum later replaces this file and User::issueApiToken(), nothing else.
 */
class ApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = User::findByApiToken($request->bearerToken());

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        auth()->setUser($user);

        return $next($request);
    }
}

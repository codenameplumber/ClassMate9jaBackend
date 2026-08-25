<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Gate a route to one or more roles. A signed-in user who lands on the
     * wrong dashboard is sent to their own rather than shown a 403 — being in
     * the wrong place is a navigation mistake, not an attack.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! in_array($user->role, $roles, true)) {
            return redirect($user->homeRoute());
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds a teacher who has not been approved by an admin.
 *
 * The role gate answers "is this a teacher"; this answers "has anyone vouched
 * for them". Both are needed: a self-registered account gets the teacher role
 * the moment it is created, and marking other people's children is not
 * something an account should be able to grant itself.
 *
 * They are sent to a page that says what is happening rather than shown a 403.
 * Waiting on a human is not an error, and a dashboard with no classes on it
 * just looks broken.
 */
class EnsureApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->isActive()) {
            return redirect()->route('teacher.pending');
        }

        return $next($request);
    }
}

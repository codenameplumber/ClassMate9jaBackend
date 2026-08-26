<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The offline student app, served as routes rather than as files.
 *
 * Twelve screens, one Blade view. They were twelve near-identical static .html
 * files, which is why their extension showed up in the URL; the shells were
 * byte-for-byte the same apart from a title and a script tag, so they are one
 * view with two parameters now.
 *
 * The pages are gated by the Laravel session. The device still talks to /api
 * with a bearer token, which this controller mints once per session and hands
 * to the page — so a student signs in once, on the web, and the app inside
 * inherits it. There is no second login, and no account living in local
 * storage pretending to be one.
 */
class LearnController extends Controller
{
    /**
     * screen => [page module, title]
     *
     * The keys are the URL segments and the modules are the files already in
     * public/app/js/pages. Adding a screen is a line here.
     */
    public const SCREENS = [
        'dashboard' => ['student-dashboard', 'Dashboard'],
        'subjects' => ['student-subjects', 'My subjects'],
        'scheme' => ['student-scheme', 'Scheme of work'],
        'lesson' => ['student-lesson', 'Lesson'],
        'practice' => ['student-practice', 'Practice'],
        'exam' => ['student-exam', 'Exam'],
        'tutor' => ['student-tutor', 'Ask the tutor'],
        'assignments' => ['student-assignments', 'Classwork'],
        'results' => ['student-results', 'Results'],
        'progress' => ['student-progress', 'My progress'],
        'downloads' => ['student-downloads', 'Downloads'],
        'settings' => ['student-settings', 'Settings'],
    ];

    public function show(Request $request, string $screen): View|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        // A student who has not chosen subjects yet has an app with nothing in
        // it. Send them to finish rather than to an empty dashboard.
        if ($user->isStudent() && ! $user->onboarded_at) {
            return redirect()->route('onboarding');
        }

        [$module, $title] = self::SCREENS[$screen];

        return view('learn.app', [
            'module' => $module,
            'title' => $title,
            'profile' => $user->apiProfile(),
            'apiToken' => $this->sessionToken($request),
        ]);
    }

    /**
     * One API token per browser session.
     *
     * Issuing on every page load would invalidate the token the device is
     * already holding — the stored value is a hash, so the plain text cannot
     * be read back and re-served. Minting once and keeping it in the session
     * gives the app a stable credential and rotates it on the next sign-in,
     * which is the behaviour you want anyway.
     */
    private function sessionToken(Request $request): string
    {
        $token = $request->session()->get('api_token');

        if (! $token) {
            $token = $request->user()->issueApiToken();
            $request->session()->put('api_token', $token);
        }

        return $token;
    }
}

<?php

use App\Http\Controllers\LearnController;
use App\Http\Middleware\EnsureApproved;
use App\Http\Middleware\EnsureRole;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Onboarding;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\TeacherSetup;
use App\Livewire\Auth\VerifyNotice;
use App\Livewire\Admin;
use App\Livewire\ParentArea;
use App\Livewire\Teacher;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'marketing')->name('home');

/* ------------------------------------------------------------------ guest */
Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    // Back to the sign-in page rather than the marketing site: someone who
    // just signed out is far more likely to be switching accounts than to be
    // reading about the product.
    return redirect()->route('login');
})->name('logout');

/* ----------------------------------------------------------- authenticated */
Route::middleware('auth')->group(function () {

    Route::get('/onboarding', Onboarding::class)->name('onboarding');

    // Everyone lands here after signing in and is forwarded to their own app.
    Route::get('/home', fn () => redirect(auth()->user()->homeRoute()))->name('dashboard');

    /* Email verification — a banner, never a blocker, so a demo is never stuck */
    Route::get('/email/verify', VerifyNotice::class)->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->markEmailAsVerified();
            event(new Verified($request->user()));
        }

        return redirect(auth()->user()->homeRoute())->with('status', 'Email verified. Welcome in.');
    })->middleware('signed')->name('verification.verify');

    Route::post('/email/verification-notification', function () {
        request()->user()->sendEmailVerificationNotification();

        return back()->with('status', 'Verification link sent to ' . request()->user()->email);
    })->middleware('throttle:6,1')->name('verification.send');

    /* -------------------------------------------------------------- learn */

    /*
    | The offline student app. Twelve screens, one route, one Blade view.
    |
    | These used to be static files under public/app, which is why the URLs
    | carried a .html extension. They are routes now, gated by the session,
    | and the page hands the device the API token it needs.
    */
    Route::middleware(EnsureRole::class . ':student,admin')
        ->get('/learn/{screen}', [LearnController::class, 'show'])
        ->whereIn('screen', array_keys(LearnController::SCREENS))
        ->name('learn.screen');

    Route::redirect('/learn', '/learn/dashboard');

    /* ------------------------------------------------------------ teacher */

    /*
    | The two teacher pages that sit outside the approval gate.
    |
    | Setup comes first: an admin cannot decide on an account that has not said
    | which school it belongs to or what it teaches. Pending is where they wait
    | afterwards -- an honest holding page rather than a 403 or an empty
    | dashboard.
    */
    Route::middleware(EnsureRole::class . ':teacher,admin')->group(function () {
        Route::get('/teacher/setup', TeacherSetup::class)->name('teacher.setup');
        Route::get('/teacher/pending', Teacher\Pending::class)->name('teacher.pending');
    });

    Route::middleware([EnsureRole::class . ':teacher,admin', EnsureApproved::class])->prefix('teacher')->group(function () {
        Route::get('/', Teacher\Dashboard::class)->name('teacher.dashboard');
        Route::get('/classes', Teacher\Classes::class)->name('teacher.classes');
        Route::get('/assignments', Teacher\Assignments::class)->name('teacher.assignments');
        Route::get('/marking/{assignment?}', Teacher\Marking::class)->name('teacher.marking');
        Route::get('/reports', Teacher\Reports::class)->name('teacher.reports');
    });

    /* ------------------------------------------------------------- parent */
    Route::middleware(EnsureRole::class . ':parent,admin')->prefix('parent')->group(function () {
        Route::get('/', ParentArea\Dashboard::class)->name('parent.dashboard');
        Route::get('/report', ParentArea\Report::class)->name('parent.report');
    });

    /* -------------------------------------------------------------- admin */
    Route::middleware(EnsureRole::class . ':admin')->prefix('admin')->group(function () {
        Route::get('/', Admin\Dashboard::class)->name('admin.dashboard');
        Route::get('/courses', Admin\Courses::class)->name('admin.courses');
        Route::get('/content', Admin\Content::class)->name('admin.content');
        Route::get('/teachers', Admin\Teachers::class)->name('admin.teachers');
        Route::get('/students', Admin\Students::class)->name('admin.students');
        Route::get('/people', Admin\People::class)->name('admin.people');
    });
});

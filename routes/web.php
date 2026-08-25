<?php

use App\Http\Middleware\EnsureRole;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Onboarding;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
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

    return redirect()->route('home');
})->name('logout');

/* ----------------------------------------------------------- authenticated */
Route::middleware('auth')->group(function () {

    Route::get('/onboarding', Onboarding::class)->name('onboarding');

    // Everyone lands here after signing in and is forwarded to their own app.
    Route::get('/home', fn () => redirect(auth()->user()->homeRoute()))->name('dashboard');

    /* Email verification — a banner, never a blocker, so a demo is never stuck */
    Route::get('/email/verify', fn () => view('auth.verify'))->name('verification.notice');

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

    /* ------------------------------------------------------------ teacher */
    Route::middleware(EnsureRole::class . ':teacher,admin')->prefix('teacher')->group(function () {
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
        Route::get('/content', Admin\Content::class)->name('admin.content');
        Route::get('/people', Admin\People::class)->name('admin.people');
    });
});

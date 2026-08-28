<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\TutorController;
use App\Http\Middleware\ApiToken;
use Illuminate\Support\Facades\Route;

/*
| The API surface for the offline student app. Every endpoint exists because a
| designed screen needs it — there is no general-purpose REST layer here, and a
| public API is a permanent contract this product does not need yet.
*/

// Throttled hard: the only unauthenticated write endpoints, and an unlimited
// login endpoint is a password cracker with a free server attached.
Route::middleware('throttle:8,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/forgot-password', [AccountController::class, 'forgotPassword']);
});

Route::middleware(ApiToken::class)->group(function () {
    /* Account */
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/onboarding', [AccountController::class, 'onboard']);
    Route::patch('/me', [AccountController::class, 'update']);
    Route::post('/password', [AccountController::class, 'changePassword']);

    /* Content and progress */
    Route::get('/bootstrap', [SyncController::class, 'bootstrap']);
    Route::post('/state', [SyncController::class, 'pushState']);
    Route::post('/attempts', [SyncController::class, 'recordAttempt']);

    /* Practice, stocked on demand */
    Route::post('/practice', [SyncController::class, 'requestPractice'])->middleware('throttle:10,1');

    /* Classwork set by a teacher */
    Route::get('/assignments', [SyncController::class, 'assignments']);
    Route::get('/assignments/{assignment}', [SyncController::class, 'assignment']);
    Route::post('/submissions', [SyncController::class, 'submit']);
    Route::get('/submissions', [SyncController::class, 'mySubmissions']);

    /* The tutor */
    Route::post('/tutor', [TutorController::class, 'ask']);
});

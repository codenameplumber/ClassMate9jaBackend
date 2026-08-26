<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/**
 * The account screens in the student app: onboarding, settings, and starting a
 * password reset. Each mirrors a Livewire screen on the web so the two paths
 * cannot drift in behaviour.
 */
class AccountController extends Controller
{
    /** Finish onboarding. Mirrors App\Livewire\Auth\Onboarding::finish(). */
    public function onboard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'level' => ['required', 'string', 'in:JSS1,JSS2,JSS3,SS1,SS2,SS3'],
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*' => ['string', 'exists:subjects,slug'],
            'goal' => ['required', 'in:waec,jamb,both,school'],
            'locale' => ['required', 'in:en,pcm,both'],
            'dailyGoal' => ['required', 'integer', 'min:5', 'max:240'],
            'school' => ['nullable', 'string', 'max:190'],
        ]);

        $user = auth()->user();

        $user->forceFill([
            'level' => $data['level'],
            'goal' => $data['goal'],
            'locale' => $data['locale'],
            'daily_goal' => $data['dailyGoal'],
            'onboarded_at' => now(),
        ])->save();

        $user->subjects()->sync(
            Subject::whereIn('slug', $data['subjects'])->pluck('id')
        );

        return response()->json(['ok' => true, 'user' => $this->profile($user->fresh())]);
    }

    /** Update settings from the student app. */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:3', 'max:120'],
            'level' => ['sometimes', 'string', 'in:JSS1,JSS2,JSS3,SS1,SS2,SS3'],
            'locale' => ['sometimes', 'in:en,pcm,both'],
            'dailyGoal' => ['sometimes', 'integer', 'min:5', 'max:240'],
            'subjects' => ['sometimes', 'array'],
            'subjects.*' => ['string', 'exists:subjects,slug'],
        ]);

        $user = auth()->user();

        $user->fill(array_filter([
            'name' => $data['name'] ?? null,
            'level' => $data['level'] ?? null,
            'locale' => $data['locale'] ?? null,
            'daily_goal' => $data['dailyGoal'] ?? null,
        ], fn ($v) => $v !== null))->save();

        if (isset($data['subjects'])) {
            $user->subjects()->sync(Subject::whereIn('slug', $data['subjects'])->pluck('id'));
        }

        return response()->json(['ok' => true, 'user' => $this->profile($user->fresh())]);
    }

    /**
     * Start a password reset.
     *
     * Always reports success. Confirming which addresses have accounts would
     * turn this endpoint into a user-list oracle for anyone with the app.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink(['email' => $data['email']]);

        return response()->json([
            'ok' => true,
            'message' => 'If that address has an account, a reset link is on its way.',
        ]);
    }

    private function profile(User $user): array
    {
        return $user->apiProfile();
    }
}

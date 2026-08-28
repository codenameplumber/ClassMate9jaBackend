<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
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
            'school' => ['sometimes', 'nullable', 'string', 'max:190'],
            'subjects' => ['sometimes', 'array'],
            'subjects.*' => ['string', 'exists:subjects,slug'],
        ]);

        $user = auth()->user();

        // Match an existing school by name before making another one, or the
        // same school ends up in the table once per student who types it.
        $schoolId = null;

        if (array_key_exists('school', $data) && filled($data['school'])) {
            $schoolId = School::firstOrCreate(['name' => trim($data['school'])])->id;
        }

        $user->fill(array_filter([
            'name' => $data['name'] ?? null,
            'level' => $data['level'] ?? null,
            'locale' => $data['locale'] ?? null,
            'daily_goal' => $data['dailyGoal'] ?? null,
            'school_id' => $schoolId,
        ], fn ($v) => $v !== null))->save();

        if (isset($data['subjects'])) {
            $user->subjects()->sync(Subject::whereIn('slug', $data['subjects'])->pluck('id'));
        }

        return response()->json(['ok' => true, 'user' => $this->profile($user->fresh())]);
    }

    /**
     * Change the password from inside the app.
     *
     * The current one is required: a device left unlocked on a desk should not
     * be enough to lock its owner out of their own account. Every other token
     * is dropped afterwards, so a session someone else still holds dies with
     * the old password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $user = auth()->user();

        if (! Hash::check($data['current'], $user->password)) {
            return response()->json([
                'message' => 'That is not your current password.',
            ], 422);
        }

        $user->forceFill(['password' => $data['password']])->save();

        return response()->json([
            'ok' => true,
            'token' => $user->issueApiToken(),
            'message' => 'Password changed.',
        ]);
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

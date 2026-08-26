<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Those details do not match any account.'], 422);
        }

        return response()->json([
            'token' => $user->issueApiToken(),
            'user' => $this->profile($user),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'student',
            'locale' => 'both',
        ]);

        return response()->json([
            'token' => $user->issueApiToken(),
            'user' => $this->profile($user),
        ], 201);
    }

    public function me(): JsonResponse
    {
        return response()->json(['user' => $this->profile(auth()->user())]);
    }

    public function logout(): JsonResponse
    {
        auth()->user()->forceFill(['api_token' => null])->save();

        return response()->json(['message' => 'Signed out.']);
    }

    private function profile(User $user): array
    {
        return $user->apiProfile();
    }
}

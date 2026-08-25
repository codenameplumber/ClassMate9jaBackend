<?php

namespace App\Livewire\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('New password')]
class ResetPassword extends Component
{
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function resetPassword(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], ['password.confirmed' => 'The two passwords do not match.']);

        $status = Password::reset([
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
            'token' => $this->token,
        ], function ($user) {
            $user->forceFill([
                'password' => $this->password,
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PasswordReset) {
            $this->addError('email', __($status));

            return;
        }

        session()->flash('status', 'Password changed. You can log in now.');
        $this->redirect(route('login'), navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.reset-password');
    }
}

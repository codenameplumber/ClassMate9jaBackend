<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Log in')]
class Login extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = true;

    /** Fills the form for a judge or tester. Demo accounts only. */
    public function useDemo(string $role): void
    {
        $this->email = $role . '@classmate9ja.ng';
        $this->password = 'password';
    }

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], [], ['email' => 'email address']);

        $key = 'login:' . Str::lower($this->email) . '|' . request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', 'Too many attempts. Try again in '
                . RateLimiter::availableIn($key) . ' seconds.');

            return;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key, 60);
            $this->addError('email', 'Those details do not match any account.');

            return;
        }

        RateLimiter::clear($key);
        session()->regenerate();

        $this->redirect(Auth::user()->homeRoute(), navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}

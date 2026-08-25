<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Forgot password')]
class ForgotPassword extends Component
{
    public string $email = '';

    public function sendLink(): void
    {
        $this->validate(['email' => ['required', 'email']], [], ['email' => 'email address']);

        $status = Password::sendResetLink(['email' => $this->email]);

        // Always report success: confirming which addresses exist would leak
        // the user list to anyone with the login page open.
        session()->flash('status', 'If that address has an account, a reset link is on its way.');
        $this->reset('email');
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}

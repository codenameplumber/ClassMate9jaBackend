<?php

namespace App\Livewire\Auth;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "Check your email" after signing up.
 *
 * Was a plain Blade view reaching for <x-layouts.guest>, which resolves under
 * resources/views/components — a directory this application does not have. The
 * route therefore returned a 500 for anyone who followed it. A component gets
 * the layout the way every other full-page screen here does.
 */
#[Layout('layouts.guest')]
#[Title('Verify your email')]
class VerifyNotice extends Component
{
    public function resend(): void
    {
        request()->user()->sendEmailVerificationNotification();

        session()->flash('status', 'Verification link sent to ' . request()->user()->email);
    }

    public function render()
    {
        return view('livewire.auth.verify-notice');
    }
}

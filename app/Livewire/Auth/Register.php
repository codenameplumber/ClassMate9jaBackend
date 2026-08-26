<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Create account')]
class Register extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $role = 'student';
    public bool $terms = false;

    public function mount(): void
    {
        $requested = request()->query('role');
        if (in_array($requested, ['student', 'teacher', 'parent'], true)) {
            $this->role = $requested;
        }
    }

    public function register(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => ['required', 'in:student,teacher,parent'],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => 'Please accept the terms to continue.',
            'password.confirmed' => 'The two passwords do not match.',
        ], ['email' => 'email address']);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'locale' => 'both',

            // Anyone can claim to be a teacher; marking other people's children
            // is not a thing an account gets to grant itself. Students and
            // parents are approved on sight, because nothing they can reach
            // belongs to anybody else.
            'status' => $data['role'] === 'teacher' ? 'pending' : 'approved',
            'approved_at' => $data['role'] === 'teacher' ? null : now(),
        ]);

        event(new Registered($user));
        Auth::login($user, remember: true);
        session()->regenerate();

        $this->redirect($user->homeRoute(), navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}

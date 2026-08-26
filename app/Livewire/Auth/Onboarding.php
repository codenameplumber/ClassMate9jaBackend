<?php

namespace App\Livewire\Auth;

use App\Models\Subject;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Set up your account')]
class Onboarding extends Component
{
    public int $step = 1;

    public string $level = 'SS2';
    public string $school = '';
    public array $subjects = [];
    public string $goal = 'both';
    public string $locale = 'both';
    public int $daily_goal = 30;

    public const LEVELS = ['JSS1', 'JSS2', 'JSS3', 'SS1', 'SS2', 'SS3'];

    public function mount()
    {
        $user = auth()->user();

        // Teachers have a wizard of their own -- school, subjects, levels --
        // because an admin cannot approve an account that has not said what it
        // is for.
        if ($user->isTeacher()) {
            return $this->redirect(route('teacher.setup'), navigate: false);
        }

        // Parents and admins have nothing to choose.
        if (! $user->isStudent()) {
            $user->forceFill(['onboarded_at' => now()])->save();

            return $this->redirect($user->homeRoute(), navigate: false);
        }

        $this->level = $user->level ?? 'SS2';
        $this->school = $user->school?->name ?? '';
        $this->subjects = $user->subjects()->pluck('subjects.id')->all();
    }

    public function steps(): int { return 4; }

    public function toggleSubject(int $id): void
    {
        $this->subjects = in_array($id, $this->subjects, true)
            ? array_values(array_diff($this->subjects, [$id]))
            : [...$this->subjects, $id];
    }

    public function selectCore(): void
    {
        $this->subjects = Subject::where('is_core', true)->pluck('id')->all();
    }

    public function next(): void
    {
        if ($this->step === 2 && count($this->subjects) === 0) {
            $this->addError('subjects', 'Pick at least one subject.');

            return;
        }

        $this->resetErrorBag();

        if ($this->step < $this->steps()) {
            $this->step++;

            return;
        }

        $this->finish();
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function skip(): void
    {
        if (! $this->subjects) {
            $this->selectCore();
        }
        $this->finish();
    }

    private function finish(): void
    {
        $user = auth()->user();

        $user->forceFill([
            'level' => $this->level,
            'goal' => $this->goal,
            'locale' => $this->locale,
            'daily_goal' => $this->daily_goal,
            'onboarded_at' => now(),
        ])->save();

        $user->subjects()->sync($this->subjects);

        session()->flash('status', 'You are all set. Welcome to ClassMate 9ja!');
        $this->redirect(route('learn.screen', 'dashboard'), navigate: false);
    }

    public function render()
    {
        // Only subjects that are actually taught at the level chosen: offering
        // a JSS1 student Further Maths is offering them an empty app.
        $allSubjects = Subject::with('levels')
            ->orderByDesc('is_core')
            ->orderBy('name')
            ->get()
            ->filter(fn ($subject) => $subject->offersLevel($this->level))
            ->values();

        return view('livewire.auth.onboarding', [
            'allSubjects' => $allSubjects,
            'levels' => self::LEVELS,
        ]);
    }
}

<?php

namespace App\Livewire\Auth;

use App\Models\School;
use App\Models\Subject;
use App\Support\Levels;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * What a teacher tells us before an admin looks at them.
 *
 * Deliberately in front of the approval gate rather than behind it: an
 * administrator cannot sensibly approve an account that has not said which
 * school it belongs to or what it intends to teach. This screen is what turns
 * "someone registered" into a request somebody can actually decide on.
 *
 * Subjects are picked per level, because that is how teaching actually works —
 * the same person takes Maths at SS1 and SS2 but not at JSS1, and a pivot with
 * the level on it is the only honest way to record that.
 */
#[Layout('layouts.guest')]
#[Title('Set up your teaching')]
class TeacherSetup extends Component
{
    public string $school = '';

    public ?int $schoolId = null;

    public string $phone = '';

    /** subject id => list of levels, as chosen on screen. */
    public array $teaching = [];

    public function mount()
    {
        $user = auth()->user();

        if (! $user->isTeacher()) {
            return $this->redirect($user->homeRoute(), navigate: false);
        }

        $this->schoolId = $user->school_id;
        $this->school = $user->school?->name ?? '';
        $this->phone = $user->phone ?? '';

        foreach ($user->teachingSubjects as $subject) {
            $this->teaching[$subject->id][] = $subject->pivot->level;
        }
    }

    /** Tick or untick one subject at one level. */
    public function toggle(int $subjectId, string $level): void
    {
        if (! Levels::isValid($level)) {
            return;
        }

        $current = $this->teaching[$subjectId] ?? [];

        $this->teaching[$subjectId] = in_array($level, $current, true)
            ? array_values(array_diff($current, [$level]))
            : [...$current, $level];

        if (! $this->teaching[$subjectId]) {
            unset($this->teaching[$subjectId]);
        }

        $this->resetErrorBag('teaching');
    }

    public function isTeaching(int $subjectId, string $level): bool
    {
        return in_array($level, $this->teaching[$subjectId] ?? [], true);
    }

    public function submit(): void
    {
        $this->validate([
            'school' => ['required', 'string', 'min:3', 'max:190'],
            'phone' => ['nullable', 'string', 'max:20'],
        ], [], ['school' => 'school name']);

        if (! $this->teaching) {
            $this->addError('teaching', 'Choose at least one subject and the level you teach it at.');

            return;
        }

        $user = auth()->user();

        // Match an existing school by name before making another one, or three
        // teachers from the same school end up in three different schools.
        $school = School::firstOrCreate(['name' => trim($this->school)]);

        $user->forceFill([
            'school_id' => $school->id,
            'phone' => $this->phone ?: null,
            'subject_slug' => Subject::find(array_key_first($this->teaching))?->slug,
            'onboarded_at' => now(),
            'status' => $user->isApproved() ? 'approved' : 'pending',
        ])->save();

        $user->syncTeaching($this->teaching);

        session()->flash('status', 'Sent to your administrator.');

        $this->redirect($user->fresh()->homeRoute(), navigate: false);
    }

    public function render()
    {
        // Only offerings that exist. A teacher cannot be assigned to a level of
        // a subject the platform does not teach.
        $subjects = Subject::with('levels')
            ->orderByDesc('is_core')
            ->orderBy('name')
            ->get()
            ->map(fn ($subject) => [
                'subject' => $subject,
                'levels' => $subject->levels
                    ->sortBy(fn ($l) => array_search($l->level, Levels::ALL))
                    ->pluck('level')
                    ->all(),
            ])
            ->filter(fn ($row) => $row['levels'])
            ->values();

        return view('livewire.auth.teacher-setup', [
            'subjects' => $subjects,
            'schools' => School::orderBy('name')->pluck('name')->all(),
        ]);
    }
}

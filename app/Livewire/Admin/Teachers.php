<?php

namespace App\Livewire\Admin;

use App\Models\Subject;
use App\Models\User;
use App\Support\Levels;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Approving teachers, and seeing what they have actually done since.
 *
 * Pending accounts sort to the top and stay there, because the cost of a
 * forgotten one is a real teacher who cannot mark. Everything else on the row
 * exists to make the decision without leaving the page: what they say they
 * teach, which school they claim, and — for accounts already in — how many
 * classes and scripts they are carrying.
 */
#[Layout('layouts.app')]
class Teachers extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'pending';

    /** The account open in the assign-subjects panel. */
    public ?int $editingId = null;

    /** subject id => levels, while the panel is open. */
    public array $teaching = [];

    public string $rejectReason = '';

    public ?int $rejectingId = null;

    public function updatedSearch(): void { $this->resetPage(); }

    public function updatedStatus(): void { $this->resetPage(); }

    /* ------------------------------------------------------------ decide */

    public function approve(int $id): void
    {
        $teacher = $this->teacher($id);

        $teacher->approve(auth()->user());

        session()->flash('status', $teacher->name . ' can now sign in and mark.');
    }

    public function startReject(int $id): void
    {
        $this->rejectingId = $id;
        $this->rejectReason = '';
    }

    public function cancelReject(): void
    {
        $this->rejectingId = null;
        $this->rejectReason = '';
    }

    public function reject(): void
    {
        $teacher = $this->teacher($this->rejectingId);

        // A reason is not optional. The teacher is shown it, and "no" with no
        // explanation is how a school ends up with the same person applying
        // three times.
        $this->validate([
            'rejectReason' => ['required', 'string', 'min:5', 'max:500'],
        ], [], ['rejectReason' => 'reason']);

        $teacher->reject($this->rejectReason, auth()->user());

        $this->cancelReject();

        session()->flash('status', $teacher->name . ' was turned down.');
    }

    /* ------------------------------------------------- assign what they teach */

    public function edit(int $id): void
    {
        $teacher = $this->teacher($id);

        $this->editingId = $id;
        $this->teaching = [];

        foreach ($teacher->teachingSubjects as $subject) {
            $this->teaching[$subject->id][] = $subject->pivot->level;
        }
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->teaching = [];
    }

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
    }

    public function isTeaching(int $subjectId, string $level): bool
    {
        return in_array($level, $this->teaching[$subjectId] ?? [], true);
    }

    public function saveTeaching(): void
    {
        $teacher = $this->teacher($this->editingId);

        $teacher->syncTeaching($this->teaching);

        session()->flash('status', 'Updated what ' . $teacher->firstName() . ' teaches.');

        $this->cancelEdit();
    }

    /* ------------------------------------------------------------------ */

    /**
     * Load a teacher by id, or refuse.
     *
     * The ids arrive as Livewire properties, which the browser can set to
     * anything. Approving a student into a teacher account by posting their id
     * would be a very cheap privilege escalation, so the role is checked here
     * rather than assumed from the list the page happened to render.
     */
    private function teacher(?int $id): User
    {
        abort_unless($id !== null, 404);

        $user = User::with('teachingSubjects')->find($id);

        abort_unless($user && $user->isTeacher(), 404, 'No such teacher.');

        return $user;
    }

    public function render()
    {
        $teachers = User::query()
            ->where('role', 'teacher')
            ->with(['school', 'teachingSubjects'])
            ->withCount([
                'classrooms',
                'classrooms as students_count' => fn ($q) => $q->join(
                    'classroom_student', 'classroom_student.classroom_id', '=', 'classrooms.id'
                ),
            ])
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")))
            // Pending first whatever else is on screen: a forgotten one is a
            // teacher who cannot work.
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'rejected')")
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.teachers', [
            'teachers' => $teachers,
            'pendingCount' => User::pendingTeachers()->count(),
            'subjects' => Subject::with('levels')->orderByDesc('is_core')->orderBy('name')->get(),
            'editing' => $this->editingId ? User::find($this->editingId) : null,
        ])->title('Teachers');
    }
}

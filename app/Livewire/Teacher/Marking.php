<?php

namespace App\Livewire\Teacher;

use App\Models\Assignment;
use App\Models\Submission;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The batch marking queue.
 *
 * The claim this screen has to earn is "thirty scripts in five minutes", so the
 * AI marks first and the teacher adjudicates. Anything the marker was unsure
 * about is pushed to the top and flagged; everything else is one key away from
 * approved.
 */
#[Layout('layouts.app')]
class Marking extends Component
{
    public ?int $assignmentId = null;
    public int $index = 0;

    /** Teacher overrides, keyed submissionId => [answerId => mark]. */
    public array $overrides = [];

    public int $approvedThisSession = 0;

    public function mount(?Assignment $assignment = null): void
    {
        // An assignment can arrive by URL, so ownership is checked here rather
        // than assumed. Without this, any signed-in teacher could read — and
        // approve — another teacher's class by guessing an id.
        if ($assignment?->exists && ! $this->mayMark($assignment)) {
            abort(403, 'That assignment belongs to another teacher.');
        }

        $this->assignmentId = $assignment?->exists
            ? $assignment->id
            : $this->firstAssignmentWithWork();

        $this->index = $this->firstPendingIndex();
    }

    /* ------------------------------------------------------------------ */

    /** Admins may mark anywhere; a teacher only inside their own classrooms. */
    private function mayMark(Assignment $assignment): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() || $assignment->classroom?->teacher_id === $user?->id;
    }

    private function teacherAssignments()
    {
        return Assignment::with('classroom')
            ->when(
                ! auth()->user()?->isAdmin(),
                fn ($q) => $q->whereHas('classroom', fn ($c) => $c->where('teacher_id', auth()->id()))
            )
            ->withCount('submissions')
            ->having('submissions_count', '>', 0)
            ->get();
    }

    private function firstAssignmentWithWork(): ?int
    {
        $withPending = Assignment::whereHas('classroom', fn ($q) => $q->where('teacher_id', auth()->id()))
            ->whereHas('submissions', fn ($q) => $q->where('status', '!=', 'approved'))
            ->first();

        return $withPending?->id ?? $this->teacherAssignments()->first()?->id;
    }

    /**
     * The single chokepoint for "which assignment am I marking".
     *
     * assignmentId is a Livewire property, which means the browser can set it
     * to anything. Every read goes through here, so an id belonging to another
     * teacher resolves to null and no script is ever loaded, listed, or
     * approved.
     */
    public function getAssignmentProperty(): ?Assignment
    {
        if (! $this->assignmentId) {
            return null;
        }

        $assignment = Assignment::with('classroom', 'questions')->find($this->assignmentId);

        return $assignment && $this->mayMark($assignment) ? $assignment : null;
    }

    /** Reset to the first script whenever the teacher switches assignment. */
    public function updatedAssignmentId(): void
    {
        $this->overrides = [];
        $this->index = $this->firstPendingIndex();
    }

    /** Pending first, and inside that the ones the marker flagged. */
    public function getScriptsProperty()
    {
        // Reads the guarded accessor, never the raw property, so an assignment
        // this teacher may not mark yields an empty queue.
        $assignment = $this->assignment;

        if (! $assignment) {
            return collect();
        }

        return Submission::with(['student', 'answers.question'])
            ->where('assignment_id', $assignment->id)
            ->get()
            ->sortBy([
                fn ($a, $b) => ($a->isApproved() ? 1 : 0) <=> ($b->isApproved() ? 1 : 0),
                fn ($a, $b) => ($b->needs_review ? 1 : 0) <=> ($a->needs_review ? 1 : 0),
                fn ($a, $b) => $a->student->name <=> $b->student->name,
            ])
            ->values();
    }

    public function getCurrentProperty(): ?Submission
    {
        return $this->scripts[$this->index] ?? null;
    }

    private function firstPendingIndex(): int
    {
        $i = $this->scripts->search(fn ($s) => ! $s->isApproved());

        return $i === false ? 0 : $i;
    }

    /* --------------------------------------------------------- marking ---- */

    public function markFor(Submission $submission, $answer): int
    {
        return $this->overrides[$submission->id][$answer->id]
            ?? $answer->teacher_mark
            ?? $answer->ai_mark
            ?? 0;
    }

    public function scoreFor(Submission $submission): int
    {
        return $submission->answers->sum(fn ($answer) => $this->markFor($submission, $answer));
    }

    public function step(int $answerId, int $delta): void
    {
        $submission = $this->current;
        if (! $submission) {
            return;
        }

        $answer = $submission->answers->firstWhere('id', $answerId);
        if (! $answer) {
            return;
        }

        $max = $answer->question->max_mark;
        $now = $this->markFor($submission, $answer);

        $this->overrides[$submission->id][$answerId] = max(0, min($max, $now + $delta));
    }

    public function selectScript(int $i): void
    {
        $this->index = max(0, min($this->scripts->count() - 1, $i));
    }

    public function previous(): void
    {
        $this->index = max(0, $this->index - 1);
    }

    /** Approve the open script and jump to the next one still pending. */
    public function approve(): void
    {
        $submission = $this->current;
        if (! $submission) {
            return;
        }

        if (! $submission->isApproved()) {
            $this->commit($submission);
            $this->approvedThisSession++;
        }

        $scripts = $this->scripts;
        $next = $scripts->skip($this->index + 1)->search(fn ($s) => ! $s->isApproved());

        if ($next !== false) {
            $this->index = $this->index + 1 + $next;

            return;
        }

        $stillPending = $scripts->search(fn ($s) => ! $s->isApproved());
        if ($stillPending === false) {
            session()->flash('status', 'Every script approved. Marking done.');
        } else {
            $this->index = $stillPending;
        }
    }

    /** Approve everything the marker was confident about, leaving the rest. */
    public function approveConfident(): void
    {
        $confident = $this->scripts->filter(fn ($s) => ! $s->isApproved() && ! $s->needs_review);

        foreach ($confident as $submission) {
            $this->commit($submission);
        }

        $this->approvedThisSession += $confident->count();
        $this->index = $this->firstPendingIndex();

        session()->flash('status', $confident->count() . ' scripts approved. '
            . $this->scripts->filter(fn ($s) => ! $s->isApproved())->count()
            . ' flagged scripts left for you.');
    }

    private function commit(Submission $submission): void
    {
        foreach ($submission->answers as $answer) {
            $mark = $this->markFor($submission, $answer);

            if ($mark !== $answer->ai_mark) {
                $answer->update(['teacher_mark' => $mark]);
            }
        }

        $submission->update([
            'final_score' => $this->scoreFor($submission),
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        unset($this->overrides[$submission->id]);
    }

    public function render()
    {
        $scripts = $this->scripts;

        return view('livewire.teacher.marking', [
            'assignments' => $this->teacherAssignments(),
            'assignment' => $this->assignment,
            'scripts' => $scripts,
            'current' => $this->current,
            'pending' => $scripts->filter(fn ($s) => ! $s->isApproved())->count(),
            'floor' => (int) config('ai.confidence_floor', 80),
        ])->title('Marking queue');
    }
}

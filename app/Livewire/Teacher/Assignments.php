<?php

namespace App\Livewire\Teacher;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\AssignmentDrafter;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Assignments extends Component
{
    public bool $drafting = false;

    public ?int $classroomId = null;
    public ?int $topicId = null;
    public string $kind = 'classwork';
    public int $count = 5;
    public string $dueOn = '';

    /** Filled once the drafter has run, before the teacher publishes. */
    public array $draft = [];

    /** Which question is open for editing, if any. */
    public ?int $editing = null;

    /* ------------------------------------------------------------ editing

       A draft is a suggestion, not a paper. The drafter pulls from the scheme
       or from a model, and both get things wrong: an option that gives the
       answer away, a mark allocation that does not match how the teacher
       teaches it, a question about a topic the class has not reached. Sending
       that to thirty students unchanged is how a teacher stops trusting the
       tool, so nothing here is publishable without being editable first.
       -------------------------------------------------------------------- */

    public function editQuestion(int $index): void
    {
        if (isset($this->draft['questions'][$index])) {
            $this->editing = $index;
        }
    }

    public function stopEditing(): void
    {
        $this->editing = null;
    }

    public function removeQuestion(int $index): void
    {
        if (! isset($this->draft['questions'][$index])) {
            return;
        }

        unset($this->draft['questions'][$index]);
        $this->draft['questions'] = array_values($this->draft['questions']);
        $this->editing = null;

        if (! $this->draft['questions']) {
            // A paper with no questions is not work, it is an empty screen.
            $this->draft = [];
        }
    }

    public function addQuestion(string $kind = 'objective'): void
    {
        if (! isset($this->draft['questions'])) {
            return;
        }

        $this->draft['questions'][] = $kind === 'written'
            ? [
                'kind' => 'written',
                'prompt' => '',
                'options' => null,
                'answer_index' => null,
                'expected' => '',
                'max_mark' => 5,
            ]
            : [
                'kind' => 'objective',
                'prompt' => '',
                'options' => ['', '', '', ''],
                'answer_index' => 0,
                'expected' => '',
                'max_mark' => 2,
            ];

        $this->editing = count($this->draft['questions']) - 1;
    }

    /** Mark the nominated option as the correct one. */
    public function setAnswer(int $index, int $option): void
    {
        if (($this->draft['questions'][$index]['kind'] ?? null) !== 'objective') {
            return;
        }

        $this->draft['questions'][$index]['answer_index'] = $option;
        $this->draft['questions'][$index]['expected'] =
            $this->draft['questions'][$index]['options'][$option] ?? '';
    }

    public function moveQuestion(int $index, int $direction): void
    {
        $to = $index + $direction;
        $questions = $this->draft['questions'] ?? [];

        if (! isset($questions[$index], $questions[$to])) {
            return;
        }

        [$questions[$index], $questions[$to]] = [$questions[$to], $questions[$index]];

        $this->draft['questions'] = $questions;
        $this->editing = $to;
    }

    /**
     * Everything wrong with the paper, in the teacher's words.
     *
     * Checked before publishing rather than while typing: a half-written
     * question is a normal state to be in, and shouting about it mid-sentence
     * is how a form becomes unusable.
     *
     * @return array<int, string>
     */
    public function draftProblems(): array
    {
        $problems = [];

        foreach ($this->draft['questions'] ?? [] as $i => $q) {
            $n = $i + 1;

            if (trim((string) ($q['prompt'] ?? '')) === '') {
                $problems[] = "Question {$n} has no question in it.";
            }

            if ((int) ($q['max_mark'] ?? 0) < 1) {
                $problems[] = "Question {$n} is worth no marks.";
            }

            if (($q['kind'] ?? '') !== 'objective') {
                if (trim((string) ($q['expected'] ?? '')) === '') {
                    $problems[] = "Question {$n} has no marking scheme, so nobody can mark it.";
                }

                continue;
            }

            $options = array_values(array_filter(
                array_map('trim', (array) ($q['options'] ?? [])),
                fn ($o) => $o !== ''
            ));

            if (count($options) < 2) {
                $problems[] = "Question {$n} needs at least two options.";
            }

            if (! isset(($q['options'] ?? [])[$q['answer_index'] ?? -1])
                || trim((string) $q['options'][$q['answer_index']]) === '') {
                $problems[] = "Question {$n} has no correct answer marked.";
            }
        }

        return $problems;
    }

    public function mount(): void
    {
        $this->classroomId = Classroom::where('teacher_id', auth()->id())->value('id');
        $this->dueOn = now()->addDays(4)->toDateString();
    }

    public function openDrafter(): void
    {
        $this->drafting = true;
        $this->draft = [];
        $this->editing = null;
        $this->resetErrorBag();
        $this->topicId ??= $this->topics()->first()?->id;
    }

    public function topics()
    {
        $classroom = Classroom::find($this->classroomId);
        $subjectId = $classroom?->subject_id
            ?? Subject::where('slug', auth()->user()->subject_slug ?? 'maths')->value('id');

        return Topic::where('subject_id', $subjectId)
            ->orderBy('week')->orderBy('position')
            ->get();
    }

    public function generate(AssignmentDrafter $drafter): void
    {
        $topic = Topic::find($this->topicId);
        if (! $topic) {
            $this->addError('topicId', 'Choose a topic first.');

            return;
        }

        $this->draft = $drafter->draft($topic, $this->count, $this->kind);
    }

    public function publish(): void
    {
        // A paper only leaves this screen once it is answerable and markable.
        if ($problems = $this->draftProblems()) {
            $this->addError('draft', implode(' ', $problems));

            return;
        }

        $classroom = Classroom::findOrFail($this->classroomId);
        $topic = Topic::find($this->topicId);

        $assignment = Assignment::create([
            'classroom_id' => $classroom->id,
            'topic_id' => $topic?->id,
            'created_by' => auth()->id(),
            'title' => $this->draft['title'],
            'kind' => $this->kind,
            'ai_generated' => $this->draft['generated'] ?? false,
            'question_count' => count($this->draft['questions']),
            'due_on' => $this->dueOn,
            'published_at' => now(),
        ]);

        foreach ($this->draft['questions'] as $i => $q) {
            // Blank options are dropped rather than stored, so a teacher who
            // only wanted three choices does not ship a fourth empty one. The
            // answer is re-found by value, because removing a blank above it
            // would otherwise shift the index onto the wrong option.
            $options = null;
            $answerIndex = null;

            if (($q['kind'] ?? '') === 'objective') {
                $correct = $q['options'][$q['answer_index']] ?? null;
                $options = array_values(array_filter(
                    array_map('trim', (array) ($q['options'] ?? [])),
                    fn ($o) => $o !== ''
                ));
                $answerIndex = max(0, array_search(trim((string) $correct), $options, true));
            }

            $assignment->questions()->create([
                'position' => $i + 1,
                'kind' => $q['kind'],
                'prompt' => trim($q['prompt']),
                'options' => $options,
                'answer_index' => $answerIndex,
                'expected' => trim((string) ($q['expected'] ?? '')) ?: null,
                'max_mark' => max(1, (int) ($q['max_mark'] ?? 1)),
            ]);
        }

        $assignment->update(['question_count' => count($this->draft['questions'])]);

        $this->drafting = false;
        $this->draft = [];
        $this->editing = null;

        session()->flash('status', 'Sent to ' . $classroom->name
            . '. Students see it the moment they open the app.');
    }

    public function render()
    {
        $assignments = Assignment::with('classroom')
            ->whereHas('classroom', fn ($q) => $q->where('teacher_id', auth()->id()))
            ->withCount([
                'submissions',
                'submissions as approved_count' => fn ($q) => $q->where('status', 'approved'),
            ])
            ->latest()
            ->get();

        return view('livewire.teacher.assignments', [
            'assignments' => $assignments,
            'classrooms' => Classroom::where('teacher_id', auth()->id())->get(),
            'topics' => $this->drafting ? $this->topics() : collect(),
        ])->title('Assignments');
    }
}

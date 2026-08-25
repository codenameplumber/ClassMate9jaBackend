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

    public function mount(): void
    {
        $this->classroomId = Classroom::where('teacher_id', auth()->id())->value('id');
        $this->dueOn = now()->addDays(4)->toDateString();
    }

    public function openDrafter(): void
    {
        $this->drafting = true;
        $this->draft = [];
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
            $assignment->questions()->create([
                'position' => $i + 1,
                'kind' => $q['kind'],
                'prompt' => $q['prompt'],
                'options' => $q['options'] ?? null,
                'answer_index' => $q['answer_index'] ?? null,
                'expected' => $q['expected'] ?? null,
                'max_mark' => $q['max_mark'] ?? 1,
            ]);
        }

        $this->drafting = false;
        $this->draft = [];

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

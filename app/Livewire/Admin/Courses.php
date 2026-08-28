<?php

namespace App\Livewire\Admin;

use App\Jobs\GenerateCourse;
use App\Jobs\GenerateQuestions;
use App\Models\CourseGeneration;
use App\Models\Question;
use App\Models\Topic;
use App\Models\Subject;
use App\Models\SubjectLevel;
use App\Support\Levels;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Concerns\PaginatesInHouse;
use Livewire\WithPagination;

/**
 * Courses: adding them, editing them, removing them, and asking for one to be
 * written.
 *
 * A course here is a subject plus the levels it is taught at. Creating one is
 * therefore two decisions, not one — what the subject is, and which years take
 * it — and the second is what the generator works from. A subject with no
 * levels is inert: nobody can enrol in it and nothing will be written for it.
 */
#[Layout('layouts.app')]
class Courses extends Component
{
    use PaginatesInHouse;
    use WithPagination;

    #[Url]
    public string $search = '';

    /* --- the create / edit form --- */
    public bool $editing = false;
    public ?int $subjectId = null;

    public string $name = '';
    public string $shortName = '';
    public string $slug = '';
    public string $emoji = '📘';
    public string $tone = 'brand';
    public string $blurb = '';
    public bool $isCore = false;
    public array $levels = [];

    /* --- the generation request --- */
    public ?int $generatingFor = null;
    public string $genLevel = '';
    public string $brief = '';

    /* --- deletion --- */
    public ?int $deletingId = null;

    public const TONES = ['brand', 'sky', 'amber', 'violet', 'clay'];

    /* ------------------------------------------------------------ form */

    public function create(): void
    {
        $this->reset(['subjectId', 'name', 'shortName', 'slug', 'blurb', 'levels']);
        $this->emoji = '📘';
        $this->tone = 'brand';
        $this->isCore = false;
        $this->editing = true;
    }

    public function edit(int $id): void
    {
        $subject = Subject::with('levels')->findOrFail($id);

        $this->subjectId = $subject->id;
        $this->name = $subject->name;
        $this->shortName = $subject->short_name;
        $this->slug = $subject->slug;
        $this->emoji = $subject->emoji ?: '📘';
        $this->tone = $subject->tone;
        $this->blurb = $subject->blurb ?? '';
        $this->isCore = (bool) $subject->is_core;
        $this->levels = $subject->levels->pluck('level')->all();
        $this->editing = true;
    }

    public function cancel(): void
    {
        $this->reset(['editing', 'subjectId', 'name', 'shortName', 'slug', 'blurb', 'levels']);
        $this->resetErrorBag();
    }

    public function updatedName(string $value): void
    {
        // Only auto-slug a new course. Changing the slug of a live one would
        // orphan every device that has already downloaded it.
        if (! $this->subjectId) {
            $this->slug = Str::slug($value);
        }

        if (! $this->shortName) {
            $this->shortName = Str::limit($value, 12, '');
        }
    }

    public function toggleLevel(string $level): void
    {
        if (! Levels::isValid($level)) {
            return;
        }

        $this->levels = in_array($level, $this->levels, true)
            ? array_values(array_diff($this->levels, [$level]))
            : [...$this->levels, $level];

        $this->resetErrorBag('levels');
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'shortName' => ['required', 'string', 'min:2', 'max:24'],
            'slug' => [
                'required', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/',
                'unique:subjects,slug' . ($this->subjectId ? ',' . $this->subjectId : ''),
            ],
            'emoji' => ['required', 'string', 'max:16'],
            'tone' => ['required', 'in:' . implode(',', self::TONES)],
            'blurb' => ['nullable', 'string', 'max:400'],
        ], [
            'slug.regex' => 'The slug can only hold lowercase letters, numbers and hyphens.',
        ]);

        if (! $this->levels) {
            $this->addError('levels', 'Choose at least one level. A subject with no levels cannot be taught.');

            return;
        }

        $subject = Subject::updateOrCreate(
            ['id' => $this->subjectId],
            [
                'slug' => $data['slug'],
                'name' => $data['name'],
                'short_name' => $data['shortName'],
                'emoji' => $data['emoji'],
                'tone' => $data['tone'],
                'blurb' => $data['blurb'] ?: null,
                'is_core' => $this->isCore,
                'status' => 'published',
            ]
        );

        $this->syncLevels($subject);

        session()->flash('status', $subject->name . ' saved.');
        $this->cancel();
    }

    /**
     * Add the newly ticked levels and drop the unticked ones.
     *
     * An offering that already holds content is never silently deleted —
     * removing a level a student has downloaded is a decision, not a checkbox.
     */
    private function syncLevels(Subject $subject): void
    {
        $existing = $subject->levels()->get()->keyBy('level');
        $kept = 0;

        foreach ($this->levels as $level) {
            if (! $existing->has($level)) {
                SubjectLevel::create([
                    'subject_id' => $subject->id,
                    'level' => $level,
                    'status' => 'draft',
                ]);
            }
            $kept++;
        }

        foreach ($existing as $level => $offering) {
            if (in_array($level, $this->levels, true)) {
                continue;
            }

            if ($offering->topic_count > 0) {
                session()->flash('warning',
                    "{$subject->short_name} {$level} still holds {$offering->topic_count} topics, so it was kept. "
                    . 'Remove its content first if you really mean to drop that level.');

                continue;
            }

            $offering->delete();
        }
    }

    /* --------------------------------------------------------- deleting */

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
    }

    public function cancelDelete(): void
    {
        $this->deletingId = null;
    }

    /**
     * Remove a course.
     *
     * Refused while anyone is enrolled or a class is running on it. The
     * cascade would take the topics, the lessons and every question with it,
     * and a student mid-term would simply find the subject gone.
     */
    public function delete(): void
    {
        $subject = Subject::withCount(['students', 'topics'])->findOrFail($this->deletingId);

        if ($subject->students_count > 0) {
            session()->flash('warning',
                "{$subject->name} cannot be removed: {$subject->students_count} students are taking it.");
            $this->cancelDelete();

            return;
        }

        if ($subject->classrooms()->exists()) {
            session()->flash('warning',
                "{$subject->name} cannot be removed: classes are running on it.");
            $this->cancelDelete();

            return;
        }

        $name = $subject->name;
        $subject->delete();

        session()->flash('status', $name . ' and all of its content were removed.');
        $this->cancelDelete();
    }

    /* ------------------------------------------------------- generating */

    public function startGeneration(int $subjectId, string $level): void
    {
        $this->generatingFor = $subjectId;
        $this->genLevel = $level;
        $this->brief = '';
    }

    public function cancelGeneration(): void
    {
        $this->reset(['generatingFor', 'genLevel', 'brief']);
        $this->resetErrorBag();
    }

    /**
     * Queue a course to be written.
     *
     * The brief is the admin explaining what they want, and it is required and
     * long for a reason: a generator given "write Biology SS2" produces a
     * generic syllabus, and a generic syllabus is what nobody uses twice.
     */
    public function requestGeneration(): void
    {
        $this->validate([
            'brief' => ['required', 'string', 'min:40', 'max:4000'],
            'genLevel' => ['required', Levels::rule()],
        ], [
            'brief.min' => 'Say more about what this course should cover — a thin brief writes a thin course.',
        ], ['genLevel' => 'level']);

        $subject = Subject::findOrFail($this->generatingFor);

        $offering = SubjectLevel::firstOrCreate(
            ['subject_id' => $subject->id, 'level' => $this->genLevel],
            ['status' => 'draft']
        );

        $run = CourseGeneration::create([
            'subject_id' => $subject->id,
            'level' => $this->genLevel,
            'requested_by' => auth()->id(),
            'brief' => $this->brief,
            'status' => 'queued',
        ]);

        $offering->update(['status' => 'generating']);

        GenerateCourse::dispatch($run->id);

        session()->flash('status',
            "Queued. {$subject->short_name} {$this->genLevel} is being written — this takes a few minutes.");

        $this->cancelGeneration();
    }

    /**
     * Stock one offering's practice bank up to the full target.
     *
     * The same work `php artisan questions:generate` does, scoped to one
     * subject and level. One job per topic, because a job that tried to write
     * five hundred questions in one go would be killed by the worker's
     * runtime limit long before it finished.
     */
    public function stockQuestions(int $subjectId, string $level): void
    {
        if (! Levels::isValid($level)) {
            return;
        }

        $subject = Subject::findOrFail($subjectId);

        $topics = Topic::where('subject_id', $subjectId)
            ->where('level', $level)
            ->get(['id', 'title']);

        if ($topics->isEmpty()) {
            session()->flash('warning',
                "{$subject->short_name} {$level} has no topics yet — have the course written first.");

            return;
        }

        $target = (int) config('ai.questions_per_offering', 500);
        $perTopic = (int) ceil($target / $topics->count());

        $counts = Question::where('subject_id', $subjectId)
            ->where('level', $level)
            ->selectRaw('topic, count(*) as total')
            ->groupBy('topic')
            ->pluck('total', 'topic');

        $short = $topics->filter(fn ($t) => (int) ($counts[$t->title] ?? 0) < $perTopic);

        if ($short->isEmpty()) {
            session()->flash('status', "{$subject->short_name} {$level} is already stocked.");

            return;
        }

        foreach ($short as $topic) {
            GenerateQuestions::dispatch($subjectId, $level, $topic->id, $perTopic);
        }

        session()->flash('status', sprintf(
            '%s %s: %d topics queued, about %s questions. This takes a while.',
            $subject->short_name, $level, $short->count(), number_format($short->count() * $perTopic)
        ));
    }

    public function render()
    {
        $subjects = Subject::query()
            ->with(['levels' => fn ($q) => $q->orderByRaw(
                'FIELD(level, ' . collect(Levels::ALL)->map(fn ($l) => "'{$l}'")->implode(',') . ')'
            )])
            ->withCount(['topics', 'questions', 'students'])
            ->withCount(['questions as ai_questions_count' => fn ($q) => $q->where('source', 'ai')])
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('slug', 'like', "%{$this->search}%")))
            ->orderByDesc('is_core')
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.admin.courses', [
            'subjects' => $subjects,
            'allLevels' => Levels::ALL,
            'tones' => self::TONES,
            'runs' => CourseGeneration::with('subject', 'requester')->latest()->limit(8)->get(),
        ])->title('Courses');
    }
}

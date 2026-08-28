<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateQuestions;
use App\Jobs\MarkSubmission;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\StudentState;
use App\Models\Subject;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The entire sync surface for the offline student app.
 *
 * Everything a device writes carries a client_uuid, so a retried request
 * upserts instead of duplicating. The device can retry blindly on a bad
 * connection and the server needs no deduplication logic of its own.
 */
class SyncController extends Controller
{
    /** Everything the device needs to run for a week without asking again. */
    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth()->user();

        // Content is scoped to the student's own year. Without this a JSS1
        // student would download the SS3 scheme alongside their own, which is
        // both wrong on the screen and paid for by the megabyte on a phone.
        $level = $user->level;

        $subjects = $user->subjects()
            ->with([
                'topics' => fn ($q) => $q->forLevel($level),
                'topics.lessons',
                'topics.classwork',
            ])
            ->get();

        return response()->json([
            'syncedAt' => now()->toIso8601String(),
            'contentVersion' => Subject::max('updated_at'),
            'level' => $level,
            'subjects' => $subjects->map(fn ($subject) => [
                'slug' => $subject->slug,
                'name' => $subject->name,
                'short' => $subject->short_name,
                'emoji' => $subject->emoji,
                'tone' => $subject->tone,
                'packMb' => $subject->pack_mb,
                'blurb' => $subject->blurb,
                'topics' => $subject->topics->map(fn ($topic) => [
                    'ref' => $topic->ref,
                    'week' => $topic->week,
                    'weekTitle' => $topic->week_title,
                    'title' => $topic->title,
                    'mins' => $topic->minutes,
                    'lessons' => $topic->lessons->mapWithKeys(fn ($lesson) => [
                        $lesson->locale => [
                            'title' => $lesson->title,
                            'objectives' => $lesson->objectives,
                            'body' => $lesson->body,
                        ],
                    ]),
                    'classwork' => $topic->classwork->map(fn ($q) => [
                        'q' => $q->prompt,
                        'options' => $q->options,
                        'answer' => $q->answer_index,
                        'why' => $q->explanation,
                    ]),
                ]),
            ]),
            'questions' => $this->pastQuestionsFor($subjects, $level),
            'practice' => $this->stockPractice($subjects, $level),
            'assignments' => $this->assignmentsFor($user),            'state' => $this->stateFor($user),
        ]);
    }

    /**
     * The progress blob, with the parts the server actually knows overlaid.
     *
     * Exams sat and work marked are records on this server, not opinions the
     * device holds -- so they are derived here rather than trusted from the
     * payload. Everything else (streaks, mastery, badges) is the device's own
     * and is passed through untouched.
     *
     * This exists because the two lists below used to come from a hard-coded
     * array in the front-end, so every student -- including one who had just
     * registered -- opened Progress and saw the same five invented entries
     * about electric fields.
     */
    private function stateFor($user): array
    {
        $payload = $user->state?->payload ?? [];

        return array_merge($payload, [
            'exams' => $this->examsFor($user),
            'activity' => $this->activityFor($user),
        ]);
    }

    /** Practice sets and mocks, most recent first. */
    private function examsFor($user): array
    {
        return $user->attempts()
            ->with('subject')
            ->limit(10)
            ->get()
            ->map(fn ($attempt) => [
                'id' => 'a' . $attempt->id,
                'subject' => $attempt->subject?->slug,
                'title' => $attempt->title,
                'score' => (int) $attempt->score,
                'total' => (int) $attempt->total,
                'mins' => (int) $attempt->minutes,
                'date' => $attempt->submitted_at?->toDateString(),
            ])
            ->all();
    }

    /**
     * What this student has actually done, newest first.
     *
     * Three sources merged: papers sat, lessons finished, and classwork a
     * teacher has marked and released. A student who has done nothing sees an
     * empty list, which is the truthful answer and the one that makes the
     * screen mean something once it fills up.
     */
    private function activityFor($user): array
    {
        $attempts = $user->attempts()
            ->with('subject')
            ->limit(15)
            ->get()
            ->map(fn ($a) => [
                'at' => $a->submitted_at?->toIso8601String(),
                'kind' => $a->kind === 'practice' ? 'practice' : 'exam',
                'text' => $a->total > 0
                    ? "{$a->title} — scored {$a->percent()}%"
                    : $a->title,
                'points' => $a->score * 3,
            ]);

        $lessons = $user->lessonCompletions()
            ->with('topic.subject')
            ->latest('completed_at')
            ->limit(15)
            ->get()
            ->map(fn ($c) => [
                'at' => $c->completed_at?->toIso8601String(),
                'kind' => 'lesson',
                'text' => 'Completed "' . ($c->topic?->title ?? 'a lesson') . '"'
                    . ($c->topic?->subject ? ' · ' . $c->topic->subject->short_name : ''),
                'points' => 40,
            ]);

        $marked = Submission::with('assignment')
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->latest('approved_at')
            ->limit(10)
            ->get()
            ->map(fn ($s) => [
                'at' => $s->approved_at?->toIso8601String(),
                'kind' => 'marked',
                'text' => ($s->assignment?->title ?? 'Classwork')
                    . ' — marked ' . $s->score() . '/' . $s->max_score,
                'points' => $s->score() * 2,
            ]);

        return $attempts->concat($lessons)->concat($marked)
            ->filter(fn ($row) => $row['at'] !== null)
            ->sortByDesc('at')
            ->take(20)
            ->values()
            ->all();
    }

    /**
     * The past-question bank for the subjects this student takes.
     *
     * Practice is graded on the device so a student sees why an answer was
     * wrong with no network, which means the answer key genuinely has to
     * ship. That is the opposite of an assignment, where the key never leaves
     * the server: there a mark is the teacher's to give, here the student is
     * only ever marking their own practice.
     *
     * The shape matches the client bank in js/data/questions.js exactly, so
     * the device can swap the bundled sample for this with no translation
     * layer standing between them.
     */
    private function pastQuestionsFor($subjects, ?string $level = null): array
    {
        $slugs = $subjects->pluck('slug', 'id');

        if ($slugs->isEmpty()) {
            return [];
        }

        return Question::whereIn('subject_id', $slugs->keys())
            ->forLevel($level)
            ->orderBy('subject_id')
            ->orderByDesc('year')
            ->get()
            ->map(fn ($q) => [
                'id' => $q->ref,
                'subject' => $slugs[$q->subject_id],
                'body' => $q->exam_body,
                'year' => (int) $q->year,
                'topic' => $q->topic,
                'q' => $q->prompt,
                'options' => $q->options,
                'answer' => (int) $q->answer_index,
                'why' => $q->explanation,
            ])
            ->all();
    }

    /**
     * Top up the practice bank for anything the student takes that is thin.
     *
     * Generate-once, serve-forever. The alternative -- calling a model while
     * the student waits for their twenty questions -- costs money on every
     * practice set, takes half a minute, and breaks the promise printed on
     * that screen: that every question is already on the phone and needs no
     * data. So the first sync that finds a thin subject queues the work, and
     * the questions arrive on the next one.
     *
     * Guarded by a cache key per subject and level, because bootstrap is
     * called on every app open and thirty students opening at 8am must not
     * queue the same batch thirty times.
     *
     * @return array<string, string> subject slug => state, for the app to show
     */
    private function stockPractice($subjects, ?string $level): array
    {
        if (! $level || $subjects->isEmpty()) {
            return [];
        }

        $target = (int) config('ai.questions_per_topic', 10);
        $state = [];

        $counts = Question::whereIn('subject_id', $subjects->pluck('id'))
            ->where('level', $level)
            ->selectRaw('subject_id, count(*) as total')
            ->groupBy('subject_id')
            ->pluck('total', 'subject_id');

        foreach ($subjects as $subject) {
            $have = (int) ($counts[$subject->id] ?? 0);

            if ($have >= $target) {
                $state[$subject->slug] = 'ready';

                continue;
            }

            $state[$subject->slug] = $have > 0 ? 'topping-up' : 'building';

            $lock = $this->stockLock($subject->id, $level);

            // add() is atomic, so only one request in the window wins.
            if (Cache::add($lock, true, now()->addHours(6))) {
                GenerateQuestions::dispatch($subject->id, $level);
            }
        }

        return $state;
    }

    /** One key per subject and level, so two callers cannot queue the same work. */
    private function stockLock(int $subjectId, string $level, ?int $topicId = null): string
    {
        return $topicId
            ? "practice-stock:{$subjectId}:{$level}:{$topicId}"
            : "practice-stock:{$subjectId}:{$level}";
    }

    /**
     * The practice screen asking for a topic to be stocked, directly.
     *
     * Same work as the sync-time top-up, but scoped to what the student is
     * looking at right now, so a student who opens a thin topic gets that
     * topic filled rather than waiting for their subject's turn.
     */
    public function requestPractice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'exists:subjects,slug'],
            'topicRef' => ['nullable', 'string', 'max:32'],
        ]);

        $user = auth()->user();
        $level = $user->level;

        $subject = Subject::where('slug', $data['subject'])->firstOrFail();

        if (! $level) {
            return response()->json(['queued' => false, 'reason' => 'no-level']);
        }

        $topic = ! empty($data['topicRef'])
            ? \App\Models\Topic::where('subject_id', $subject->id)
                ->where('level', $level)
                ->where('ref', $data['topicRef'])
                ->first()
            : null;

        // The same key shape the sync-time top-up uses, so a subject already
        // queued by a bootstrap is not queued a second time by the screen.
        $lock = $this->stockLock($subject->id, $level, $topic?->id);

        if (! Cache::add($lock, true, now()->addHour())) {
            return response()->json(['queued' => false, 'reason' => 'already-running']);
        }

        GenerateQuestions::dispatch($subject->id, $level, $topic?->id);

        return response()->json([
            'queued' => true,
            'message' => 'Building your practice set. Check back in a few minutes.',
        ]);
    }

    /** The whole progress blob, last-write-wins. */
    public function pushState(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payload' => ['required', 'array'],
            'deviceUpdatedAt' => ['nullable', 'date'],
        ]);

        $user = auth()->user();

        $state = StudentState::updateOrCreate(
            ['user_id' => $user->id],
            [
                'payload' => $data['payload'],
                'device_updated_at' => $data['deviceUpdatedAt'] ?? now(),
                'version' => DB::raw('version + 1'),
            ]
        );

        return response()->json([
            'ok' => true,
            'version' => $state->fresh()->version,
            'syncedAt' => now()->toIso8601String(),
        ]);
    }

    /** A finished practice set or mock exam. */
    public function recordAttempt(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clientUuid' => ['required', 'uuid'],
            'subject' => ['nullable', 'string'],
            'kind' => ['required', 'string', 'max:24'],
            'title' => ['required', 'string', 'max:190'],
            'score' => ['required', 'integer', 'min:0'],
            'total' => ['required', 'integer', 'min:1'],
            'minutes' => ['nullable', 'integer', 'min:0'],
            'autoSubmitted' => ['nullable', 'boolean'],
            'answers' => ['nullable', 'array'],
        ]);

        $attempt = Attempt::updateOrCreate(
            ['client_uuid' => $data['clientUuid']],
            [
                'user_id' => auth()->id(),
                'subject_id' => Subject::where('slug', $data['subject'] ?? '')->value('id'),
                'kind' => $data['kind'],
                'title' => $data['title'],
                'score' => $data['score'],
                'total' => $data['total'],
                'minutes' => $data['minutes'] ?? 0,
                'auto_submitted' => $data['autoSubmitted'] ?? false,
                'submitted_at' => now(),
            ]
        );

        if ($attempt->wasRecentlyCreated && ! empty($data['answers'])) {
            foreach ($data['answers'] as $answer) {
                $attempt->answers()->create([
                    'topic' => $answer['topic'] ?? null,
                    'chosen' => $answer['chosen'] ?? null,
                    'is_correct' => (bool) ($answer['correct'] ?? false),
                ]);
            }
        }

        return response()->json(['ok' => true, 'id' => $attempt->id], 201);
    }

    /** A completed assignment, which goes straight into the marking pipeline. */
    public function submit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clientUuid' => ['required', 'uuid'],
            'assignmentId' => ['required', 'integer', 'exists:assignments,id'],
            'answers' => ['required', 'array'],
            'answers.*.questionId' => ['required', 'integer'],
            'answers.*.chosen' => ['nullable', 'integer'],
            'answers.*.text' => ['nullable', 'string'],
        ]);

        $assignment = Assignment::with('questions', 'classroom')->findOrFail($data['assignmentId']);

        // Belonging to the class is the authorisation. Without this check any
        // signed-in student could post work into any classroom by guessing an
        // assignment id, and it would land in that teacher's marking queue.
        $enrolled = $assignment->classroom
            ?->students()
            ->whereKey(auth()->id())
            ->exists();

        if (! $enrolled) {
            return response()->json([
                'message' => 'That assignment was not set for your class.',
            ], 403);
        }

        $submission = Submission::updateOrCreate(
            ['client_uuid' => $data['clientUuid']],
            [
                'assignment_id' => $assignment->id,
                'user_id' => auth()->id(),
                'max_score' => $assignment->maxScore(),
                'status' => 'pending',
                'submitted_at' => now(),
            ]
        );

        // A resend of the same paper must not create a second set of answers.
        $submission->answers()->delete();

        foreach ($data['answers'] as $answer) {
            $question = $assignment->questions->firstWhere('id', $answer['questionId']);
            if (! $question) {
                continue;
            }

            $submission->answers()->create([
                'assignment_question_id' => $question->id,
                'chosen' => $answer['chosen'] ?? null,
                'answer_text' => $answer['text'] ?? ($question->options[$answer['chosen'] ?? -1] ?? null),
            ]);
        }

        MarkSubmission::dispatch($submission->id);

        return response()->json(['ok' => true, 'submissionId' => $submission->id], 201);
    }

    /** The classwork list the student app shows. */
    public function assignments(Request $request): JsonResponse
    {
        return response()->json([
            'assignments' => $this->assignmentsFor(auth()->user()),
        ]);
    }

    /** One paper, with its questions, ready to be answered. */
    public function assignment(Assignment $assignment): JsonResponse
    {
        $user = auth()->user();

        $enrolled = $assignment->classroom
            ?->students()
            ->whereKey($user->id)
            ->exists();

        if (! $enrolled) {
            return response()->json(['message' => 'That assignment was not set for your class.'], 403);
        }

        $submission = Submission::with('answers')
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $user->id)
            ->first();

        return response()->json([
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'kind' => $assignment->kind,
                'dueOn' => $assignment->due_on?->toDateString(),
                'classroom' => $assignment->classroom->name,
                'maxScore' => $assignment->maxScore(),
                'questions' => $assignment->questions->map(fn ($q) => [
                    'id' => $q->id,
                    'kind' => $q->kind,
                    'prompt' => $q->prompt,
                    'options' => $q->options,
                    'maxMark' => $q->max_mark,
                ]),
            ],
            'submission' => $submission ? $this->submissionPayload($submission) : null,
        ]);
    }

    /** Marked scripts coming back to the student. */
    public function mySubmissions(): JsonResponse
    {
        $submissions = Submission::with(['assignment', 'answers.question'])
            ->where('user_id', auth()->id())
            ->latest('submitted_at')
            ->limit(20)
            ->get();

        return response()->json([
            'submissions' => $submissions->map(fn ($s) => $this->submissionPayload($s)),
        ]);
    }

    /**
     * A student only ever sees marks their teacher has approved.
     *
     * An AI mark that has not been reviewed is a draft, and showing a draft as
     * a grade would be the fastest way to lose a teacher's trust.
     */
    private function submissionPayload(Submission $submission): array
    {
        $approved = $submission->isApproved();

        return [
            'id' => $submission->id,
            'assignmentId' => $submission->assignment_id,
            'title' => $submission->assignment?->title,
            'status' => $submission->status,
            'submittedAt' => $submission->submitted_at?->toIso8601String(),
            'released' => $approved,
            'score' => $approved ? $submission->score() : null,
            'maxScore' => $submission->max_score,
            'answers' => $approved
                ? $submission->answers->map(fn ($a) => [
                    'questionId' => $a->assignment_question_id,
                    'prompt' => $a->question?->prompt,
                    'given' => $a->answer_text,
                    'mark' => $a->mark(),
                    'maxMark' => $a->question?->max_mark,
                    'comment' => $a->teacher_comment ?: $a->ai_comment,
                ])
                : [],
        ];
    }

    private function assignmentsFor($user): array
    {
        return Assignment::with('questions', 'classroom')
            ->whereHas('classroom.students', fn ($q) => $q->where('users.id', $user->id))
            ->whereNotNull('published_at')
            // A paper with no questions is not work, it is an empty screen.
            // Never send one to a student, whatever a teacher managed to save.
            ->has('questions')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($assignment) => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'kind' => $assignment->kind,
                'dueOn' => $assignment->due_on?->toDateString(),
                'classroom' => $assignment->classroom->name,
                'submitted' => $assignment->submissions()->where('user_id', $user->id)->exists(),
                'questions' => $assignment->questions->map(fn ($q) => [
                    'id' => $q->id,
                    'kind' => $q->kind,
                    'prompt' => $q->prompt,
                    'options' => $q->options,
                    'maxMark' => $q->max_mark,
                ]),
            ])
            ->all();
    }
}

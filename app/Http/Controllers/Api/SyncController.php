<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\MarkSubmission;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\StudentState;
use App\Models\Subject;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $subjects = $user->subjects()->with(['topics.lessons', 'topics.classwork'])->get();

        return response()->json([
            'syncedAt' => now()->toIso8601String(),
            'contentVersion' => Subject::max('updated_at'),
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
            'assignments' => $this->assignmentsFor($user),
            'state' => $user->state?->payload,
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

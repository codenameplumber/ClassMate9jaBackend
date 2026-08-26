<?php

namespace App\Jobs;

use App\Models\Question;
use App\Models\Subject;
use App\Models\SubjectLevel;
use App\Models\Topic;
use App\Services\Ai\CourseWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * Fills the practice bank for one subject at one level.
 *
 * Generate-once, serve-forever. The first student to open practice on a topic
 * with nothing in it triggers this; everyone after is served from the database
 * and, once synced, from their own phone. That is deliberately not "generate
 * while the student waits": a live call per practice set would cost money on
 * every attempt, take half a minute, and break the promise printed on that
 * screen — that every question is already on the device and needs no data.
 *
 * Everything written here is `source: ai` with `reviewed_at` null. An
 * AI-written answer key nobody has checked marks a correct student wrong and
 * nothing looks broken, so the provenance is what lets a bad batch be found
 * and pulled.
 */
class GenerateQuestions implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 240;

    /** The default when no caller says otherwise. */
    public const PER_TOPIC = 10;

    /**
     * How many questions to ask for in one call.
     *
     * A single request for forty comes back truncated or repetitive, and a
     * batch that fails loses everything in it. Twelve is small enough to come
     * back whole and large enough not to waste the prompt.
     */
    public const BATCH = 12;

    /**
     * The most calls one topic gets in one run of this job.
     *
     * A ceiling on runtime, not on the bank: the worker has a timeout, and at
     * roughly twenty seconds a call this keeps the job comfortably inside it.
     * A topic wanting more than this is simply topped up the next time the
     * command runs, which is why the whole thing is idempotent.
     */
    public const MAX_ROUNDS = 5;

    /**
     * @param  int|null  $perTopic  how full this topic should end up. The
     *                              on-demand path uses the config default; the
     *                              bulk command divides its own target across
     *                              the offering's topics and passes that.
     */
    public function __construct(
        public int $subjectId,
        public string $level,
        public ?int $topicId = null,
        public ?int $perTopic = null,
    ) {
        $this->onQueue('content');
    }

    private function target(): int
    {
        return max(1, $this->perTopic ?: (int) config('ai.questions_per_topic', self::PER_TOPIC));
    }

    public function handle(CourseWriter $writer): void
    {
        if (! $writer->isConfigured()) {
            return;
        }

        $subject = Subject::find($this->subjectId);

        if (! $subject) {
            return;
        }

        $topics = $this->topics();
        $target = $this->target();

        foreach ($topics as $topic) {
            // Counted from the database rather than tallied, because this job
            // retries and another may have filled the topic in between.
            $have = $this->countFor($subject, $topic);

            for ($round = 0; $have < $target && $round < self::MAX_ROUNDS; $round++) {
                $written = $writer->questions(
                    $subject->name,
                    $this->level,
                    $topic->title,
                    min(self::BATCH, $target - $have),
                );

                // The writer declined, or every question it returned had a
                // broken answer key and was dropped. Asking again immediately
                // would just spend the same money on the same refusal.
                if (! $written) {
                    break;
                }

                foreach ($written as $row) {
                    $this->store($subject, $topic, $row);
                }

                $after = $this->countFor($subject, $topic);

                // No progress means the model is repeating itself and every
                // question collided with one already stored. The topic is as
                // full as this model is going to make it.
                if ($after <= $have) {
                    break;
                }

                $have = $after;
            }
        }

        SubjectLevel::firstOrCreate(
            ['subject_id' => $subject->id, 'level' => $this->level],
            ['status' => 'draft']
        )->refreshCounts();
    }

    private function countFor(Subject $subject, Topic $topic): int
    {
        return Question::where('subject_id', $subject->id)
            ->where('level', $this->level)
            ->where('topic', $topic->title)
            ->count();
    }

    /** Either the one topic asked for, or every topic short of questions. */
    private function topics()
    {
        $query = Topic::where('subject_id', $this->subjectId)
            ->where('level', $this->level);

        if ($this->topicId) {
            return $query->where('id', $this->topicId)->get();
        }

        // A whole level at once is a lot of calls, so it is capped per run and
        // simply dispatched again when more are needed.
        return $query->orderBy('week')->orderBy('position')->limit(6)->get();
    }

    private function store(Subject $subject, Topic $topic, array $row): void
    {
        // The ref has to be unique and stable. Hashing the prompt means the
        // same question generated twice collides instead of being stored twice.
        $ref = 'ai-' . substr(sha1($subject->slug . $this->level . $row['prompt']), 0, 16);

        Question::updateOrCreate(['ref' => $ref], [
            'subject_id' => $subject->id,
            'level' => $this->level,
            // Not claimed as a real past paper. The writer is told never to
            // present an invented question as one, and this keeps the record
            // honest: it is practice, categorised by topic.
            'exam_body' => 'PRACTICE',
            'year' => (int) now()->format('Y'),
            'topic' => Str::limit($row['topic'] ?? $topic->title, 190, ''),
            'prompt' => $row['prompt'],
            'options' => $row['options'],
            'answer_index' => $row['answer_index'],
            'explanation' => $row['explanation'] ?: null,
            'source' => 'ai',
            'reviewed_at' => null,
        ]);
    }
}

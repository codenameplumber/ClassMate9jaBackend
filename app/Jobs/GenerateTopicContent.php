<?php

namespace App\Jobs;

use App\Models\ClassworkQuestion;
use App\Models\CourseGeneration;
use App\Models\Lesson;
use App\Models\SubjectLevel;
use App\Models\Topic;
use App\Services\Ai\CourseWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Writes one topic: both lessons and its classwork.
 *
 * Both lessons, not one and a translation. Every topic in this platform exists
 * in English and in Pidgin, and Topic::lessonFor() falls back to English when
 * a locale is missing — silently. A course generated with the Pidgin half
 * skipped would look complete and quietly serve English to every student who
 * asked for Pidgin, which is the kind of failure nobody reports because
 * nothing appears broken.
 *
 * If the Pidgin lesson fails, the English one is still kept. Half a topic a
 * teacher can edit beats no topic.
 */
class GenerateTopicContent implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 240;

    public function __construct(
        public int $topicId,
        public int $runId,
    ) {
        $this->onQueue('content');
    }

    public function handle(CourseWriter $writer): void
    {
        $topic = Topic::with('subject')->find($this->topicId);
        $run = CourseGeneration::find($this->runId);

        if (! $topic || ! $topic->subject || ! $run) {
            return;
        }

        $subject = $topic->subject->name;
        $written = 0;

        foreach (['en', 'pcm'] as $locale) {
            if (Lesson::where('topic_id', $topic->id)->where('locale', $locale)->exists()) {
                continue;
            }

            $lesson = $writer->lesson(
                $subject,
                $topic->level,
                $topic->week_title,
                $topic->title,
                $locale,
            );

            if (! $lesson) {
                continue;
            }

            Lesson::updateOrCreate(
                ['topic_id' => $topic->id, 'locale' => $locale],
                [
                    'title' => $lesson['title'],
                    'objectives' => $lesson['objectives'],
                    'body' => $lesson['body'],
                    'version' => 1,
                ]
            );

            $written++;
        }

        if (! ClassworkQuestion::where('topic_id', $topic->id)->exists()) {
            $classwork = $writer->classwork($subject, $topic->level, $topic->title, 2);

            foreach ($classwork ?? [] as $question) {
                ClassworkQuestion::create([
                    'topic_id' => $topic->id,
                    'prompt' => $question['prompt'],
                    'options' => $question['options'],
                    'answer_index' => $question['answer_index'],
                    'explanation' => $question['explanation'],
                ]);
            }
        }

        $run->increment('lessons_written', $written);

        $this->closeOffIfDone($run);
    }

    /**
     * The last topic to finish publishes the offering.
     *
     * Counted from the content on disk rather than from a tally, because jobs
     * retry and a counter that was incremented twice would publish a course
     * that is not finished.
     */
    private function closeOffIfDone(CourseGeneration $run): void
    {
        $topics = Topic::where('subject_id', $run->subject_id)
            ->where('level', $run->level)
            ->pluck('id');

        $withLessons = Lesson::whereIn('topic_id', $topics)
            ->select('topic_id')
            ->distinct()
            ->count();

        if ($withLessons < $topics->count()) {
            return;
        }

        SubjectLevel::firstOrCreate(
            ['subject_id' => $run->subject_id, 'level' => $run->level],
            ['status' => 'draft']
        )->refreshCounts();

        if ($run->isRunning()) {
            $run->markDone();
        }
    }

    public function failed(?\Throwable $e): void
    {
        $run = CourseGeneration::find($this->runId);

        // One topic failing is not the course failing. The run is left open so
        // the remaining topics can still close it off, and the offering shows
        // the shortfall in its topic count.
        if ($run) {
            SubjectLevel::where('subject_id', $run->subject_id)
                ->where('level', $run->level)
                ->get()
                ->each->refreshCounts();
        }
    }
}

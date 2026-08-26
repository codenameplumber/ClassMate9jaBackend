<?php

namespace App\Jobs;

use App\Models\CourseGeneration;
use App\Models\SubjectLevel;
use App\Models\Topic;
use App\Services\Ai\CourseWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Writes the skeleton of a course, then hands each topic to its own job.
 *
 * Split that way for a reason that is not architectural neatness: a managed
 * queue worker gets a fixed number of seconds per job, and one course is a
 * scheme call plus roughly seventy more for lessons and classwork. As one job
 * it would be killed halfway every time. As a scheme plus N children, each
 * piece finishes well inside the limit and a failure costs one topic rather
 * than the whole course.
 *
 * It also means progress is real. Topics appear as they are written, so an
 * admin watching the screen sees a course fill up rather than a spinner.
 */
class GenerateCourse implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $runId)
    {
        // Content generation is slow and nobody is waiting on it. It must never
        // sit in front of a script a student just submitted.
        $this->onQueue('content');
    }

    public function handle(CourseWriter $writer): void
    {
        $run = CourseGeneration::with('subject')->find($this->runId);

        if (! $run || ! $run->subject) {
            return;
        }

        $run->markRunning();

        if (! $writer->isConfigured()) {
            $this->giveUp($run, 'No AI provider is configured, so no course could be written. '
                . 'Set OPENAI_API_KEY and run this again.');

            return;
        }

        $scheme = $writer->scheme(
            $run->subject->name,
            $run->level,
            $run->brief,
        );

        if (! $scheme) {
            $this->giveUp($run, 'The scheme of work came back empty or unreadable. '
                . 'Try again, or give a longer brief.');

            return;
        }

        $topicIds = $this->writeTopics($run, $scheme);

        $run->update(['topics_written' => count($topicIds)]);

        if (! $topicIds) {
            $this->giveUp($run, 'The scheme held no usable topics.');

            return;
        }

        // Each topic writes its own two lessons and its classwork. The last one
        // to finish closes the run off.
        foreach ($topicIds as $topicId) {
            GenerateTopicContent::dispatch($topicId, $run->id);
        }
    }

    /**
     * Turn the scheme into topic rows.
     *
     * Refs follow the same w{week}t{position} convention the shipped
     * curriculum uses, so generated content is indistinguishable from seeded
     * content everywhere downstream — the PWA already keys on this.
     */
    private function writeTopics(CourseGeneration $run, array $scheme): array
    {
        $ids = [];

        DB::transaction(function () use ($run, $scheme, &$ids) {
            foreach ($scheme as $week) {
                foreach (array_values($week['topics']) as $index => $topic) {
                    $ref = 'w' . $week['week'] . 't' . ($index + 1);

                    $row = Topic::updateOrCreate(
                        [
                            'subject_id' => $run->subject_id,
                            'level' => $run->level,
                            'ref' => $ref,
                        ],
                        [
                            'week' => $week['week'],
                            'week_title' => $week['week_title'],
                            'title' => $topic['title'],
                            'minutes' => $topic['minutes'],
                            'position' => $index + 1,
                            'has_rich_lesson' => false,
                        ]
                    );

                    $ids[] = $row->id;
                }
            }
        });

        return $ids;
    }

    private function giveUp(CourseGeneration $run, string $why): void
    {
        $run->markFailed($why);

        SubjectLevel::where('subject_id', $run->subject_id)
            ->where('level', $run->level)
            ->get()
            ->each->refreshCounts();
    }

    /**
     * A killed worker must not leave the offering stuck on "writing".
     */
    public function failed(?\Throwable $e): void
    {
        $run = CourseGeneration::find($this->runId);

        if ($run && $run->isRunning()) {
            $this->giveUp($run, 'The job did not finish: ' . ($e?->getMessage() ?? 'unknown reason'));
        }
    }
}

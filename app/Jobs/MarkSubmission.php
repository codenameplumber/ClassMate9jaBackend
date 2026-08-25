<?php

namespace App\Jobs;

use App\Models\Submission;
use App\Services\Ai\Marker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Marks one submission.
 *
 * Objective answers are decided here in PHP — free, instant, and exactly
 * right. Only written answers reach the model, and only when one is
 * configured. A failure to reach the model leaves the answer unmarked rather
 * than guessing, which is why the teacher's queue is the safety net.
 */
class MarkSubmission implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $submissionId) {}

    public function handle(Marker $marker): void
    {
        $submission = Submission::with('answers.question')->find($this->submissionId);

        if (! $submission || $submission->isApproved()) {
            return;
        }

        $submission->update(['status' => 'marking']);

        $floor = (int) config('ai.confidence_floor', 80);
        $total = 0;
        $needsReview = false;

        foreach ($submission->answers as $answer) {
            $question = $answer->question;

            if (! $question->isWritten()) {
                $correct = $answer->chosen !== null && $answer->chosen === $question->answer_index;
                $mark = $correct ? $question->max_mark : 0;

                $answer->update([
                    'ai_mark' => $mark,
                    'ai_confidence' => 100,
                    'ai_comment' => $correct
                        ? 'Correct.'
                        : 'The correct answer is: ' . ($question->options[$question->answer_index] ?? $question->expected),
                ]);

                $total += $mark;

                continue;
            }

            $result = $marker->mark($question, (string) $answer->answer_text);

            if ($result === null) {
                $answer->update([
                    'ai_mark' => null,
                    'ai_confidence' => 0,
                    'ai_comment' => 'Awaiting your mark — this answer was not machine-marked.',
                ]);
                $needsReview = true;

                continue;
            }

            $answer->update([
                'ai_mark' => $result['mark'],
                'ai_confidence' => $result['confidence'],
                'ai_comment' => $result['comment'],
            ]);

            $total += $result['mark'];

            if ($result['confidence'] < $floor) {
                $needsReview = true;
            }
        }

        $submission->update([
            'ai_score' => $total,
            'max_score' => $submission->assignment->maxScore(),
            'needs_review' => $needsReview,
            'status' => 'marked',
        ]);
    }
}

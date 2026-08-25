<?php

namespace App\Services\Ai;

use App\Models\AssignmentQuestion;

/**
 * The no-key driver, and the reason the platform is fully usable without one.
 *
 * Objective marking never reaches a Marker at all — it is decided in PHP. This
 * driver only declines written answers, which then sit in the teacher's queue
 * marked "awaiting your mark". Nothing breaks; a human simply does the part a
 * model would have done.
 */
class NullMarker implements Marker
{
    public function mark(AssignmentQuestion $question, string $answer): ?array
    {
        return null;
    }

    public function draftQuestions(string $subject, string $topic, string $level, int $count): ?array
    {
        return null;
    }

    public function isConfigured(): bool
    {
        return false;
    }
}

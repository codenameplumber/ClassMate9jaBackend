<?php

namespace App\Services\Ai;

use App\Models\AssignmentQuestion;

/**
 * The seam.
 *
 * Everything above this interface is provider-agnostic; swapping OpenAI for a
 * different provider, or for an on-device model, means writing one more class
 * and changing one config line. No screen and no job touches a vendor SDK.
 */
interface Marker
{
    /**
     * Mark one written answer against the question's marking scheme.
     *
     * @return array{mark:int, confidence:int, comment:string}|null
     *         null means "I cannot mark this" — the answer then waits for a
     *         human rather than receiving a guessed mark.
     */
    public function mark(AssignmentQuestion $question, string $answer): ?array;

    /**
     * Draft exam-style questions for a topic.
     *
     * @return array<int, array>|null
     */
    public function draftQuestions(string $subject, string $topic, string $level, int $count): ?array;

    public function isConfigured(): bool;
}

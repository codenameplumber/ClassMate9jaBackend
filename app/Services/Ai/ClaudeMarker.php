<?php

namespace App\Services\Ai;

use Anthropic\Client;
use App\Models\AssignmentQuestion;
use App\Services\Ai\Schema\MarkResult;
use Illuminate\Support\Facades\Log;

/**
 * Written-answer marking, by Claude.
 *
 * Three things shape this class:
 *
 *  1. The marking scheme is identical for all thirty scripts on an assignment
 *     and the student's answer is the only part that varies, so the scheme goes
 *     in a cached system block and the answer in the user turn. Script two
 *     onward reads the cache instead of paying for the prefix again.
 *
 *  2. The reply is a structured output, validated at the API boundary. A
 *     marking pipeline that regexes a number out of prose is one odd reply away
 *     from writing a wrong grade into a student's record.
 *
 *  3. It returns null rather than guessing. A refusal, a timeout, a malformed
 *     reply — every one of them means "a human marks this", which is exactly
 *     what the teacher's review queue exists for.
 */
class ClaudeMarker implements Marker
{
    public function __construct(
        private readonly ?string $key,
        private readonly string $model,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->key);
    }

    private function client(): Client
    {
        return new Client(apiKey: $this->key);
    }

    public function mark(AssignmentQuestion $question, string $answer): ?array
    {
        if (! $this->isConfigured() || trim($answer) === '') {
            return null;
        }

        $scheme = <<<PROMPT
        You are marking a Nigerian secondary school answer against a marking scheme,
        the way a WAEC examiner would.

        Question: {$question->prompt}
        Marks available: {$question->max_mark}
        Marking scheme / model answer: {$question->expected}

        Rules:
        - Give credit for correct method even when the final answer is wrong.
        - Do not deduct for spelling, phrasing, or answers written in Pidgin.
        - Award whole marks only, from 0 to {$question->max_mark}.
        - Set confidence below 80 whenever the answer is ambiguous, partly correct
          in a way the scheme does not cover, or unreadable. A low confidence is a
          request for a teacher to look, not a failure.
        PROMPT;

        try {
            $message = $this->client()->messages->create(
                model: $this->model,
                maxTokens: 1024,
                // Stable across every script on this assignment, so it caches.
                system: [
                    ['type' => 'text', 'text' => $scheme, 'cacheControl' => ['type' => 'ephemeral']],
                ],
                messages: [
                    ['role' => 'user', 'content' => "Student answer:\n\n" . $answer],
                ],
                outputConfig: ['format' => MarkResult::class],
            );

            // A refusal is a normal outcome to handle, not an exception to catch.
            if ($message->stopReason === 'refusal') {
                Log::info('Marking declined', ['question' => $question->id]);

                return null;
            }

            $result = $message->parsedOutput();

            if (! $result instanceof MarkResult) {
                return null;
            }

            return [
                'mark' => max(0, min($question->max_mark, $result->mark)),
                'confidence' => max(0, min(100, $result->confidence)),
                'comment' => trim($result->comment),
            ];
        } catch (\Throwable $e) {
            // Losing a student's script to an API hiccup is unacceptable. The
            // answer stays unmarked and surfaces in the teacher's queue.
            Log::warning('Marking call failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    public function draftQuestions(string $subject, string $topic, string $level, int $count): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $brief = <<<PROMPT
        You write exam questions for Nigerian secondary schools, following the WAEC
        and JAMB syllabus.

        Subject: {$subject}
        Class: {$level}
        Topic: {$topic}

        Write {$count} questions. Make the last one a written answer asking the
        student to explain or show working; the rest objective with four options.
        Use Nigerian contexts in word problems where they genuinely explain
        something — market trading, danfo fares, NEPA, recharge cards — never as
        decoration.

        Reply with JSON and nothing else, in exactly this shape:
        {"questions": [
          {"kind": "objective", "prompt": "...", "options": ["a","b","c","d"],
           "answer_index": 0, "expected": "the correct option written out", "max_mark": 2},
          {"kind": "written", "prompt": "...", "options": null, "answer_index": null,
           "expected": "the marking scheme, saying what earns each mark", "max_mark": 5}
        ]}
        PROMPT;

        try {
            $message = $this->client()->messages->create(
                model: $this->model,
                maxTokens: 4096,
                messages: [['role' => 'user', 'content' => $brief]],
            );

            if ($message->stopReason === 'refusal') {
                return null;
            }

            $parsed = $this->json($this->textOf($message));
            $questions = $parsed['questions'] ?? null;

            return is_array($questions) && $questions ? $questions : null;
        } catch (\Throwable $e) {
            Log::warning('Drafting call failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /** Content is a list of blocks; only the text ones carry the answer. */
    private function textOf(object $message): string
    {
        $text = '';

        foreach ($message->content as $block) {
            if (($block->type ?? null) === 'text') {
                $text .= $block->text;
            }
        }

        return trim($text);
    }

    /** Tolerates a fenced block or a sentence wrapped around the JSON. */
    private function json(string $text): ?array
    {
        $direct = json_decode($text, true);
        if (is_array($direct)) {
            return $direct;
        }

        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $text, $match)) {
            $found = json_decode($match[0], true);
            if (is_array($found)) {
                return $found;
            }
        }

        return null;
    }
}

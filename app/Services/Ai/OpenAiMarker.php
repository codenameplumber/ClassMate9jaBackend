<?php

namespace App\Services\Ai;

use App\Models\AssignmentQuestion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Written-answer marking, by OpenAI.
 *
 * Two things shape this class:
 *
 *  1. The marking scheme is identical for all thirty scripts on an assignment
 *     and the student's answer is the only part that varies, so the scheme goes
 *     in the system message and the answer in the user turn. That ordering is
 *     what lets the provider reuse the prefix across a whole class.
 *
 *  2. It returns null rather than guessing. A refusal, a timeout, a malformed
 *     reply — every one of them means "a human marks this", which is exactly
 *     what the teacher's review queue exists for.
 */
class OpenAiMarker implements Marker
{
    public function __construct(
        private readonly ?string $key,
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $timeout = 60,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->key);
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

        Reply with JSON only:
        {"mark": <integer 0-{$question->max_mark}>,
         "confidence": <integer 0-100>,
         "comment": "<one or two sentences addressed to the student, naming the
                      specific mistake or what earned the marks>"}
        PROMPT;

        try {
            $response = Http::withToken($this->key)
                ->timeout($this->timeout)
                ->acceptJson()
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'temperature' => 0,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $scheme],
                        ['role' => 'user', 'content' => "Student answer:\n\n" . $answer],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('Marking call failed', [
                    'status' => $response->status(),
                    'body' => str($response->body())->limit(400),
                ]);

                return null;
            }

            $parsed = $this->json((string) data_get($response->json(), 'choices.0.message.content'));

            if (! is_array($parsed) || ! isset($parsed['mark'])) {
                return null;
            }

            return [
                'mark' => (int) max(0, min($question->max_mark, (int) $parsed['mark'])),
                'confidence' => (int) max(0, min(100, (int) ($parsed['confidence'] ?? 50))),
                'comment' => trim((string) ($parsed['comment'] ?? '')),
            ];
        } catch (\Throwable $e) {
            // Losing a student's script to an API hiccup is unacceptable. The
            // answer stays unmarked and surfaces in the teacher's queue.
            Log::warning('Marking call threw', ['message' => $e->getMessage()]);

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

        Reply with JSON only:
        {"questions": [
          {"kind": "objective", "prompt": "...", "options": ["a","b","c","d"],
           "answer_index": 0, "expected": "the correct option written out", "max_mark": 2},
          {"kind": "written", "prompt": "...", "options": null, "answer_index": null,
           "expected": "the marking scheme, saying what earns each mark", "max_mark": 5}
        ]}
        PROMPT;

        try {
            $response = Http::withToken($this->key)
                ->timeout($this->timeout)
                ->acceptJson()
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'temperature' => 0.4,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [['role' => 'user', 'content' => $brief]],
                ]);

            if ($response->failed()) {
                return null;
            }

            $parsed = $this->json((string) data_get($response->json(), 'choices.0.message.content'));
            $questions = $parsed['questions'] ?? null;

            return is_array($questions) && $questions ? $questions : null;
        } catch (\Throwable $e) {
            Log::warning('Drafting call threw', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /** Tolerates a fenced block or a sentence wrapped around the JSON. */
    private function json(string $text): ?array
    {
        $text = trim($text);

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

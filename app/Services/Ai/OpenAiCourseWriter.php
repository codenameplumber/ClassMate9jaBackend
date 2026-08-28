<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Curriculum written by OpenAI, to the Nigerian scheme of work.
 *
 * Three things shape this class:
 *
 *  1. Each call does one small job. A single prompt asking for twelve weeks of
 *     lessons in two languages returns something truncated and shallow; asking
 *     for one lesson at a time costs more calls and produces work a teacher
 *     will actually use.
 *
 *  2. Pidgin is generated, not translated. A lesson written in English and
 *     then converted reads like a translation, because it is one. The lesson
 *     is written again from the same brief by someone told to teach in Pidgin.
 *
 *  3. Every failure returns null. The caller records a partial course and an
 *     admin re-runs it — which is recoverable, unlike a subject stuck in
 *     `generating` because something threw halfway through.
 */
class OpenAiCourseWriter implements CourseWriter
{
    public function __construct(
        private readonly ?string $key,
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $timeout = 90,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->key);
    }

    /* ------------------------------------------------------------ scheme */

    public function scheme(string $subject, string $level, string $brief, int $weeks = 12): ?array
    {
        $prompt = <<<PROMPT
        You are a Nigerian curriculum planner writing a scheme of work for a
        secondary school, following the NERDC national curriculum and the WAEC
        and JAMB syllabus where the level is senior.

        Subject: {$subject}
        Level: {$level}
        Weeks: {$weeks}

        What the school asked for:
        {$brief}

        Write {$weeks} weeks. Each week has a title and two topics. Order them
        so each week genuinely depends on the one before — a scheme that could
        be shuffled is not a scheme.

        Pitch it at {$level} specifically. A JSS1 topic and an SS3 topic with
        the same name are different lessons, and getting that wrong is the
        single most useless thing you can do here.

        Reply with JSON only:
        {"weeks": [
          {"week": 1, "week_title": "...",
           "topics": [{"title": "...", "minutes": 30}, {"title": "...", "minutes": 25}]}
        ]}
        PROMPT;

        $parsed = $this->ask($prompt, 0.4, 8000, 'scheme');
        $weeksOut = $parsed['weeks'] ?? null;

        if (! is_array($weeksOut) || ! $weeksOut) {
            return null;
        }

        $clean = [];

        foreach (array_values($weeksOut) as $i => $week) {
            $topics = [];

            foreach (array_values($week['topics'] ?? []) as $topic) {
                $title = trim((string) ($topic['title'] ?? ''));

                if ($title === '') {
                    continue;
                }

                $topics[] = [
                    'title' => $title,
                    'minutes' => max(10, min(60, (int) ($topic['minutes'] ?? 25))),
                ];
            }

            if (! $topics) {
                continue;
            }

            $clean[] = [
                'week' => (int) ($week['week'] ?? $i + 1),
                'week_title' => trim((string) ($week['week_title'] ?? 'Week ' . ($i + 1))),
                'topics' => $topics,
            ];
        }

        return $clean ?: null;
    }

    /* ------------------------------------------------------------ lesson */

    public function lesson(
        string $subject,
        string $level,
        string $weekTitle,
        string $topic,
        string $locale,
    ): ?array {
        $voice = $locale === 'pcm'
            ? 'Write the whole lesson in Nigerian Pidgin — the natural Pidgin a Lagos '
                . 'teacher uses with a class, not a caricature and not English with a few '
                . 'words swapped. This is not a translation: teach the topic again, in Pidgin.'
            : 'Write in clear, simple English. Short sentences. No padding.';

        $prompt = <<<PROMPT
        You are writing one lesson for a Nigerian {$level} class.

        Subject: {$subject}
        Week: {$weekTitle}
        Topic: {$topic}

        {$voice}

        The lesson is a list of blocks, in order. Block types:
          {"t": "h", "text": "a heading"}
          {"t": "p", "text": "a paragraph"}
          {"t": "f", "text": "a formula or a worked line, shown apart"}
          {"t": "naija", "text": "a Nigerian example that genuinely explains the idea —
                                  market trading, danfo fares, NEPA, recharge cards, football.
                                  Never decoration."}
          {"t": "list", "items": ["...", "..."]}

        Rules:
        - Lead with the idea in plain words before any formula.
        - Write in plain text. No LaTeX, no markdown, no backslash commands and
          no dollar signs around maths. The app shows your reply as written, so
          \\frac{a}{b} and \\[ ... \\] arrive on a student's screen exactly like
          that and mean nothing to them.
        - Write maths the way it is written on a blackboard, using ordinary
          characters: x^2 or x², sqrt(16) or \u{221A}16, a/b, 3 \u{00D7} 4, \u{2248}, \u{2264}, \u{03C0}.
          For a fraction in running text write "a/b"; to set one out, put it on
          its own line.
        - Show working line by line for anything calculated.
        - Name the mistake students at this level actually make, and why it is wrong.
        - 8 to 14 blocks. A lesson nobody finishes teaches nothing.

        Reply with JSON only:
        {"title": "...",
         "objectives": ["by the end a student can ...", "..."],
         "body": [ ...blocks... ]}
        PROMPT;

        $parsed = $this->ask($prompt, 0.6, 4000, 'lesson');

        $body = $parsed['body'] ?? null;
        $title = trim((string) ($parsed['title'] ?? ''));

        if (! is_array($body) || ! $body || $title === '') {
            return null;
        }

        $objectives = array_values(array_filter(
            array_map(fn ($o) => trim((string) $o), $parsed['objectives'] ?? []),
            fn ($o) => $o !== ''
        ));

        return [
            'title' => $title,
            'objectives' => $objectives ?: ['Understand ' . $topic . '.'],
            'body' => array_values($body),
        ];
    }

    /* --------------------------------------------------------- questions */

    public function classwork(string $subject, string $level, string $topic, int $count = 2): ?array
    {
        $prompt = <<<PROMPT
        Write {$count} objective questions checking that a Nigerian {$level}
        student understood this lesson.

        Subject: {$subject}
        Topic: {$topic}

        Rules:
        - Four options each, exactly one correct.
        - Make the wrong options the mistakes students actually make, not filler.
          An option nobody would pick tests nothing.
        - answer_index is the 0-based position of the correct option.
        - The explanation says why the right answer is right, in one or two sentences.

        Reply with JSON only:
        {"questions": [
          {"prompt": "...", "options": ["a","b","c","d"], "answer_index": 0, "explanation": "..."}
        ]}
        PROMPT;

        return $this->objectives($this->ask($prompt, 0.5, 3000, 'classwork'), $count);
    }

    public function questions(string $subject, string $level, string $topic, int $count = 10): ?array
    {
        $exam = str_starts_with($level, 'SS')
            ? 'in the style of WAEC, JAMB and NECO past questions'
            : 'in the style of a junior secondary end-of-term examination';

        $prompt = <<<PROMPT
        Write {$count} objective practice questions for a Nigerian {$level}
        student, {$exam}.

        Subject: {$subject}
        Topic: {$topic}

        Rules:
        - Four options each, exactly one correct.
        - Wrong options must be the mistakes students actually make.
        - answer_index is the 0-based position of the correct option.
        - Vary the difficulty across the set.
        - Never present an invented question as a real past paper. Do not name a
          year or a body as though this came from it; the fields below are for
          categorising the topic, not for claiming provenance.

        Reply with JSON only:
        {"questions": [
          {"prompt": "...", "options": ["a","b","c","d"], "answer_index": 0,
           "explanation": "...", "topic": "the sub-topic this checks"}
        ]}
        PROMPT;

        $cleaned = $this->objectives($this->ask($prompt, 0.6, 6000, 'questions'), $count);

        if (! $cleaned) {
            return null;
        }

        return array_map(fn ($q) => $q + [
            'topic' => $q['topic'] ?? $topic,
        ], $cleaned);
    }

    /**
     * Shared cleaning for anything shaped like a multiple-choice question.
     *
     * The answer index is the field that matters: one that points past the end
     * of the options marks a correct student wrong and nothing on the screen
     * looks broken. Those are dropped here rather than written to the database.
     */
    private function objectives(?array $parsed, int $count): ?array
    {
        $rows = $parsed['questions'] ?? null;

        if (! is_array($rows)) {
            return null;
        }

        $clean = [];

        foreach ($rows as $row) {
            $prompt = trim((string) ($row['prompt'] ?? ''));
            $options = array_values(array_filter(
                array_map(fn ($o) => trim((string) $o), (array) ($row['options'] ?? [])),
                fn ($o) => $o !== ''
            ));
            $answer = (int) ($row['answer_index'] ?? -1);

            if ($prompt === '' || count($options) < 2 || ! isset($options[$answer])) {
                continue;
            }

            $clean[] = [
                'prompt' => $prompt,
                'options' => $options,
                'answer_index' => $answer,
                'explanation' => trim((string) ($row['explanation'] ?? '')),
                'topic' => trim((string) ($row['topic'] ?? '')) ?: null,
            ];

            if (count($clean) >= $count) {
                break;
            }
        }

        return $clean ?: null;
    }

    /* ------------------------------------------------------------------- */

    private function ask(string $prompt, float $temperature, int $maxTokens, string $what): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::withToken($this->key)
                ->timeout($this->timeout)
                ->acceptJson()
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ]);

            if ($response->failed()) {
                Log::warning('Course writing call failed', [
                    'what' => $what,
                    'status' => $response->status(),
                    'body' => str($response->body())->limit(400),
                ]);

                return null;
            }

            return $this->json((string) data_get($response->json(), 'choices.0.message.content'));
        } catch (\Throwable $e) {
            Log::warning('Course writing call threw', ['what' => $what, 'message' => $e->getMessage()]);

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

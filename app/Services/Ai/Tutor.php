<?php

namespace App\Services\Ai;

use Anthropic\Client;
use App\Models\Topic;
use Illuminate\Support\Facades\Log;

/**
 * The AI tutor, answered by Claude.
 *
 * The device already ships a rule-based tutor that works in flight mode. This
 * is the online tier: with network, the question comes here and gets a real
 * answer, cached afterwards so the next student asking the same thing gets it
 * instantly — including offline, once the cache ships inside a content pack.
 *
 * Returns null on any failure and the caller falls back to the on-device
 * tutor, so a dead key or a dropped connection degrades to the offline
 * experience rather than to an error message in a student's face.
 */
class Tutor
{
    public function __construct(
        private readonly ?string $key,
        private readonly string $model,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->key);
    }

    /**
     * @param  array  $context  ['subject','topic','level','locale' => en|pcm|both]
     */
    public function answer(string $question, array $context = []): ?string
    {
        if (! $this->isConfigured() || trim($question) === '') {
            return null;
        }

        $subject = $context['subject'] ?? 'their subjects';
        $topic = $context['topic'] ?? null;
        $level = $context['level'] ?? 'SS2';
        $locale = $context['locale'] ?? 'both';

        $voice = match ($locale) {
            'pcm' => 'Reply entirely in Nigerian Pidgin — the natural Pidgin a Lagos '
                . 'teacher would use with a student, not a caricature of it.',
            'en' => 'Reply in clear, simple English.',
            default => 'Reply in English, but drop into Nigerian Pidgin for the one '
                . 'sentence carrying the hardest idea, the way a good Nigerian teacher '
                . 'switches register to make something land.',
        };

        $system = "You are the ClassMate 9ja tutor, teaching a Nigerian {$level} student "
            . "following the WAEC and JAMB syllabus. Subject: {$subject}.";

        if ($topic) {
            $system .= "\nThe student is currently on the topic: {$topic}.";
        }

        $system .= "\n\n{$voice}\n\n" . <<<'GUIDE'
        How to answer:
        - Lead with the idea in plain words before any formula.
        - Use one everyday Nigerian example where it genuinely explains something —
          market trading, danfo fares, NEPA and generators, recharge cards, football.
          Never as decoration.
        - Show working line by line when it is a calculation.
        - Name the mistake students usually make here, and why it is wrong.
        - Stay under 220 words. A student on a small screen with little patience has
          to reach the useful part immediately.
        - Never invent a WAEC or JAMB past question and present it as real. If you
          write a practice question, say that is what it is.
        GUIDE;

        try {
            $message = (new Client(apiKey: $this->key))->messages->create(
                model: $this->model,
                maxTokens: 1200,
                // Identical for every student on this topic, so it caches.
                system: [
                    ['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']],
                ],
                messages: [['role' => 'user', 'content' => $question]],
            );

            if ($message->stopReason === 'refusal') {
                Log::info('Tutor declined a question');

                return null;
            }

            $reply = '';
            foreach ($message->content as $block) {
                if (($block->type ?? null) === 'text') {
                    $reply .= $block->text;
                }
            }

            $reply = trim($reply);

            return $reply !== '' ? $reply : null;
        } catch (\Throwable $e) {
            Log::warning('Tutor call failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /** A stable cache key: the same question on the same topic is asked constantly. */
    public function cacheKey(string $question, array $context = []): string
    {
        return 'tutor:' . sha1(implode('|', [
            mb_strtolower(trim(preg_replace('/\s+/', ' ', $question))),
            $context['topic'] ?? '',
            $context['subject'] ?? '',
            $context['locale'] ?? 'both',
        ]));
    }

    public static function contextForTopic(?Topic $topic): array
    {
        if (! $topic) {
            return [];
        }

        return ['topic' => $topic->title, 'subject' => $topic->subject?->name];
    }
}

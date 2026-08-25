<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\Ai\Tutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class TutorController extends Controller
{
    public function __construct(private readonly Tutor $tutor) {}

    /**
     * Answer one question.
     *
     * Three tiers, in order: a cached answer (free and instant), a live model
     * call, or nothing — and "nothing" is a normal outcome, not an error. The
     * app falls back to its on-device tutor whenever `answered` is false, which
     * is exactly what happens with no key configured.
     */
    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'min:2', 'max:1000'],
            'subject' => ['nullable', 'string', 'max:64'],
            'topicRef' => ['nullable', 'string', 'max:32'],
            'locale' => ['nullable', 'in:en,pcm,both'],
        ]);

        $user = auth()->user();

        // A question is cheap for the student and not for us, so the ceiling is
        // generous but real.
        $limiterKey = 'tutor:' . $user->id;
        if (RateLimiter::tooManyAttempts($limiterKey, 40)) {
            return response()->json([
                'answered' => false,
                'reason' => 'rate-limited',
                'retryAfter' => RateLimiter::availableIn($limiterKey),
            ], 429);
        }

        // `nullable` does not put the key in $data when the field is absent,
        // so every optional field is read with a default.
        $subject = ! empty($data['subject'])
            ? Subject::where('slug', $data['subject'])->first()
            : null;

        $topic = ($subject && ! empty($data['topicRef']))
            ? Topic::where('subject_id', $subject->id)->where('ref', $data['topicRef'])->first()
            : null;

        $context = array_filter([
            'subject' => $subject?->name,
            'topic' => $topic?->title,
            'level' => $user->level ?? 'SS2',
            'locale' => $data['locale'] ?? $user->locale ?? 'both',
        ]);

        if (! $this->tutor->isConfigured()) {
            return response()->json([
                'answered' => false,
                'reason' => 'not-configured',
            ]);
        }

        $key = $this->tutor->cacheKey($data['question'], $context);

        // The shared cache is what makes this get cheaper the more it is used:
        // thirty students on the same topic ask nearly the same question.
        $cached = Cache::get($key);
        if ($cached) {
            return response()->json([
                'answered' => true,
                'reply' => $cached,
                'cached' => true,
            ]);
        }

        RateLimiter::hit($limiterKey, 3600);

        $reply = $this->tutor->answer($data['question'], $context);

        if ($reply === null) {
            return response()->json([
                'answered' => false,
                'reason' => 'unavailable',
            ]);
        }

        Cache::put($key, $reply, now()->addDays(30));

        return response()->json([
            'answered' => true,
            'reply' => $reply,
            'cached' => false,
        ]);
    }
}

<?php

namespace App\Providers;

use App\Services\Ai\ClaudeMarker;
use App\Services\Ai\CourseWriter;
use App\Services\Ai\Marker;
use App\Services\Ai\NullCourseWriter;
use App\Services\Ai\NullMarker;
use App\Services\Ai\OpenAiCourseWriter;
use App\Services\Ai\OpenAiMarker;
use App\Services\Ai\Tutor;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the AI layer.
 *
 * Two providers are implemented; AI_DRIVER picks one. Either way a missing key
 * resolves to NullMarker, so a half-finished .env can never take the platform
 * down — objective marking still runs in PHP and written answers wait for a
 * teacher.
 */
class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Marker::class, function () {
            return match ($this->activeDriver()) {
                'openai' => new OpenAiMarker(
                    key: config('ai.openai.key'),
                    baseUrl: rtrim((string) config('ai.openai.base_url'), '/'),
                    model: config('ai.model'),
                    timeout: (int) config('ai.timeout', 60),
                ),
                'claude' => new ClaudeMarker(
                    key: config('ai.anthropic.key'),
                    model: config('ai.model'),
                ),
                default => new NullMarker(),
            };
        });

        /*
        | Writing curriculum, as opposed to marking it.
        |
        | OpenAI-only for now, and given its own timeout: a lesson is a much
        | longer generation than a mark, and the marking timeout is tuned to
        | fit two calls inside a 90-second worker.
        */
        $this->app->singleton(CourseWriter::class, function () {
            if ($this->activeDriver() !== 'openai') {
                return new NullCourseWriter();
            }

            return new OpenAiCourseWriter(
                key: config('ai.openai.key'),
                baseUrl: rtrim((string) config('ai.openai.base_url'), '/'),
                model: config('ai.writer_model') ?: config('ai.model'),
                timeout: (int) config('ai.writer_timeout', 90),
            );
        });

        $this->app->singleton(Tutor::class, function () {
            // The tutor is OpenAI-only for now; on the Claude driver it falls
            // back to the on-device tutor rather than pretending to be wired.
            $ready = $this->activeDriver() === 'openai';

            return new Tutor(
                key: $ready ? config('ai.openai.key') : null,
                baseUrl: rtrim((string) config('ai.openai.base_url'), '/'),
                model: config('ai.tutor_model') ?: config('ai.model'),
                timeout: (int) config('ai.timeout', 45),
            );
        });
    }

    /** The configured driver, or none when its key is missing. */
    private function activeDriver(): ?string
    {
        $driver = config('ai.driver');

        if ($driver === 'openai' && filled(config('ai.openai.key'))) {
            return 'openai';
        }

        if ($driver === 'claude' && filled(config('ai.anthropic.key'))) {
            return 'claude';
        }

        return null;
    }
}

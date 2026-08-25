<?php

namespace App\Providers;

use App\Services\Ai\ClaudeMarker;
use App\Services\Ai\Marker;
use App\Services\Ai\NullMarker;
use App\Services\Ai\Tutor;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Marker::class, function () {
            // A configured driver with no key still resolves to null, so a
            // half-finished .env can never take the platform down.
            if (! $this->ready()) {
                return new NullMarker();
            }

            return new ClaudeMarker(
                key: config('ai.anthropic.key'),
                model: config('ai.model'),
            );
        });

        $this->app->singleton(Tutor::class, fn () => new Tutor(
            key: $this->ready() ? config('ai.anthropic.key') : null,
            model: config('ai.tutor_model') ?: config('ai.model'),
        ));
    }

    private function ready(): bool
    {
        return config('ai.driver') === 'claude' && filled(config('ai.anthropic.key'));
    }
}

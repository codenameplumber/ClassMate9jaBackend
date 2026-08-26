<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One admin request to have a course written, and what became of it.
 *
 * Kept as a record rather than a fire-and-forget job because generation is
 * slow, costs money, and can half-fail. When it does, an admin needs to see
 * which part landed and why the rest did not — a subject that silently never
 * filled up is the worst possible outcome.
 */
class CourseGeneration extends Model
{
    protected $fillable = [
        'subject_id', 'level', 'requested_by', 'brief', 'status',
        'topics_written', 'lessons_written', 'questions_written',
        'error', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }

    public function isRunning(): bool { return in_array($this->status, ['queued', 'running'], true); }
    public function isDone(): bool { return $this->status === 'done'; }
    public function hasFailed(): bool { return $this->status === 'failed'; }

    public function markRunning(): void
    {
        $this->update(['status' => 'running', 'started_at' => now()]);
    }

    public function markDone(): void
    {
        $this->update(['status' => 'done', 'finished_at' => now()]);
    }

    public function markFailed(string $reason): void
    {
        $this->update([
            'status' => 'failed',
            'error' => str($reason)->limit(2000),
            'finished_at' => now(),
        ]);
    }

    /** How long it ran, for the admin list. */
    public function duration(): ?string
    {
        if (! $this->started_at || ! $this->finished_at) {
            return null;
        }

        $seconds = $this->finished_at->diffInSeconds($this->started_at);

        return $seconds < 60
            ? $seconds . 's'
            : intdiv($seconds, 60) . 'm ' . ($seconds % 60) . 's';
    }
}

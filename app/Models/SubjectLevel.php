<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One subject, taught at one level — the unit a student is actually given.
 *
 * A subject on its own cannot be offered to anyone: Further Maths does not
 * exist at JSS2 and Basic Science does not exist at SS3. This row is the
 * declaration that it does exist somewhere, and how far along it is.
 */
class SubjectLevel extends Model
{
    protected $fillable = [
        'subject_id', 'level', 'status', 'topic_count', 'question_count', 'published_at',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }

    /** The topics that fill this offering. */
    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class, 'subject_id', 'subject_id')
            ->where('topics.level', $this->level)
            ->orderBy('week')
            ->orderBy('position');
    }

    public function isPublished(): bool { return $this->status === 'published'; }

    public function isGenerating(): bool { return $this->status === 'generating'; }

    /** An offering with no content is a promise, not a course. */
    public function isEmpty(): bool { return $this->topic_count === 0; }

    public function scopePublished($query) { return $query->where('status', 'published'); }

    /**
     * Recount from the content actually on disk and publish if there is any.
     *
     * Counters are derived rather than incremented, so a half-finished
     * generation that gets re-run cannot leave the number lying.
     */
    public function refreshCounts(): void
    {
        $topics = Topic::where('subject_id', $this->subject_id)
            ->where('level', $this->level)
            ->count();

        $questions = Question::where('subject_id', $this->subject_id)
            ->where('level', $this->level)
            ->count();

        $this->update([
            'topic_count' => $topics,
            'question_count' => $questions,
            'status' => $topics > 0 ? 'published' : $this->status,
            'published_at' => $topics > 0 ? ($this->published_at ?? now()) : null,
        ]);
    }
}

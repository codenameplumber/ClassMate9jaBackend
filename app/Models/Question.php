<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Question extends Model
{
    protected $fillable = [
        'ref', 'subject_id', 'exam_body', 'year',
        'topic', 'prompt', 'options', 'answer_index', 'explanation',
        'level', 'source', 'reviewed_at', 'reviewed_by',
    ];

    protected function casts(): array
    {
        return ['options' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }

    /** Written by a model, and not yet checked by anyone. */
    public function isUnreviewedAi(): bool
    {
        return $this->source === 'ai' && $this->reviewed_at === null;
    }

    /**
     * The answer key points at an option that exists.
     *
     * Cheap, and worth running on everything a model writes: an index past the
     * end of the options array marks a correct student wrong and nothing about
     * the screen would look broken.
     */
    public function hasUsableAnswer(): bool
    {
        return is_array($this->options)
            && count($this->options) >= 2
            && isset($this->options[$this->answer_index]);
    }

    public function scopeForLevel($query, ?string $level)
    {
        return $level ? $query->where('level', $level) : $query;
    }

    public function scopeFilter($query, array $f)
    {
        return $query
            ->when($f['subject_id'] ?? null, fn ($q, $v) => $q->where('subject_id', $v))
            ->when($f['exam_body'] ?? null, fn ($q, $v) => $q->where('exam_body', $v))
            ->when($f['year'] ?? null, fn ($q, $v) => $q->where('year', $v))
            ->when($f['topic'] ?? null, fn ($q, $v) => $q->where('topic', $v))
            ->when($f['level'] ?? null, fn ($q, $v) => $q->where('level', $v));
    }
}

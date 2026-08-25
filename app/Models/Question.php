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
    ];

    protected function casts(): array
    {
        return ['options' => 'array'];
    }

    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }

    public function scopeFilter($query, array $f)
    {
        return $query
            ->when($f['subject_id'] ?? null, fn ($q, $v) => $q->where('subject_id', $v))
            ->when($f['exam_body'] ?? null, fn ($q, $v) => $q->where('exam_body', $v))
            ->when($f['year'] ?? null, fn ($q, $v) => $q->where('year', $v))
            ->when($f['topic'] ?? null, fn ($q, $v) => $q->where('topic', $v));
    }
}

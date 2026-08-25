<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Assignment extends Model
{
    protected $fillable = [
        'classroom_id', 'topic_id', 'created_by', 'title', 'kind',
        'ai_generated', 'question_count', 'due_on', 'published_at',
    ];

    protected function casts(): array
    {
        return ['ai_generated' => 'boolean', 'due_on' => 'date', 'published_at' => 'datetime'];
    }

    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
    public function topic(): BelongsTo { return $this->belongsTo(Topic::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function questions(): HasMany { return $this->hasMany(AssignmentQuestion::class)->orderBy('position'); }
    public function submissions(): HasMany { return $this->hasMany(Submission::class); }

    public function maxScore(): int
    {
        return (int) $this->questions()->sum('max_mark');
    }
}

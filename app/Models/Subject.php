<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Subject extends Model
{
    protected $fillable = [
        'slug', 'name', 'short_name', 'emoji', 'tone',
        'is_core', 'pack_mb', 'topic_count', 'blurb', 'status',
    ];

    protected function casts(): array
    {
        return ['is_core' => 'boolean'];
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class)->orderBy('week')->orderBy('position');
    }

    public function questions(): HasMany { return $this->hasMany(Question::class); }

    /** Every level this subject is declared to be taught at. */
    public function levels(): HasMany
    {
        return $this->hasMany(SubjectLevel::class);
    }

    /** The levels a student can actually be given content for today. */
    public function publishedLevels(): array
    {
        return $this->levels->where('status', 'published')->pluck('level')->all();
    }

    public function offersLevel(?string $level): bool
    {
        return $level !== null && in_array($level, $this->publishedLevels(), true);
    }

    /** Only subjects with at least one level ready to teach. */
    public function scopeTeachable($query)
    {
        return $query->where('status', 'published')
            ->whereHas('levels', fn ($q) => $q->where('status', 'published'));
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'enrollments');
    }

    /** Classes running on this subject -- checked before a course is removed. */
    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    /** Teachers assigned to it, at any level. */
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'teacher_subject')->withPivot('level');
    }

    public function getRouteKeyName(): string { return 'slug'; }

    /** Weeks 1-12, each with its topics -- the shape every scheme screen wants. */
    public function scheme(?string $level = null)
    {
        $topics = $level
            ? $this->topics->where('level', $level)
            : $this->topics;

        return $topics->groupBy('week')->map(fn ($topics, $week) => [
            'week' => $week,
            'title' => $topics->first()->week_title,
            'topics' => $topics,
        ])->values();
    }
}

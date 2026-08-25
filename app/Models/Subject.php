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
        'is_core', 'pack_mb', 'topic_count', 'blurb',
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

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'enrollments');
    }

    public function getRouteKeyName(): string { return 'slug'; }

    /** Weeks 1-12, each with its topics -- the shape every scheme screen wants. */
    public function scheme()
    {
        return $this->topics->groupBy('week')->map(fn ($topics, $week) => [
            'week' => $week,
            'title' => $topics->first()->week_title,
            'topics' => $topics,
        ])->values();
    }
}

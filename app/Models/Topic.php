<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Topic extends Model
{
    protected $fillable = [
        'subject_id', 'level', 'ref', 'week', 'week_title',
        'title', 'minutes', 'position', 'has_rich_lesson',
    ];

    protected function casts(): array
    {
        return ['has_rich_lesson' => 'boolean'];
    }

    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }

    public function scopeForLevel($query, ?string $level)
    {
        return $level ? $query->where('level', $level) : $query;
    }
    public function lessons(): HasMany { return $this->hasMany(Lesson::class); }
    public function classwork(): HasMany { return $this->hasMany(ClassworkQuestion::class); }
    public function completions(): HasMany { return $this->hasMany(LessonCompletion::class); }

    /** Falls back to English when a locale has not been authored yet. */
    public function lessonFor(string $locale = 'en'): ?Lesson
    {
        return $this->lessons->firstWhere('locale', $locale)
            ?? $this->lessons->firstWhere('locale', 'en');
    }
}

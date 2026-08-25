<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Classroom extends Model
{
    protected $fillable = [
        'school_id', 'teacher_id', 'subject_id', 'name', 'level', 'room', 'join_code',
    ];

    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function assignments(): HasMany { return $this->hasMany(Assignment::class)->latest(); }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'classroom_student')->withTimestamps();
    }
}

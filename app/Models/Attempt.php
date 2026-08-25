<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Attempt extends Model
{
    protected $fillable = [
        'user_id', 'subject_id', 'kind', 'title', 'score', 'total',
        'minutes', 'auto_submitted', 'client_uuid', 'submitted_at',
    ];

    protected function casts(): array
    {
        return ['auto_submitted' => 'boolean', 'submitted_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
    public function answers(): HasMany { return $this->hasMany(AttemptAnswer::class); }

    public function percent(): int
    {
        return $this->total > 0 ? (int) round($this->score / $this->total * 100) : 0;
    }
}

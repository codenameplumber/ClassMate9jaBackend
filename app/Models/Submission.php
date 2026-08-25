<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Submission extends Model
{
    protected $fillable = [
        'assignment_id', 'user_id', 'client_uuid', 'ai_score', 'final_score',
        'max_score', 'status', 'needs_review', 'submitted_at', 'approved_at', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'needs_review' => 'boolean',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo { return $this->belongsTo(Assignment::class); }
    public function student(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function answers(): HasMany { return $this->hasMany(SubmissionAnswer::class); }

    /** The teacher's number always wins over the AI's. */
    public function score(): int
    {
        return (int) ($this->final_score ?? $this->ai_score ?? 0);
    }

    public function percent(): int
    {
        return $this->max_score > 0 ? (int) round($this->score() / $this->max_score * 100) : 0;
    }

    public function isApproved(): bool { return $this->status === 'approved'; }
}

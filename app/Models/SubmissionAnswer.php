<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class SubmissionAnswer extends Model
{
    protected $fillable = [
        'submission_id', 'assignment_question_id', 'answer_text', 'chosen',
        'ai_mark', 'ai_confidence', 'ai_comment', 'teacher_mark', 'teacher_comment',
    ];

    public function submission(): BelongsTo { return $this->belongsTo(Submission::class); }
    public function question(): BelongsTo { return $this->belongsTo(AssignmentQuestion::class, 'assignment_question_id'); }

    /** What the student actually receives: a teacher override, else the AI mark. */
    public function mark(): int
    {
        return (int) ($this->teacher_mark ?? $this->ai_mark ?? 0);
    }

    public function wasOverridden(): bool
    {
        return $this->teacher_mark !== null && $this->teacher_mark !== $this->ai_mark;
    }
}

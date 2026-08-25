<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class AssignmentQuestion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'assignment_id', 'position', 'kind', 'prompt',
        'options', 'answer_index', 'expected', 'max_mark',
    ];

    protected function casts(): array
    {
        return ['options' => 'array'];
    }

    public function assignment(): BelongsTo { return $this->belongsTo(Assignment::class); }

    public function isWritten(): bool { return $this->kind === 'written'; }
}

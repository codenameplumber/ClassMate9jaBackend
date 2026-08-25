<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class AttemptAnswer extends Model
{
    public $timestamps = false;

    protected $fillable = ['attempt_id', 'question_id', 'topic', 'chosen', 'is_correct'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }

    public function attempt(): BelongsTo { return $this->belongsTo(Attempt::class); }
}

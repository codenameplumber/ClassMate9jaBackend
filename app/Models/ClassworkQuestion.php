<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ClassworkQuestion extends Model
{
    protected $fillable = ['topic_id', 'prompt', 'options', 'answer_index', 'explanation'];

    protected function casts(): array
    {
        return ['options' => 'array'];
    }

    public function topic(): BelongsTo { return $this->belongsTo(Topic::class); }
}

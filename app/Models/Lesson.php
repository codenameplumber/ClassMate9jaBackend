<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Lesson extends Model
{
    protected $fillable = ['topic_id', 'locale', 'title', 'objectives', 'body', 'version'];

    protected function casts(): array
    {
        return ['objectives' => 'array', 'body' => 'array'];
    }

    public function topic(): BelongsTo { return $this->belongsTo(Topic::class); }
}

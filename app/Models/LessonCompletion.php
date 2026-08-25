<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class LessonCompletion extends Model
{
    protected $fillable = ['user_id', 'topic_id', 'client_uuid', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function topic(): BelongsTo { return $this->belongsTo(Topic::class); }
}

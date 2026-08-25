<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class StudySession extends Model
{
    protected $fillable = ['user_id', 'minutes', 'occurred_on', 'source', 'client_uuid'];

    protected function casts(): array
    {
        return ['occurred_on' => 'date'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class StudentState extends Model
{
    protected $fillable = ['user_id', 'payload', 'version', 'device_updated_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'device_updated_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class School extends Model
{
    protected $fillable = ['name', 'state', 'lga', 'type'];

    public function users(): HasMany { return $this->hasMany(User::class); }
    public function classrooms(): HasMany { return $this->hasMany(Classroom::class); }
}
